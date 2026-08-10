<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;

interface ExporterInterface
{
    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    public function export(array $rows, ReportFilter $filter, string $tenant): void;
}
