<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Logger;
use App\Request;
use App\Response;
use Throwable;

final class HealthController
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly string $root
    ) {
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $database = ['status' => 'not_configured', 'detail' => '尚未配置独立开发库'];

        if ($this->config->isDbConfigured()) {
            try {
                $pdo = Database::connect($this->config->db());
                $pdo->query('SELECT 1');
                $database = ['status' => 'ok'];
            } catch (Throwable $error) {
                // Never return or log DSN, account or credential values.
                $this->logger->error('health.database', ['type' => get_class($error)]);
                $database = ['status' => 'unavailable', 'detail' => '开发库连接失败'];
            }
        }

        return Response::json([
            'app' => 'ok',
            'env' => $this->config->get('app_env'),
            'timezone' => date_default_timezone_get(),
            'database' => $database,
            'stage' => 'P06',
        ]);
    }
}
