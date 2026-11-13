<?php

declare(strict_types=1);

/**
 * Only web entry point. The web server document root must point at public/.
 * Everything else (config, migrations, logs, tests, backups) lives outside it.
 */
$root = dirname(__DIR__);

require $root . '/app/Bootstrap.php';

try {
    $app = App\Bootstrap::create($root);
    $app->handle(App\Request::fromGlobals())->send();
} catch (Throwable $error) {
    // Bootstrap failures (e.g. missing config) must not leak paths or traces.
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '服务暂时不可用。';
}
