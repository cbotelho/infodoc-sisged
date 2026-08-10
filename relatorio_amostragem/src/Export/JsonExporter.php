<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;

final class JsonExporter extends AbstractExporter
{
    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.json', $tenant, date('Ymd_His'));
        $this->sendHeaders($filename, 'application/json; charset=utf-8');

        $payload = [
            'tenant' => $tenant,
            'filtros' => [
                'data_inicio' => $filter->dataInicio,
                'data_fim' => $filter->dataFim,
                'percentual' => $filter->percentual,
                'setor' => $filter->setor,
                'formato' => $filter->formato,
            ],
            'total_registros' => count($rows),
            'dados' => $rows,
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
