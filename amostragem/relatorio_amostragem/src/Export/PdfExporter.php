<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;

final class PdfExporter extends AbstractExporter
{
    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.pdf', $tenant, date('Ymd_His'));
        $this->sendHeaders($filename, 'application/pdf');

        if (class_exists('\\Dompdf\\Dompdf')) {
            $this->exportWithDompdf($rows, $filter, $tenant);
            return;
        }

        echo $this->buildSimplePdf($rows, $filter, $tenant);
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function exportWithDompdf(array $rows, ReportFilter $filter, string $tenant): void
    {
        $html = $this->buildHtml($rows, $filter, $tenant);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        echo $dompdf->output();
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildHtml(array $rows, ReportFilter $filter, string $tenant): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $bodyRows = '';
        foreach ($rows as $row) {
            $bodyRows .= '<tr>'
                . '<td>' . $escape((string) ($row['id'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['numero_caixa'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['numero_documento'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['assunto'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['tipo_assunto'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['link_documento'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['paginas'] ?? '')) . '</td>'
                . '<td>' . $escape((string) ($row['data_registro'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#222}'
            . 'h1{font-size:16px;margin:0 0 6px}'
            . '.meta{margin-bottom:10px;font-size:11px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{border:1px solid #999;padding:4px 6px;text-align:left}'
            . 'th{background:#efefef;font-weight:bold}'
            . '</style></head><body>'
            . '<h1>Relatorio de Percentual de Amostragem</h1>'
            . '<div class="meta">Tenant: ' . $escape($tenant)
            . ' | Periodo: ' . $escape($filter->dataInicio) . ' a ' . $escape($filter->dataFim)
            . ' | Percentual: ' . $escape($filter->percentual) . '%'
            . ' | Setor: ' . $escape($filter->setor)
            . '</div>'
            . '<table><thead><tr>'
            . '<th>ID</th><th>N&ordm; Caixa</th><th>N&ordm; Doc</th>'
            . '<th>Assunto</th><th>Tipo Assunto</th><th>Link Doc</th>'
            . '<th>P&aacute;ginas</th><th>Data Envio</th>'
            . '</tr></thead><tbody>'
            . $bodyRows
            . '</tbody></table></body></html>';
    }

    /**
     * Fallback: gera um PDF válido (sem Dompdf) via estrutura PDF 1.4 manual.
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildSimplePdf(array $rows, ReportFilter $filter, string $tenant): string
    {
        // Prepara linhas de texto (somente ASCII/Latin-1 para fontes Type1)
        $safe = static function (string $s): string {
            $out = preg_replace('/[^\x20-\x7E]/', '?', (string) $s);
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $out);
        };

        $lines = [
            'Relatorio de Percentual de Amostragem',
            'Tenant: ' . $tenant . '   Periodo: ' . $filter->dataInicio . ' a ' . $filter->dataFim,
            'Percentual: ' . $filter->percentual . '%   Setor: ' . $filter->setor,
            str_repeat('-', 110),
            'ID       | Caixa       | Doc Num      | Assunto             | Tipo         | Pag | Data Envio',
            str_repeat('-', 110),
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%-8s | %-11s | %-12s | %-19s | %-12s | %-3s | %s',
                mb_strimwidth((string) ($row['id'] ?? ''), 0, 8),
                mb_strimwidth((string) ($row['numero_caixa'] ?? ''), 0, 11),
                mb_strimwidth((string) ($row['numero_documento'] ?? ''), 0, 12),
                mb_strimwidth((string) ($row['assunto'] ?? ''), 0, 19),
                mb_strimwidth((string) ($row['tipo_assunto'] ?? ''), 0, 12),
                (string) ($row['paginas'] ?? ''),
                (string) ($row['data_registro'] ?? '')
            );
        }

        // Monta o content stream PDF
        $y = 820;
        $lineHeight = 13;
        $streamParts = ['BT', '/F1 8 Tf'];

        foreach ($lines as $line) {
            if ($y < 20) {
                break;
            }

            $streamParts[] = '1 0 0 1 20 ' . $y . ' Tm';
            $streamParts[] = '(' . $safe((string) $line) . ') Tj';
            $y -= $lineHeight;
        }

        $streamParts[] = 'ET';
        $stream = implode("\n", $streamParts);
        $streamLen = strlen($stream);

        // Objetos PDF indexados (1–5)
        $objects = [
            1 => '<</Type /Catalog /Pages 2 0 R>>',
            2 => '<</Type /Pages /Kids [3 0 R] /Count 1>>',
            3 => '<</Type /Page /Parent 2 0 R /MediaBox [0 0 1190 842] /Resources <</Font <</F1 4 0 R>>>> /Contents 5 0 R>>',
            4 => '<</Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding>>',
            5 => "<</Length {$streamLen}>>\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $objCount = count($objects) + 1;

        $pdf .= "xref\n0 {$objCount}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $objCount; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<</Size {$objCount} /Root 1 0 R>>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
