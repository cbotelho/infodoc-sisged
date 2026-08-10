<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;
use ZipArchive;

final class DocxExporter extends AbstractExporter
{
    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.docx', $tenant, date('Ymd_His'));
        $this->sendHeaders(
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        if (class_exists('\\PhpOffice\\PhpWord\\PhpWord')) {
            $this->exportWithPhpWord($rows, $tenant, $filter);
            return;
        }

        echo $this->buildSimpleDocx($rows, $tenant, $filter);
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function exportWithPhpWord(array $rows, string $tenant, ReportFilter $filter): void
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        $section->addText('Relatorio de Percentual de Amostragem');
        $section->addText('Tenant: ' . $tenant);
        $section->addText('Periodo: ' . $filter->dataInicio . ' a ' . $filter->dataFim);
        $section->addText('Percentual: ' . $filter->percentual . '%');
        $section->addText('Setor: ' . $filter->setor);
        $section->addTextBreak(1);

        foreach ($this->buildTextLines($rows) as $line) {
            $section->addText($line);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildSimpleDocx(array $rows, string $tenant, ReportFilter $filter): string
    {
        $tmpDocx = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmpDocx === false) {
            return '';
        }

        $zip = new ZipArchive();
        $zip->open($tmpDocx, ZipArchive::OVERWRITE);

        $documentXml = $this->buildDocumentXml($rows, $tenant, $filter);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $content = (string) file_get_contents($tmpDocx);
        @unlink($tmpDocx);

        return $content;
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildDocumentXml(array $rows, string $tenant, ReportFilter $filter): string
    {
        $lines = [
            'Relatorio de Percentual de Amostragem',
            'Tenant: ' . $tenant,
            'Periodo: ' . $filter->dataInicio . ' a ' . $filter->dataFim,
            'Percentual: ' . $filter->percentual . '%',
            'Setor: ' . $filter->setor,
            str_repeat('-', 70),
        ];

        $lines = array_merge($lines, $this->buildTextLines($rows));

        $paragraphs = [];
        foreach ($lines as $line) {
            $escaped = htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $paragraphs[] = '<w:p><w:r><w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . implode('', $paragraphs) . '</w:body>'
            . '</w:document>';
    }
}
