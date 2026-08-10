<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'RelatorioAmostragem\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

function apply_report_db_defaults(): void
{
    // Mantida para compatibilidade, sem aplicar defaults sensiveis.
}

apply_report_db_defaults();

function create_report_pdo(?string $tenant = null): PDO
{
    $host = get_required_env_value(['DB_SERVER', 'DB_HOST']);
    $port = getenv('DB_SERVER_PORT') ?: getenv('DB_PORT') ?: '3306';
    $database = resolve_report_database_name($tenant);
    $user = get_required_env_value(['DB_SERVER_USERNAME', 'DB_USER']);
    $password = get_required_env_value(['DB_SERVER_PASSWORD', 'DB_PASSWORD']);

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function resolve_report_database_name(?string $tenant = null): string
{
    $resolvedTenant = strtolower(trim((string) ($tenant ?? resolve_report_tenant_from_request())));

    if ($resolvedTenant === 'cipemac') {
        return (string) (getenv('DB_DATABASE_CIPEMAC') ?: 'sisged_cipemac');
    }

    return (string) (getenv('DB_DATABASE_GEA') ?: getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'sisged_gea');
}

function resolve_report_tenant_from_request(): string
{
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');

    if ($host !== '' && strpos($host, ',') !== false) {
        $parts = explode(',', $host);
        $host = trim($parts[0]);
    }

    $host = strtolower(trim($host));

    if ($host !== '' && strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }

    return str_contains($host, 'cipemac') ? 'cipemac' : 'ged';
}

function get_required_env_value(array $keys): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }

        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
    }

    throw new RuntimeException('Variavel de ambiente obrigatoria ausente para conexao do relatorio: ' . implode(' ou ', $keys));
}
