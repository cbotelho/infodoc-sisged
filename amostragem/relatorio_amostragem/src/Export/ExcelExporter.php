<?php

declare(strict_types=1);

namespace RelatorioAmostragem\Export;

use RelatorioAmostragem\ReportFilter;
use ZipArchive;

final class ExcelExporter extends AbstractExporter
{
    /** @var list<string> */
    private const HEADERS = [
        'ID', 'Setor', 'Interessado', 'Páginas', 'Data', 'Arquivo',
    ];

    public function export(array $rows, ReportFilter $filter, string $tenant): void
    {
        $filename = sprintf('relatorio_amostragem_%s_%s.xlsx', $tenant, date('Ymd_His'));
        $this->sendHeaders(
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $this->exportWithPhpSpreadsheet($rows, $tenant);
            return;
        }

        // Fallback: XLSX real via ZipArchive (OpenXML completo).
        echo $this->buildXlsx($rows, $tenant);
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function exportWithPhpSpreadsheet(array $rows, string $tenant): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Amostragem');

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, (string) ($row['id'] ?? ''));
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, (string) ($row['setor'] ?? ''));
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, (string) ($row['interessado'] ?? ''));
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (string) ($row['paginas'] ?? ''));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (string) ($row['data_registro'] ?? ''));

            $arquivo = $this->normalizeDocumentUrl((string) ($row['arquivo'] ?? ''), $tenant);
            if ($arquivo !== '') {
                $sheet->setCellValueByColumnAndRow(6, $rowNumber, $arquivo);
                $sheet->getCellByColumnAndRow(6, $rowNumber)->getHyperlink()->setUrl($arquivo);
                $sheet->getStyleByColumnAndRow(6, $rowNumber)->getFont()->setUnderline(true)->getColor()->setARGB('FF0563C1');
            } else {
                $sheet->setCellValueByColumnAndRow(6, $rowNumber, '');
            }
            $rowNumber++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Gera um .xlsx válido (ZIP + OpenXML) sem dependências externas.
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildXlsx(array $rows, string $tenant): string
    {
        // ── 1. Monta XML da planilha ──────────────────────────────────────────
        $sheetXml = $this->buildSheetXml($rows, $tenant);

        // ── 2. Monta os demais XMLs do pacote OpenXML ─────────────────────────
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Amostragem" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
            . '</cellXfs>'
            . '</styleSheet>';

        // ── 3. Empacota tudo num ZIP em memória ───────────────────────────────
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpFile === false) {
            return '';
        }

        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $content = (string) file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $content;
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     */
    private function buildSheetXml(array $rows, string $tenant): string
    {
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cell = static fn(string $col, int $rowNum, string $value, int $style = 0): string =>
            sprintf('<c r="%s%d" t="inlineStr" s="%d"><is><t>%s</t></is></c>', $col, $rowNum, $style, $esc($value));

        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        // Cabeçalho com estilo negrito (s="1")
        $xml .= '<row r="1">';
        foreach (self::HEADERS as $i => $header) {
            $xml .= $cell($cols[$i], 1, $header, 1);
        }
        $xml .= '</row>';

        // Dados
        $rowFields = ['id', 'setor', 'interessado', 'paginas', 'data_registro', 'arquivo'];

        $rowNum = 2;
        foreach ($rows as $row) {
            $xml .= sprintf('<row r="%d">', $rowNum);
            foreach ($rowFields as $i => $field) {
                $value = (string) ($row[$field] ?? '');

                if ($field === 'arquivo') {
                    $value = $this->normalizeDocumentUrl($value, $tenant);
                }

                if ($field === 'arquivo' && $value !== '') {
                    $formula = htmlspecialchars(sprintf('HYPERLINK("%s","%s")', $value, $value), ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
                    $xml .= sprintf('<c r="%s%d" t="str"><f>%s</f></c>', $cols[$i], $rowNum, $formula);
                    continue;
                }

                $xml .= $cell($cols[$i], $rowNum, $value);
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function normalizeDocumentUrl(string $value, string $tenant): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $baseUrl = strtolower(trim($tenant)) === 'cipemac'
            ? 'https://cipemac.infodocsisged.com.br'
            : 'https://gea.infodocsisged.com.br';

        $normalized = ltrim(str_replace('\\', '/', $value), '/');

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'upload/')) {
            return rtrim($baseUrl, '/') . '/' . $normalized;
        }

        return rtrim($baseUrl, '/') . '/upload/' . $normalized;
    }
}
