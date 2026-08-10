<?php

declare(strict_types=1);

namespace RelatorioAmostragem;

final class TenantResolver
{
    public const GED = 'ged';
    public const CIPEMAC = 'cipemac';

    /**
     * @return self::GED|self::CIPEMAC
     */
    public function resolve(): string
    {
        $candidate = $this->fromRequest()
            ?? $this->fromSession()
            ?? $this->fromConstant()
            ?? $this->fromHost()
            ?? self::GED;

        $tenant = $this->normalizeTenant($candidate);
        $_SESSION['tenant'] = $tenant;

        return $tenant;
    }

    private function fromRequest(): ?string
    {
        $postTenant = filter_input(INPUT_POST, 'tenant', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (is_string($postTenant) && $postTenant !== '') {
            return $postTenant;
        }

        $getTenant = filter_input(INPUT_GET, 'tenant', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (is_string($getTenant) && $getTenant !== '') {
            return $getTenant;
        }

        return null;
    }

    private function fromSession(): ?string
    {
        if (!isset($_SESSION['tenant']) || !is_string($_SESSION['tenant'])) {
            return null;
        }

        return $_SESSION['tenant'];
    }

    private function fromConstant(): ?string
    {
        if (!defined('CURRENT_TENANT')) {
            return null;
        }

        $tenant = constant('CURRENT_TENANT');

        return is_string($tenant) ? $tenant : null;
    }

    private function fromHost(): ?string
    {
        $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');

        if ($host === '') {
            return null;
        }

        if (strpos($host, ',') !== false) {
            $parts = explode(',', $host);
            $host = trim($parts[0]);
        }

        $host = strtolower(trim($host));

        if (strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }

        return str_contains($host, 'cipemac') ? self::CIPEMAC : self::GED;
    }

    /**
     * @param string $tenant
     * @return self::GED|self::CIPEMAC
     */
    private function normalizeTenant(string $tenant): string
    {
        $normalized = strtolower(trim($tenant));

        if (in_array($normalized, [self::GED, self::CIPEMAC], true)) {
            return $normalized;
        }

        return self::GED;
    }
}
