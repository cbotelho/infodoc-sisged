<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;

final class TxtExporter extends AbstractExporter
{
    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.txt', $tenant, date('Ymd_His'));
        $this->sendHeaders($filename, 'text/plain; charset=utf-8');

        $header = [
            'Relatorio de Percentual de Amostragem',
            'Tenant: ' . $tenant,
            'Periodo: ' . $filter->dataInicio . ' a ' . $filter->dataFim,
            'Percentual: ' . $filter->percentual . '%',
            'Setor: ' . $filter->setor,
            str_repeat('-', 120),
        ];

        $lines = array_merge($header, $this->buildTextLines($rows));

        echo implode(PHP_EOL, $lines);
    }
}
