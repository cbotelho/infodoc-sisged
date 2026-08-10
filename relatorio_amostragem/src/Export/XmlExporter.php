<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;

final class XmlExporter extends AbstractExporter
{
    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.xml', $tenant, date('Ymd_His'));
        $this->sendHeaders($filename, 'application/xml; charset=utf-8');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');

        $filtros = $xml->addChild('filtros');
        $filtros->addChild('tenant', htmlspecialchars($tenant, ENT_XML1, 'UTF-8'));
        $filtros->addChild('data_inicio', htmlspecialchars($filter->dataInicio, ENT_XML1, 'UTF-8'));
        $filtros->addChild('data_fim', htmlspecialchars($filter->dataFim, ENT_XML1, 'UTF-8'));
        $filtros->addChild('percentual', htmlspecialchars($filter->percentual, ENT_XML1, 'UTF-8'));
        $filtros->addChild('setor', htmlspecialchars($filter->setor, ENT_XML1, 'UTF-8'));

        $dados = $xml->addChild('dados');
        foreach ($rows as $row) {
            $item = $dados->addChild('item');
            $item->addChild('id', (string) ($row['id'] ?? ''));
            $item->addChild('numero_caixa', htmlspecialchars((string) ($row['numero_caixa'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('numero_documento', htmlspecialchars((string) ($row['numero_documento'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('assunto', htmlspecialchars((string) ($row['assunto'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('tipo_assunto', htmlspecialchars((string) ($row['tipo_assunto'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('link_documento', htmlspecialchars((string) ($row['link_documento'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('paginas', (string) ($row['paginas'] ?? ''));
            $item->addChild('data_registro', (string) ($row['data_registro'] ?? ''));
            $item->addChild('setor', htmlspecialchars((string) ($row['setor'] ?? ''), ENT_XML1, 'UTF-8'));
            $item->addChild('percentual', (string) ($row['percentual'] ?? ''));
            $item->addChild('total_itens', (string) ($row['total_itens'] ?? ''));
            $item->addChild('itens_amostrados', (string) ($row['itens_amostrados'] ?? ''));
        }

        echo $xml->asXML();
    }
}
