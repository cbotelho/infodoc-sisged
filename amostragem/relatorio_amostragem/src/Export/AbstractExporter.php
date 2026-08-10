<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

abstract class AbstractExporter implements ExporterInterface
{
    protected function sendHeaders(string $filename, string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    protected function safeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'relatorio';

        return trim($filename, '_');
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     * @return array<int, string>
     */
    protected function buildTextLines(array $rows): array
    {
        $lines = ["ID\tNº CAIXA\tNº DOCUMENTO\tASSUNTO\tTIPO ASSUNTO\tLINK DOC\tPÁGINAS\tDATA ENVIO\tSETOR\tPERCENTUAL\tTOTAL\tAMOSTRADOS"];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s%%\t%s\t%s",
                (string) ($row['id'] ?? ''),
                (string) ($row['numero_caixa'] ?? ''),
                (string) ($row['numero_documento'] ?? ''),
                (string) ($row['assunto'] ?? ''),
                (string) ($row['tipo_assunto'] ?? ''),
                (string) ($row['link_documento'] ?? ''),
                (string) ($row['paginas'] ?? ''),
                (string) ($row['data_registro'] ?? ''),
                (string) ($row['setor'] ?? ''),
                (string) ($row['percentual'] ?? ''),
                (string) ($row['total_itens'] ?? ''),
                (string) ($row['itens_amostrados'] ?? '')
            );
        }

        return $lines;
    }
}
