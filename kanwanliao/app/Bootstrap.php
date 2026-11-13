<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Throwable;

final class Bootstrap
{
    private function __construct(
        private readonly string $root,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Router $router,
        private readonly ErrorHandler $errors,
        private readonly Services $services
    ) {
    }

    public static function create(string $root): self
    {
        spl_autoload_register(static function (string $class) use ($root): void {
            if (!str_starts_with($class, 'App\\')) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, 4));
            $file = $root . '/app/' . $relative . '.php';
            if (is_file($file)) {
                require $file;
            }
        });

        date_default_timezone_set('UTC');
        $config = Config::load($root);
        $logger = new Logger($config->absoluteLogPath($root), $config->isDebug());
        $errors = new ErrorHandler($logger, $root);
        $errors->register();

        $session = new NativeSession();
        $csrf = new Csrf($session);

        // A missing or broken database must not take down the whole site: read
        // pages render a 503 instead of pretending the forum is empty, and the
        // write side simply becomes unavailable.
        $reader = null;
        $accounts = null;
        $writer = null;
        $limiter = null;
        if ($config->isDbConfigured()) {
            try {
                $pdo = Database::connect($config->db());
                $reader = new ForumReadRepository($pdo);
                $accounts = new UserRepository($pdo);
                $writer = new ContentRepository($pdo);
                $limiter = new PdoRateLimiter($pdo);
            } catch (Throwable $error) {
                $logger->error('bootstrap.database', ['type' => get_class($error)]);
            }
        }

        $auth = new Auth($session, $accounts);
        $services = new Services($session, $auth, $csrf, $reader, $accounts, $writer, $limiter);

        /** @var array<int, array{0:string,1:string,2:array<int,string>}> $routes */
        $routes = require $root . '/config/routes.php';

        return new self($root, $config, $logger, new Router($routes), $errors, $services);
    }

    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request->method, $request->path);
            if ($match === null) {
                return $this->status(404, '页面不存在', '没有找到这个页面，换个入口看看吧。');
            }

            /** @var array<int, string> $handler */
            $handler = $match['handler'];
            [$class, $method] = $handler;
            if (!class_exists($class) || !method_exists($class, $method)) {
                throw new RuntimeException('Route handler unavailable.');
            }

            $controller = new $class(
                $this->config,
                $this->logger,
                $this->root,
                $this->services->reader,
                $this->services
            );
            $result = $controller->$method($request, $match['params']);
            if (!$result instanceof Response) {
                throw new RuntimeException('Invalid controller result.');
            }
            return $result;
        } catch (Throwable $error) {
            return $this->errors->render($error);
        }
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function services(): Services
    {
        return $this->services;
    }

    private function status(int $code, string $title, string $message): Response
    {
        $body = View::render($this->root, 'status', [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'authUser' => $this->services->auth->user(),
            'isAdmin' => $this->services->auth->isAdmin(),
            'csrfToken' => $this->services->csrf->token(),
        ]);
        return Response::html($body, $code);
    }
}
