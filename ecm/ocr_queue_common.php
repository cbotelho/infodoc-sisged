<?php

require_once __DIR__ . '/object_storage_helper.php';
require_once __DIR__ . '/../includes/db.php';

function ocr_source_config($source)
{
    $map = [
        'ged' => [
            'table' => 'app_entity_43',
            'file_field' => 'field_445',
            'c1_field' => 'field_446',
            'c2_field' => 'field_447',
            'c3_field' => 'field_448',
            'pages_field' => 'field_554',
            'ocr_field' => 'field_475',
            'parent_table' => 'app_entity_41',
            'parent_pk' => 'id',
            'parent_fk' => 'parent_item_id',
            'parent_secretaria_field' => 'field_433',
            'parent_setor_field' => 'field_434',
            'parent_tipo_field' => 'field_436',
            'parent_numero_field' => 'field_437',
            'select_sql' => 'SELECT id, field_445 AS file_name, field_446 AS c1, field_447 AS c2, field_448 AS c3, field_554 AS pages FROM app_entity_43 WHERE field_445 IS NOT NULL AND TRIM(field_445) <> "" AND (field_475 IS NULL OR TRIM(field_475) = "") ORDER BY id DESC LIMIT :limit',
            'label' => 'GED',
        ],
        'sefaz_rh' => [
            'table' => 'app_entity_49',
            'file_field' => 'field_542',
            'c1_field' => 'field_543',
            'c2_field' => 'field_544',
            'c3_field' => 'field_545',
            'pages_field' => 'field_552',
            'ocr_field' => 'field_567',
            'parent_table' => 'app_entity_48',
            'parent_pk' => 'id',
            'parent_fk' => 'parent_item_id',
            'parent_secretaria_field' => 'field_524',
            'parent_setor_field' => 'field_525',
            'parent_tipo_field' => 'field_526',
            'parent_numero_field' => 'field_527',
            'select_sql' => 'SELECT id, field_542 AS file_name, field_543 AS c1, field_544 AS c2, field_545 AS c3, field_552 AS pages FROM app_entity_49 WHERE field_542 IS NOT NULL AND TRIM(field_542) <> "" AND (field_567 IS NULL OR TRIM(field_567) = "") ORDER BY id DESC LIMIT :limit',
            'label' => 'SEFAZ RH',
        ],
    ];

    if (!isset($map[$source])) {
        throw new InvalidArgumentException('Origem OCR invalida.');
    }

    return $map[$source];
}

function ocr_ensure_queue_table(PDO $pdo)
{
    $sql = "
        CREATE TABLE IF NOT EXISTS app_ocr_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(32) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            ocr_chars INT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            tenant_hint VARCHAR(64) NULL,
            request_host VARCHAR(255) NULL,
            created_at BIGINT UNSIGNED NOT NULL,
            updated_at BIGINT UNSIGNED NOT NULL,
            started_at BIGINT UNSIGNED NULL,
            finished_at BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_source_record (source, record_id),
            KEY idx_status_created (status, created_at),
            KEY idx_source_status (source, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $pdo->exec($sql);

    // Compatibilidade com instalacoes que ja tinham a tabela sem campos de lote.
    $columns = [];
    $colStmt = $pdo->query('SHOW COLUMNS FROM app_ocr_queue');
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[(string) $col['Field']] = true;
    }

    if (!isset($columns['batch_token'])) {
        $pdo->exec('ALTER TABLE app_ocr_queue ADD COLUMN batch_token VARCHAR(64) NULL AFTER request_host');
        $pdo->exec('ALTER TABLE app_ocr_queue ADD KEY idx_batch_status (batch_token, status)');
    }
}

function ocr_now_ts()
{
    return time();
}

function ocr_get_processing_timeout()
{
    return ocr_get_env_int('OCR_PROCESSING_STALE_SECONDS', 1800, 60, 86400);
}

function ocr_get_tenant_hint()
{
    $tenant = (string) ($_SERVER['HTTP_X_TENANT_DB'] ?? '');
    return trim(strtolower($tenant));
}

function ocr_get_host_hint()
{
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if (strpos($host, ',') !== false) {
        $parts = explode(',', $host);
        $host = trim($parts[0]);
    }
    return trim(strtolower($host));
}

function ocr_set_runtime_tenant_context($tenantHint, $hostHint)
{
    if (is_string($tenantHint) && $tenantHint !== '') {
        $_SERVER['HTTP_X_TENANT_DB'] = $tenantHint;
    }

    if (is_string($hostHint) && $hostHint !== '') {
        $_SERVER['HTTP_X_FORWARDED_HOST'] = $hostHint;
    }
}

function ocr_unique_string_values(array $values)
{
    $seen = [];
    $out = [];

    foreach ($values as $value) {
        $v = trim((string) $value);
        if ($v === '' || isset($seen[$v])) {
            continue;
        }
        $seen[$v] = true;
        $out[] = $v;
    }

    return $out;
}

function ocr_build_file_name_candidates($fileName)
{
    $raw = trim((string) $fileName);

    return ocr_unique_string_values([
        basename($raw),
        basename(rawurldecode($raw)),
        basename(urldecode($raw)),
        basename(str_replace('+', ' ', $raw)),
        basename(rawurldecode(str_replace('+', ' ', $raw))),
    ]);
}

function ocr_ascii_slug($value)
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false && $converted !== '') {
        $text = $converted;
    }

    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[^A-Za-z0-9._\- ]+/', '', $text);

    return trim((string) $text);
}

function ocr_build_contextual_file_name_candidates($fileName, array $context = [])
{
    $candidates = ocr_build_file_name_candidates($fileName);

    $raw = trim((string) $fileName);
    $ext = strtolower(pathinfo($raw !== '' ? $raw : 'arquivo.pdf', PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'pdf';
    }

    $c1 = trim((string) ($context['c1'] ?? ''));
    $c2 = trim((string) ($context['c2'] ?? ''));
    $c3 = trim((string) ($context['c3'] ?? ''));

    if ($c1 !== '' && $c2 !== '' && $c3 !== '') {
        $partsRaw = [$c1, $c2, $c3];
        $partsAscii = [ocr_ascii_slug($c1), ocr_ascii_slug($c2), ocr_ascii_slug($c3)];

        $candidates[] = implode('_', $partsRaw) . '.' . $ext;
        $candidates[] = implode('_', $partsAscii) . '.' . $ext;
    }

    if ($c2 !== '') {
        $candidates[] = ocr_ascii_slug($c2) . '.' . $ext;
    }

    return ocr_unique_string_values(array_map('basename', $candidates));
}

function ocr_normalize_object_key_candidate($value)
{
    $key = trim((string) $value);
    if ($key === '') {
        return '';
    }

    $key = str_replace('\\', '/', $key);
    $key = preg_replace('#/+#', '/', $key);
    $key = trim((string) $key, '/');

    return $key;
}

function ocr_build_prefix_candidates($source)
{
    $cfg = ged_get_r2_config();
    $defaultPrefix = trim((string) ($cfg['object_prefix'] ?? ''), '/');

    $candidates = [$defaultPrefix];

    if ($defaultPrefix === '') {
        $candidates[] = 'ged';
    }

    return ocr_unique_string_values($candidates);
}

function ocr_build_object_key_candidates($source, $fileName, array $folderCandidates, array $context = [])
{
    $raw = trim((string) $fileName);
    $fileNameCandidates = ocr_build_contextual_file_name_candidates($raw, $context);
    $prefixCandidates = ocr_build_prefix_candidates($source);
    $keys = [];

    // Caso o banco ja tenha salvo um caminho/chave completo, tenta isso primeiro.
    foreach ([
        $raw,
        rawurldecode($raw),
        urldecode($raw),
        str_replace('+', ' ', $raw),
        rawurldecode(str_replace('+', ' ', $raw)),
    ] as $rawCandidate) {
        $normalized = ocr_normalize_object_key_candidate($rawCandidate);
        if ($normalized !== '' && strpos($normalized, '/') !== false) {
            $keys[] = $normalized;
        }
    }

    foreach ($folderCandidates as $folderCandidate) {
        $folderCandidate = trim((string) $folderCandidate, '/');
        foreach ($fileNameCandidates as $candidate) {
            foreach ($prefixCandidates as $prefix) {
                $keys[] = ged_build_object_key_with_prefix($candidate, $folderCandidate, $prefix);
            }
        }
    }

    return ocr_unique_string_values(array_map('ocr_normalize_object_key_candidate', $keys));
}

function ocr_build_folder_candidates()
{
    return ['upload', 'assinador-python/uploads', 'uploads', ''];
}

function ocr_build_local_dir_candidates()
{
    $root = rtrim(ged_get_root_dir(), DIRECTORY_SEPARATOR);

    return [
        $root . DIRECTORY_SEPARATOR . 'upload',
        $root . DIRECTORY_SEPARATOR . 'assinador-python' . DIRECTORY_SEPARATOR . 'uploads',
        $root . DIRECTORY_SEPARATOR . 'uploads',
        $root . DIRECTORY_SEPARATOR . 'assinatura' . DIRECTORY_SEPARATOR . 'uploads',
    ];
}

function ocr_try_copy_from_local($fileName, $destinationPath)
{
    foreach (ocr_build_file_name_candidates($fileName) as $candidate) {
        foreach (ocr_build_local_dir_candidates() as $dir) {
            $source = $dir . DIRECTORY_SEPARATOR . basename($candidate);

            if (!is_file($source)) {
                continue;
            }

            if (@copy($source, $destinationPath)) {
                return [
                    'mode' => 'local',
                    'file_name' => basename($candidate),
                    'source_path' => $source,
                ];
            }
        }
    }

    return null;
}

function ocr_http_get_to_file($url, $destinationPath)
{
    $safeUrl = trim((string) $url);
    if ($safeUrl === '') {
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'ignore_errors' => true,
            'header' => "User-Agent: infodoc-ocr-worker/1.0\r\n",
        ],
    ]);

    $bytes = @file_get_contents($safeUrl, false, $context);
    if ($bytes === false || trim((string) $bytes) === '') {
        return false;
    }

    if (@file_put_contents($destinationPath, $bytes) === false) {
        return false;
    }

    return is_file($destinationPath) && filesize($destinationPath) > 0;
}

function ocr_build_proxy_base_urls($tenantHint, $hostHint)
{
    $baseUrls = [];

    $appBase = trim((string) getenv('APP_BASE_URL'));
    if ($appBase !== '') {
        $baseUrls[] = rtrim($appBase, '/');
    }

    $host = trim((string) $hostHint);
    if ($host !== '') {
        $baseUrls[] = 'https://' . $host;
        $baseUrls[] = 'http://' . $host;
    }

    return ocr_unique_string_values($baseUrls);
}

function ocr_try_download_via_upload_proxy($fileName, $destinationPath, $tenantHint = '', $hostHint = '', array $context = [])
{
    $baseUrls = ocr_build_proxy_base_urls($tenantHint, $hostHint);
    if (!$baseUrls) {
        return null;
    }

    $errors = [];
    foreach ($baseUrls as $baseUrl) {
        foreach (ocr_build_contextual_file_name_candidates($fileName, $context) as $candidate) {
            $url = $baseUrl . '/upload/' . rawurlencode(basename($candidate));
            @unlink($destinationPath);

            if (ocr_http_get_to_file($url, $destinationPath)) {
                return [
                    'url' => $url,
                    'file_name' => basename($candidate),
                ];
            }

            $errors[] = $url;
        }
    }

    return [
        'failed_urls' => $errors,
    ];
}

function ocr_download_file_with_fallbacks($fileName, $destinationPath, $folder = 'upload', $source = 'ged', $tenantHint = '', $hostHint = '', array $context = [])
{
    $errors = [];

    $folderCandidates = ocr_unique_string_values(array_merge([$folder], ocr_build_folder_candidates()));
    $objectKeyCandidates = ocr_build_object_key_candidates($source, $fileName, $folderCandidates, $context);

    foreach ($objectKeyCandidates as $objectKey) {
        @unlink($destinationPath);

        try {
            ged_download_file_to_path_by_object_key($objectKey, $destinationPath);

            if (is_file($destinationPath) && filesize($destinationPath) > 0) {
                return basename($objectKey);
            }
        } catch (Throwable $e) {
            $errors[] = 'key=' . $objectKey . ' msg=' . $e->getMessage();
        }
    }

    @unlink($destinationPath);
    $proxyResult = ocr_try_download_via_upload_proxy($fileName, $destinationPath, $tenantHint, $hostHint, $context);
    if (is_array($proxyResult) && isset($proxyResult['file_name']) && is_file($destinationPath) && filesize($destinationPath) > 0) {
        return (string) $proxyResult['file_name'];
    }

    if (is_array($proxyResult) && !empty($proxyResult['failed_urls'])) {
        $errors[] = 'proxy_urls=' . implode(',', array_slice((array) $proxyResult['failed_urls'], 0, 6));
    }

    $local = ocr_try_copy_from_local($fileName, $destinationPath);
    if ($local !== null && is_file($destinationPath) && filesize($destinationPath) > 0) {
        return $local['file_name'];
    }

    throw new RuntimeException('Falha ao baixar arquivo do R2/local. Tentativas: ' . implode(' || ', $errors));
}

function ocr_placeholder_when_empty()
{
    return '[OCR_SEM_TEXTO]';
}

function ocr_command_exists($command)
{
    $name = trim((string) $command);
    if ($name === '') {
        return false;
    }

    $result = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
    return trim((string) $result) !== '';
}

function ocr_get_env_int($name, $default, $min = null, $max = null)
{
    $raw = getenv((string) $name);
    if ($raw === false || trim((string) $raw) === '') {
        $value = (int) $default;
    } else {
        $value = (int) $raw;
    }

    if ($min !== null) {
        $value = max((int) $min, $value);
    }
    if ($max !== null) {
        $value = min((int) $max, $value);
    }

    return $value;
}

function ocr_get_env_bool($name, $default = false)
{
    $raw = getenv((string) $name);
    if ($raw === false || trim((string) $raw) === '') {
        return (bool) $default;
    }

    return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
}

function ocr_pdf_page_count($filePath)
{
    if (!is_file($filePath) || !ocr_command_exists('pdfinfo')) {
        return 0;
    }

    $output = (string) @shell_exec('pdfinfo ' . escapeshellarg($filePath) . ' 2>&1');
    if ($output === '') {
        return 0;
    }

    if (preg_match('/^Pages:\s*(\d+)/mi', $output, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function ocr_extract_pdf_text_with_parser($filePath)
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return '';
    }

    require_once $autoload;
    if (!class_exists('Smalot\\PdfParser\\Parser')) {
        return '';
    }

    try {
        $parser = new Smalot\PdfParser\Parser();
        $document = $parser->parseFile($filePath);
        $text = trim((string) $document->getText());
        return $text;
    } catch (Throwable $e) {
        error_log('OCR queue: falha no parser PDF: ' . $e->getMessage());
        return '';
    }
}

function ocr_normalize_text($value)
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim((string) $text);
}

function ocr_extract_text_legacy($filePath, $originalName)
{
    $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
    $ocrText = '';

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'gif'], true)) {
        $outputTxt = tempnam(sys_get_temp_dir(), 'ocr_');
        @shell_exec('tesseract ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputTxt) . ' -l por 2>&1');
        $ocrText = (string) @file_get_contents($outputTxt . '.txt');
        @unlink($outputTxt . '.txt');
        return ocr_normalize_text($ocrText);
    }

    if ($ext === 'pdf') {
        $pdfPages = ocr_pdf_page_count($filePath);
        $parserMaxPages = ocr_get_env_int('OCR_PDF_PARSER_MAX_PAGES', 400, 0, 5000);
        $maxPdfPages = ocr_get_env_int('OCR_MAX_PDF_PAGES', 0, 0, 5000);

        $canUseParser = ($parserMaxPages <= 0 || $pdfPages <= 0 || $pdfPages <= $parserMaxPages);
        if ($canUseParser) {
            $ocrText = ocr_extract_pdf_text_with_parser($filePath);
            if ($ocrText !== '') {
                return ocr_normalize_text($ocrText);
            }
        }

        $outputTxt = tempnam(sys_get_temp_dir(), 'pdftxt_');
        if (ocr_command_exists('pdftotext')) {
            if ($maxPdfPages > 0 && $pdfPages > $maxPdfPages) {
                @shell_exec('pdftotext -f 1 -l ' . (int) $maxPdfPages . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputTxt) . ' 2>&1');
            } else {
                @shell_exec('pdftotext ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputTxt) . ' 2>&1');
            }
        }
        $ocrText = (string) @file_get_contents($outputTxt);
        @unlink($outputTxt);

        if (trim($ocrText) !== '') {
            if ($maxPdfPages > 0 && $pdfPages > $maxPdfPages) {
                $ocrText .= "\n\n[OCR_TRUNCADO_PAGINAS total=" . $pdfPages . " processadas=" . $maxPdfPages . "]";
            }
            return ocr_normalize_text($ocrText);
        }

        $tmpImg = tempnam(sys_get_temp_dir(), 'pdfimg_') . '.png';
        if (ocr_command_exists('convert')) {
            @shell_exec('convert -density 300 ' . escapeshellarg($filePath . '[0]') . ' ' . escapeshellarg($tmpImg) . ' 2>&1');
        }

        if (is_file($tmpImg)) {
            $outputTxt2 = tempnam(sys_get_temp_dir(), 'ocrpdf_');
            if (ocr_command_exists('tesseract')) {
                @shell_exec('tesseract ' . escapeshellarg($tmpImg) . ' ' . escapeshellarg($outputTxt2) . ' -l por 2>&1');
            }
            $ocrText = (string) @file_get_contents($outputTxt2 . '.txt');
            @unlink($outputTxt2 . '.txt');
            @unlink($tmpImg);
        }

        return ocr_normalize_text($ocrText);
    }

    return '';
}

function ocr_extract_pdf_page_text($filePath, $pageNumber)
{
    $page = (int) $pageNumber;
    if ($page <= 0 || !ocr_command_exists('pdftotext')) {
        return '';
    }

    $outputTxt = tempnam(sys_get_temp_dir(), 'ocr_pg_');
    @shell_exec('pdftotext -f ' . $page . ' -l ' . $page . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputTxt) . ' 2>&1');
    $text = (string) @file_get_contents($outputTxt);
    @unlink($outputTxt);

    return ocr_normalize_text($text);
}

function ocr_extract_pdf_text_range($filePath, $fromPage, $toPage)
{
    $from = max(1, (int) $fromPage);
    $to = max($from, (int) $toPage);

    if (!ocr_command_exists('pdftotext')) {
        return '';
    }

    $outputTxt = tempnam(sys_get_temp_dir(), 'ocr_rng_');
    @shell_exec('pdftotext -f ' . $from . ' -l ' . $to . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputTxt) . ' 2>&1');
    $text = (string) @file_get_contents($outputTxt);
    @unlink($outputTxt);

    return ocr_normalize_text($text);
}

function ocr_extract_fast_large_pdf_snapshot($filePath, $totalPages)
{
    $pagesTotal = max(0, (int) $totalPages);
    if ($pagesTotal <= 0) {
        return [
            'text' => '',
            'pages' => [],
        ];
    }

    $headPages = ocr_get_env_int('OCR_FAST_SCAN_HEAD_PAGES', 12, 1, 200);
    $tailPages = ocr_get_env_int('OCR_FAST_SCAN_TAIL_PAGES', 2, 0, 80);

    $parts = [];
    $pages = [];

    $headTo = min($pagesTotal, $headPages);
    $headText = ocr_extract_pdf_text_range($filePath, 1, $headTo);
    if ($headText !== '') {
        $parts[] = '[FAIXA 1-' . $headTo . "]\n" . $headText;
        for ($p = 1; $p <= $headTo; $p++) {
            $pages[] = $p;
        }
    }

    if ($tailPages > 0 && $pagesTotal > $headTo) {
        $tailFrom = max($headTo + 1, $pagesTotal - $tailPages + 1);
        $tailText = ocr_extract_pdf_text_range($filePath, $tailFrom, $pagesTotal);
        if ($tailText !== '') {
            $parts[] = '[FAIXA ' . $tailFrom . '-' . $pagesTotal . "]\n" . $tailText;
            for ($p = $tailFrom; $p <= $pagesTotal; $p++) {
                $pages[] = $p;
            }
        }
    }

    $pages = array_values(array_unique(array_map('intval', $pages)));
    sort($pages, SORT_NUMERIC);

    return [
        'text' => ocr_normalize_text(implode("\n\n", $parts)),
        'pages' => $pages,
    ];
}

function ocr_build_pdf_scan_pages($totalPages)
{
    $total = max(0, (int) $totalPages);
    if ($total <= 0) {
        return [];
    }

    $head = ocr_get_env_int('OCR_PAGE_SCAN_HEAD', 8, 1, 200);
    $tail = ocr_get_env_int('OCR_PAGE_SCAN_TAIL', 4, 0, 200);
    $stride = ocr_get_env_int('OCR_PAGE_SCAN_STRIDE', 50, 5, 1000);

    $pages = [1, $total];

    for ($p = 1; $p <= min($head, $total); $p++) {
        $pages[] = $p;
    }

    if ($tail > 0) {
        $start = max(1, $total - $tail + 1);
        for ($p = $start; $p <= $total; $p++) {
            $pages[] = $p;
        }
    }

    for ($p = 1; $p <= $total; $p += $stride) {
        $pages[] = $p;
    }

    $pages = array_values(array_unique(array_map('intval', $pages)));
    sort($pages, SORT_NUMERIC);

    return $pages;
}

function ocr_relevance_score($text)
{
    $t = mb_strtolower((string) $text, 'UTF-8');
    if ($t === '') {
        return 0;
    }

    $keywords = [
        'assunto' => 7,
        'processo' => 8,
        'protocolo' => 8,
        'oficio' => 8,
        'memorando' => 8,
        'decreto' => 7,
        'portaria' => 7,
        'setor' => 6,
        'secretaria' => 6,
        'departamento' => 6,
        'gerencia' => 5,
        'coordenacao' => 5,
        'matricula' => 7,
        'cpf' => 7,
        'cnpj' => 7,
        'servidor' => 5,
        'interessado' => 6,
        'destinatario' => 6,
    ];

    $score = 0;
    foreach ($keywords as $term => $weight) {
        $score += substr_count($t, $term) * $weight;
    }

    if (preg_match('/\b\d{2}\/\d{2}\/\d{4}\b/u', $t)) {
        $score += 4;
    }
    if (preg_match('/\b\d{3}\.\d{3}\.\d{3}\-\d{2}\b/u', $t)) {
        $score += 6;
    }
    if (preg_match('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}\b/u', $t)) {
        $score += 6;
    }

    return $score;
}

function ocr_select_relevant_pdf_pages($filePath, $totalPages)
{
    $maxRelevant = ocr_get_env_int('OCR_RELEVANT_PAGE_LIMIT', 24, 4, 300);
    $scanPages = ocr_build_pdf_scan_pages($totalPages);

    $scored = [];
    foreach ($scanPages as $page) {
        $pageText = ocr_extract_pdf_page_text($filePath, $page);
        if ($pageText === '') {
            continue;
        }

        $scored[] = [
            'page' => (int) $page,
            'score' => ocr_relevance_score($pageText),
            'text' => $pageText,
        ];
    }

    if (!$scored) {
        return [
            'pages' => [],
            'text' => '',
        ];
    }

    usort($scored, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return $a['page'] <=> $b['page'];
        }
        return $b['score'] <=> $a['score'];
    });

    $picked = array_slice($scored, 0, $maxRelevant);
    $pickedMap = [];
    foreach ($picked as $item) {
        $pickedMap[(int) $item['page']] = $item['text'];
    }

    if (!isset($pickedMap[1]) && $totalPages > 0) {
        $firstText = ocr_extract_pdf_page_text($filePath, 1);
        if ($firstText !== '') {
            $pickedMap[1] = $firstText;
        }
    }

    ksort($pickedMap, SORT_NUMERIC);
    $chunks = [];
    foreach ($pickedMap as $page => $text) {
        $chunks[] = '[PAGINA ' . $page . "]\n" . $text;
    }

    return [
        'pages' => array_map('intval', array_keys($pickedMap)),
        'text' => ocr_normalize_text(implode("\n\n", $chunks)),
    ];
}

function ocr_limit_list(array $values, $maxItems = 12)
{
    $out = [];
    $seen = [];

    foreach ($values as $value) {
        $v = trim((string) $value);
        if ($v === '') {
            continue;
        }

        $key = mb_strtolower($v, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $out[] = $v;
        if (count($out) >= (int) $maxItems) {
            break;
        }
    }

    return $out;
}

function ocr_extract_entities($text)
{
    $sourceText = (string) $text;
    $entities = [
        'document_numbers' => [],
        'subjects' => [],
        'names' => [],
        'sectors' => [],
        'dates' => [],
        'identifiers' => [],
    ];

    if (preg_match_all('/\b(?:processo|protocolo|oficio|memorando|documento|matricula)\s*(?:n[o0]?\.?\s*)?[:\-]?\s*([A-Z0-9\.\-\/]{3,40})/iu', $sourceText, $m)) {
        $entities['document_numbers'] = ocr_limit_list($m[1], 10);
    }

    if (preg_match_all('/^\s*assunto\s*[:\-]\s*(.+)$/imu', $sourceText, $m)) {
        $entities['subjects'] = ocr_limit_list($m[1], 6);
    }

    if (preg_match_all('/\b(?:setor|secretaria|departamento|coordenacao|gerencia|divisao)\s*(?:de|do|da)?\s*[:\-]?\s*([A-Za-zÀ-ÿ0-9\-\s\/]{3,80})/iu', $sourceText, $m)) {
        $entities['sectors'] = ocr_limit_list($m[1], 12);
    }

    if (preg_match_all('/\b\d{2}\/\d{2}\/\d{4}\b/u', $sourceText, $m)) {
        $entities['dates'] = ocr_limit_list($m[0], 12);
    }

    if (preg_match_all('/\b\d{3}\.\d{3}\.\d{3}\-\d{2}\b/u', $sourceText, $m)) {
        $entities['identifiers'] = array_merge($entities['identifiers'], $m[0]);
    }
    if (preg_match_all('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}\b/u', $sourceText, $m)) {
        $entities['identifiers'] = array_merge($entities['identifiers'], $m[0]);
    }
    if (preg_match_all('/\b\d{6,20}\b/u', $sourceText, $m)) {
        $entities['identifiers'] = array_merge($entities['identifiers'], $m[0]);
    }
    $entities['identifiers'] = ocr_limit_list($entities['identifiers'], 15);

    if (preg_match_all('/\b([A-ZÀ-Ý]{2,}(?:\s+[A-ZÀ-Ý]{2,}){1,5})\b/u', mb_strtoupper($sourceText, 'UTF-8'), $m)) {
        $rawNames = [];
        foreach ($m[1] as $candidate) {
            if (strpos($candidate, 'SETOR ') === 0 || strpos($candidate, 'SECRETARIA ') === 0 || strpos($candidate, 'DEPARTAMENTO ') === 0) {
                continue;
            }
            $rawNames[] = trim((string) $candidate);
        }
        $entities['names'] = ocr_limit_list($rawNames, 15);
    }

    return $entities;
}

function ocr_classify_document($source, $text, array $entities, array $context)
{
    $t = mb_strtolower((string) $text, 'UTF-8');
    $scores = [
        'folha_de_ponto' => 0,
        'processo_administrativo' => 0,
        'oficio_memorando' => 0,
        'contrato_convenio' => 0,
        'relatorio' => 0,
        'pasta_funcional' => 0,
        'outro' => 1,
    ];

    $rules = [
        'folha_de_ponto' => ['folha de ponto', 'frequencia', 'escala', 'horas'],
        'processo_administrativo' => ['processo', 'protocolo', 'despacho', 'parecer'],
        'oficio_memorando' => ['oficio', 'memorando', 'circular'],
        'contrato_convenio' => ['contrato', 'convenio', 'aditivo'],
        'relatorio' => ['relatorio', 'auditoria', 'inspecao'],
        'pasta_funcional' => ['matricula', 'servidor', 'funcional'],
    ];

    foreach ($rules as $label => $terms) {
        foreach ($terms as $term) {
            $scores[$label] += substr_count($t, $term) * 3;
        }
    }

    if ($source === 'sefaz_rh') {
        $scores['pasta_funcional'] += 2;
    }

    $contextSetor = mb_strtolower((string) ($context['setor'] ?? ''), 'UTF-8');
    if ($contextSetor !== '' && (strpos($contextSetor, 'rh') !== false || strpos($contextSetor, 'recursos humanos') !== false)) {
        $scores['pasta_funcional'] += 3;
    }

    if (!empty($entities['document_numbers'])) {
        $scores['processo_administrativo'] += 2;
    }

    arsort($scores, SORT_NUMERIC);
    $type = (string) array_key_first($scores);
    $topScore = (int) current($scores);
    $confidence = min(99, max(35, 35 + ($topScore * 4)));

    return [
        'type' => $type,
        'confidence' => $confidence,
    ];
}

function ocr_build_summary_payload($source, $fileName, $baseText, array $entities, array $context, array $meta)
{
    $classification = ocr_classify_document($source, $baseText, $entities, $context);
    $subject = '';
    if (!empty($entities['subjects'])) {
        $subject = (string) $entities['subjects'][0];
    }

    $documentNumber = '';
    if (!empty($entities['document_numbers'])) {
        $documentNumber = (string) $entities['document_numbers'][0];
    }

    $setores = array_merge((array) ($entities['sectors'] ?? []), [
        (string) ($context['setor'] ?? ''),
        (string) ($context['secretaria'] ?? ''),
    ]);
    $setores = ocr_limit_list($setores, 12);

    $payload = [
        'version' => 2,
        'source' => (string) $source,
        'file_name' => (string) $fileName,
        'document_type' => (string) $classification['type'],
        'confidence' => (int) $classification['confidence'],
        'subject' => $subject,
        'document_number' => $documentNumber,
        'names' => ocr_limit_list((array) ($entities['names'] ?? []), 15),
        'sectors_departments' => $setores,
        'dates' => ocr_limit_list((array) ($entities['dates'] ?? []), 12),
        'identifiers' => ocr_limit_list((array) ($entities['identifiers'] ?? []), 15),
        'pages_total' => (int) ($meta['pages_total'] ?? 0),
        'pages_processed' => (int) ($meta['pages_processed'] ?? 0),
        'pages_relevant' => (array) ($meta['pages_relevant'] ?? []),
        'context' => [
            'secretaria' => (string) ($context['secretaria'] ?? ''),
            'setor' => (string) ($context['setor'] ?? ''),
            'tipo' => (string) ($context['tipo'] ?? ''),
            'numero' => (string) ($context['numero'] ?? ''),
        ],
    ];

    $lines = [];
    $lines[] = 'tipo_documento: ' . $payload['document_type'];
    $lines[] = 'confianca: ' . $payload['confidence'];
    $lines[] = 'assunto: ' . ($payload['subject'] !== '' ? $payload['subject'] : '[nao identificado]');
    $lines[] = 'numero_documento: ' . ($payload['document_number'] !== '' ? $payload['document_number'] : '[nao identificado]');
    $lines[] = 'nomes: ' . (!empty($payload['names']) ? implode('; ', $payload['names']) : '[nao identificado]');
    $lines[] = 'setores_departamentos: ' . (!empty($payload['sectors_departments']) ? implode('; ', $payload['sectors_departments']) : '[nao identificado]');
    $lines[] = 'datas: ' . (!empty($payload['dates']) ? implode('; ', $payload['dates']) : '[nao identificado]');
    $lines[] = 'identificadores: ' . (!empty($payload['identifiers']) ? implode('; ', $payload['identifiers']) : '[nao identificado]');
    $lines[] = 'paginas: total=' . $payload['pages_total'] . ' processadas=' . $payload['pages_processed'];

    $summaryText = implode("\n", $lines);
    $summaryJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'summary_text' => $summaryText,
        'summary_json' => $summaryJson !== false ? $summaryJson : '{}',
        'payload' => $payload,
    ];
}

function ocr_extract_base_text_phase2($filePath, $originalName)
{
    $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return [
            'text' => ocr_extract_text_legacy($filePath, $originalName),
            'meta' => [
                'pages_total' => 0,
                'pages_processed' => 0,
                'pages_relevant' => [],
                'mode' => 'legacy_non_pdf',
            ],
        ];
    }

    $pagesTotal = ocr_pdf_page_count($filePath);
    $largePdfThreshold = ocr_get_env_int('OCR_LARGE_PDF_THRESHOLD', 120, 20, 5000);
    $enableRelevanceScoring = ocr_get_env_bool('OCR_ENABLE_RELEVANCE_SCORING', false);
    $relevanceMaxPages = ocr_get_env_int('OCR_RELEVANCE_MAX_PAGES', 300, 20, 5000);

    if ($pagesTotal > 0 && $pagesTotal >= $largePdfThreshold && ocr_command_exists('pdftotext')) {
        if (!$enableRelevanceScoring || $pagesTotal > $relevanceMaxPages) {
            $snapshot = ocr_extract_fast_large_pdf_snapshot($filePath, $pagesTotal);
            $snapshotText = (string) ($snapshot['text'] ?? '');

            if ($snapshotText !== '') {
                return [
                    'text' => $snapshotText,
                    'meta' => [
                        'pages_total' => $pagesTotal,
                        'pages_processed' => count((array) ($snapshot['pages'] ?? [])),
                        'pages_relevant' => (array) ($snapshot['pages'] ?? []),
                        'mode' => 'phase2_fast_snapshot',
                    ],
                ];
            }
        }

        $selected = ocr_select_relevant_pdf_pages($filePath, $pagesTotal);
        $selectedText = (string) ($selected['text'] ?? '');
        if ($selectedText !== '') {
            return [
                'text' => $selectedText,
                'meta' => [
                    'pages_total' => $pagesTotal,
                    'pages_processed' => count((array) ($selected['pages'] ?? [])),
                    'pages_relevant' => (array) ($selected['pages'] ?? []),
                    'mode' => 'phase2_selective',
                ],
            ];
        }
    }

    $legacy = ocr_extract_text_legacy($filePath, $originalName);
    return [
        'text' => $legacy,
        'meta' => [
            'pages_total' => $pagesTotal,
            'pages_processed' => $pagesTotal > 0 ? $pagesTotal : 0,
            'pages_relevant' => $pagesTotal > 0 ? [1] : [],
            'mode' => 'legacy_pdf',
        ],
    ];
}

function ocr_compose_storage_text($summaryText, $summaryJson, $baseText, array $meta)
{
    $maxChars = ocr_get_env_int('OCR_OUTPUT_MAX_CHARS', 30000, 2000, 200000);
    $normalizedBase = ocr_normalize_text($baseText);
    $snippetChars = ocr_get_env_int('OCR_SNIPPET_MAX_CHARS', 20000, 1000, 120000);
    if (mb_strlen($normalizedBase, 'UTF-8') > $snippetChars) {
        $normalizedBase = mb_substr($normalizedBase, 0, $snippetChars, 'UTF-8')
            . "\n\n[OCR_TEXTO_TRUNCADO chars=" . mb_strlen($baseText, 'UTF-8') . ' limite=' . $snippetChars . ']';
    }

    $composed = "[OCR_INTELIGENTE_V2]\n"
        . $summaryText
        . "\n\n[OCR_JSON]\n"
        . $summaryJson
        . "\n\n[OCR_TEXTO_RELEVANTE]\n"
        . $normalizedBase;

    if (mb_strlen($composed, 'UTF-8') > $maxChars) {
        $composed = mb_substr($composed, 0, $maxChars, 'UTF-8')
            . "\n\n[OCR_SAIDA_TRUNCADA limite=" . $maxChars . ' mode=' . (string) ($meta['mode'] ?? '') . ']';
    }

    return ocr_normalize_text($composed);
}

function ocr_extract_intelligent_payload($filePath, $originalName, $source, array $context)
{
    $phase2 = ocr_extract_base_text_phase2($filePath, $originalName);
    $baseText = ocr_normalize_text((string) ($phase2['text'] ?? ''));
    $meta = (array) ($phase2['meta'] ?? []);

    if ($baseText === '') {
        return [
            'storage_text' => '',
            'base_text' => '',
            'summary_text' => '',
            'meta' => $meta,
        ];
    }

    $entities = ocr_extract_entities($baseText);
    $summary = ocr_build_summary_payload($source, $originalName, $baseText, $entities, $context, $meta);
    $storageText = ocr_compose_storage_text(
        (string) $summary['summary_text'],
        (string) $summary['summary_json'],
        $baseText,
        $meta
    );

    return [
        'storage_text' => $storageText,
        'base_text' => $baseText,
        'summary_text' => (string) $summary['summary_text'],
        'meta' => $meta,
    ];
}

function ocr_fetch_record_context(PDO $pdo, array $cfg, $recordId)
{
    $context = [
        'c1' => '',
        'c2' => '',
        'c3' => '',
        'secretaria' => '',
        'setor' => '',
        'tipo' => '',
        'numero' => '',
    ];

    $sql = 'SELECT '
        . $cfg['parent_fk'] . ' AS parent_id, '
        . $cfg['c1_field'] . ' AS c1, '
        . $cfg['c2_field'] . ' AS c2, '
        . $cfg['c3_field'] . ' AS c3 '
        . 'FROM ' . $cfg['table'] . ' WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int) $recordId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        return $context;
    }

    $context['c1'] = trim((string) ($doc['c1'] ?? ''));
    $context['c2'] = trim((string) ($doc['c2'] ?? ''));
    $context['c3'] = trim((string) ($doc['c3'] ?? ''));

    $parentId = (int) ($doc['parent_id'] ?? 0);
    if ($parentId <= 0) {
        return $context;
    }

    $parentSql = 'SELECT '
        . $cfg['parent_secretaria_field'] . ' AS secretaria, '
        . $cfg['parent_setor_field'] . ' AS setor, '
        . $cfg['parent_tipo_field'] . ' AS tipo, '
        . $cfg['parent_numero_field'] . ' AS numero '
        . 'FROM ' . $cfg['parent_table'] . ' WHERE ' . $cfg['parent_pk'] . ' = ? LIMIT 1';
    $parentStmt = $pdo->prepare($parentSql);
    $parentStmt->execute([$parentId]);
    $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent) {
        return $context;
    }

    $context['secretaria'] = trim((string) ($parent['secretaria'] ?? ''));
    $context['setor'] = trim((string) ($parent['setor'] ?? ''));
    $context['tipo'] = trim((string) ($parent['tipo'] ?? ''));
    $context['numero'] = trim((string) ($parent['numero'] ?? ''));

    return $context;
}

function ocr_fetch_pending(PDO $pdo, $source, $limit)
{
    $cfg = ocr_source_config($source);
    $safeLimit = max(1, min(1000, (int) $limit));

    $stmt = $pdo->prepare($cfg['select_sql']);
    $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ocr_queue_stats(PDO $pdo)
{
    ocr_ensure_queue_table($pdo);
    ocr_requeue_stale_processing_jobs($pdo);

    $stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM app_ocr_queue GROUP BY status');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'queued' => 0,
        'processing' => 0,
        'done' => 0,
        'failed' => 0,
    ];

    foreach ($rows as $row) {
        $status = (string) $row['status'];
        if (isset($stats[$status])) {
            $stats[$status] = (int) $row['total'];
        }
    }

    return $stats;
}

function ocr_enqueue_records(PDO $pdo, $source, array $recordIds, $createdBy = null, $force = false, $batchToken = null)
{
    ocr_ensure_queue_table($pdo);
    $cfg = ocr_source_config($source);

    $createdAt = ocr_now_ts();
    $hostHint = ocr_get_host_hint();
    $tenantHint = ocr_get_tenant_hint();
    $batchToken = trim((string) $batchToken);
    if ($batchToken === '') {
        $batchToken = null;
    }

    $selectRecordSql = 'SELECT id, ' . $cfg['file_field'] . ' AS file_name, ' . $cfg['ocr_field'] . ' AS ocr_value FROM ' . $cfg['table'] . ' WHERE id = ? LIMIT 1';
    $selectRecordStmt = $pdo->prepare($selectRecordSql);

    $selectQueueStmt = $pdo->prepare('SELECT id, status FROM app_ocr_queue WHERE source = ? AND record_id = ? LIMIT 1');
    $insertQueueStmt = $pdo->prepare('INSERT INTO app_ocr_queue (source, record_id, file_name, status, attempts, error_message, ocr_chars, created_by, tenant_hint, request_host, batch_token, created_at, updated_at) VALUES (?, ?, ?, "queued", 0, NULL, NULL, ?, ?, ?, ?, ?, ?)');
    $updateQueueStmt = $pdo->prepare('UPDATE app_ocr_queue SET file_name = ?, status = "queued", error_message = NULL, updated_at = ?, tenant_hint = ?, request_host = ?, created_by = ?, batch_token = ? WHERE id = ?');

    $result = [
        'enqueued' => 0,
        'skipped' => 0,
        'batch_token' => $batchToken,
        'errors' => [],
    ];

    foreach ($recordIds as $rid) {
        $recordId = (int) $rid;
        if ($recordId <= 0) {
            $result['skipped']++;
            continue;
        }

        $selectRecordStmt->execute([$recordId]);
        $row = $selectRecordStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $result['errors'][] = 'Registro nao encontrado: ' . $recordId;
            continue;
        }

        $fileName = trim((string) ($row['file_name'] ?? ''));
        if ($fileName === '') {
            $result['errors'][] = 'Registro sem arquivo: ' . $recordId;
            continue;
        }

        $ocrValue = trim((string) ($row['ocr_value'] ?? ''));
        if (!$force && $ocrValue !== '') {
            $result['skipped']++;
            continue;
        }

        $selectQueueStmt->execute([$source, $recordId]);
        $queueRow = $selectQueueStmt->fetch(PDO::FETCH_ASSOC);

        if (!$queueRow) {
            $insertQueueStmt->execute([
                $source,
                $recordId,
                $fileName,
                $createdBy,
                $tenantHint !== '' ? $tenantHint : null,
                $hostHint !== '' ? $hostHint : null,
                $batchToken,
                $createdAt,
                $createdAt,
            ]);
            $result['enqueued']++;
            continue;
        }

        $status = (string) $queueRow['status'];
        if (!$force && ($status === 'queued' || $status === 'processing')) {
            $result['skipped']++;
            continue;
        }

        $updateQueueStmt->execute([
            $fileName,
            $createdAt,
            $tenantHint !== '' ? $tenantHint : null,
            $hostHint !== '' ? $hostHint : null,
            $createdBy,
            $batchToken,
            (int) $queueRow['id'],
        ]);
        $result['enqueued']++;
    }

    return $result;
}

function ocr_claim_jobs(PDO $pdo, $batchSize, $batchToken = null)
{
    ocr_ensure_queue_table($pdo);
    ocr_requeue_stale_processing_jobs($pdo, $batchToken);
    $limit = max(1, min(500, (int) $batchSize));
    $batchToken = trim((string) $batchToken);

    if ($batchToken !== '') {
        $sel = $pdo->prepare('SELECT id FROM app_ocr_queue WHERE status = "queued" AND batch_token = :batch_token ORDER BY id ASC LIMIT :limit');
        $sel->bindValue(':batch_token', $batchToken, PDO::PARAM_STR);
    } else {
        $sel = $pdo->prepare('SELECT id FROM app_ocr_queue WHERE status = "queued" ORDER BY id ASC LIMIT :limit');
    }
    $sel->bindValue(':limit', $limit, PDO::PARAM_INT);
    $sel->execute();
    $ids = $sel->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) {
        return [];
    }

    $update = $pdo->prepare('UPDATE app_ocr_queue SET status = "processing", started_at = ?, updated_at = ?, attempts = attempts + 1 WHERE id = ? AND status = "queued"');
    $fetch = $pdo->prepare('SELECT * FROM app_ocr_queue WHERE id = ? LIMIT 1');
    $now = ocr_now_ts();
    $jobs = [];

    foreach ($ids as $id) {
        $id = (int) $id;
        $update->execute([$now, $now, $id]);
        if ($update->rowCount() === 0) {
            continue;
        }

        $fetch->execute([$id]);
        $job = $fetch->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            $jobs[] = $job;
        }
    }

    return $jobs;
}

function ocr_process_batch(PDO $pdo, $batchSize, $batchToken = null)
{
    $jobs = ocr_claim_jobs($pdo, $batchSize, $batchToken);
    $summary = [
        'claimed' => count($jobs),
        'done' => 0,
        'failed' => 0,
        'batch_token' => $batchToken,
        'errors' => [],
    ];

    if (!$jobs) {
        return $summary;
    }

    $updateDone = $pdo->prepare('UPDATE app_ocr_queue SET status = "done", error_message = NULL, ocr_chars = ?, finished_at = ?, updated_at = ? WHERE id = ?');
    $updateFail = $pdo->prepare('UPDATE app_ocr_queue SET status = "failed", error_message = ?, finished_at = ?, updated_at = ? WHERE id = ?');

    foreach ($jobs as $job) {
        $jobId = (int) $job['id'];
        $source = (string) $job['source'];
        $recordId = (int) $job['record_id'];
        $fileName = (string) $job['file_name'];
        $tenantHint = (string) ($job['tenant_hint'] ?? '');
        $hostHint = (string) ($job['request_host'] ?? '');
        $now = ocr_now_ts();

        try {
            $cfg = ocr_source_config($source);
            ocr_set_runtime_tenant_context($tenantHint, $hostHint);
            $recordContext = ocr_fetch_record_context($pdo, $cfg, $recordId);

            $tmpFile = tempnam(sys_get_temp_dir(), 'ocr_dl_');
            if ($tmpFile === false) {
                throw new RuntimeException('Falha ao criar arquivo temporario para OCR.');
            }

            try {
                $resolvedName = ocr_download_file_with_fallbacks(
                    $fileName,
                    $tmpFile,
                    'upload',
                    $source,
                    $tenantHint,
                    $hostHint,
                    $recordContext
                );
                $intelligent = ocr_extract_intelligent_payload($tmpFile, $resolvedName, $source, $recordContext);
                $ocrText = trim((string) ($intelligent['storage_text'] ?? ''));
                $baseText = trim((string) ($intelligent['base_text'] ?? ''));
            } finally {
                @unlink($tmpFile);
            }

            $ocrChars = mb_strlen($baseText !== '' ? $baseText : $ocrText, 'UTF-8');
            if ($ocrText === '') {
                $ocrText = ocr_placeholder_when_empty();
                $ocrChars = 0;
            }

            $updateDocSql = 'UPDATE ' . $cfg['table'] . ' SET ' . $cfg['ocr_field'] . ' = ? WHERE id = ?';
            $updateDocStmt = $pdo->prepare($updateDocSql);
            $updateDocStmt->execute([$ocrText, $recordId]);

            $updateDone->execute([$ocrChars, $now, $now, $jobId]);
            $summary['done']++;
        } catch (Throwable $e) {
            $msg = substr((string) $e->getMessage(), 0, 2000);
            $updateFail->execute([$msg, $now, $now, $jobId]);
            $summary['failed']++;
            $summary['errors'][] = 'Job ' . $jobId . ': ' . $msg;
        }
    }

    return $summary;
}

function ocr_spawn_worker_async($batchToken, $batchSize = 20, $loop = 1)
{
    $phpBinary = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $workerPath = __DIR__ . DIRECTORY_SEPARATOR . 'ocr_worker.php';

    if (!is_file($workerPath)) {
        throw new RuntimeException('Worker OCR nao encontrado para execucao em segundo plano.');
    }

    $logDir = ged_get_root_dir() . DIRECTORY_SEPARATOR . 'log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . DIRECTORY_SEPARATOR . 'ocr_worker_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $batchToken) . '.log';

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath)
        . ' --batch=' . (int) $batchSize
        . ' --batch-token=' . escapeshellarg((string) $batchToken)
        . ' --loop=' . (int) $loop
        . ' --drain=1'
        . ' >> ' . escapeshellarg($logFile)
        . ' 2>&1';

    if (stripos(PHP_OS, 'WIN') === 0) {
        pclose(popen('start /B ' . $cmd, 'r'));
        return $logFile;
    }

    exec('nohup ' . $cmd . ' >/dev/null 2>&1 &');
    return $logFile;
}

function ocr_batch_pending_count(PDO $pdo, $batchToken)
{
    $token = trim((string) $batchToken);
    if ($token === '') {
        return 0;
    }

    ocr_requeue_stale_processing_jobs($pdo, $token);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_ocr_queue WHERE batch_token = ? AND status IN ("queued", "processing")');
    $stmt->execute([$token]);
    return (int) $stmt->fetchColumn();
}

function ocr_requeue_stale_processing_jobs(PDO $pdo, $batchToken = null, $source = null)
{
    ocr_ensure_queue_table($pdo);

    $timeout = ocr_get_processing_timeout();
    $cutoff = ocr_now_ts() - $timeout;
    $batchToken = trim((string) $batchToken);
    $source = trim((string) $source);

    $sql = 'UPDATE app_ocr_queue '
        . 'SET status = "queued", error_message = NULL, started_at = NULL, updated_at = ? '
        . 'WHERE status = "processing" AND updated_at <= ?';
    $params = [ocr_now_ts(), $cutoff];

    if ($batchToken !== '') {
        $sql .= ' AND batch_token = ?';
        $params[] = $batchToken;
    }

    if ($source !== '') {
        $sql .= ' AND source = ?';
        $params[] = $source;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->rowCount();
}

function ocr_find_active_batch_token(PDO $pdo)
{
    ocr_requeue_stale_processing_jobs($pdo);

    $stmt = $pdo->query(
        'SELECT batch_token, MAX(updated_at) AS last_update,\n'
        . 'SUM(CASE WHEN status = "queued" THEN 1 ELSE 0 END) AS queued_count,\n'
        . 'SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) AS processing_count\n'
        . 'FROM app_ocr_queue\n'
        . 'WHERE batch_token IS NOT NULL AND TRIM(batch_token) <> "" AND status IN ("queued", "processing")\n'
        . 'GROUP BY batch_token\n'
        . 'ORDER BY processing_count DESC, queued_count DESC, last_update DESC\n'
        . 'LIMIT 1'
    );

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '';
    }

    return trim((string) ($row['batch_token'] ?? ''));
}

function ocr_find_active_batch_token_by_source(PDO $pdo, $source)
{
    $source = trim((string) $source);
    if ($source === '') {
        return '';
    }

    ocr_requeue_stale_processing_jobs($pdo, null, $source);

    $stmt = $pdo->prepare(
        'SELECT batch_token, MAX(updated_at) AS last_update, '
        . 'SUM(CASE WHEN status = "queued" THEN 1 ELSE 0 END) AS queued_count, '
        . 'SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) AS processing_count '
        . 'FROM app_ocr_queue '
        . 'WHERE source = ? AND batch_token IS NOT NULL AND TRIM(batch_token) <> "" AND status IN ("queued", "processing") '
        . 'GROUP BY batch_token '
        . 'ORDER BY processing_count DESC, queued_count DESC, last_update DESC '
        . 'LIMIT 1'
    );
    $stmt->execute([$source]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '';
    }

    return trim((string) ($row['batch_token'] ?? ''));
}

function ocr_cancel_batch(PDO $pdo, $batchToken, $reason = null, $source = null)
{
    $token = trim((string) $batchToken);
    if ($token === '') {
        return 0;
    }

    ocr_ensure_queue_table($pdo);

    $source = trim((string) $source);
    $reason = trim((string) $reason);
    if ($reason === '') {
        $reason = 'Lote interrompido e reiniciado.';
    }

    $now = ocr_now_ts();

    if ($source !== '') {
        $stmt = $pdo->prepare('UPDATE app_ocr_queue SET status = "failed", error_message = ?, finished_at = ?, updated_at = ? WHERE batch_token = ? AND source = ? AND status IN ("queued", "processing")');
        $stmt->execute([$reason, $now, $now, $token, $source]);
    } else {
        $stmt = $pdo->prepare('UPDATE app_ocr_queue SET status = "failed", error_message = ?, finished_at = ?, updated_at = ? WHERE batch_token = ? AND status IN ("queued", "processing")');
        $stmt->execute([$reason, $now, $now, $token]);
    }

    return (int) $stmt->rowCount();
}

function ocr_reset_active_batch_for_source(PDO $pdo, $source, $reason = null)
{
    $activeToken = ocr_find_active_batch_token_by_source($pdo, $source);
    if ($activeToken === '') {
        return [
            'had_active_batch' => false,
            'canceled_rows' => 0,
            'canceled_batch_token' => '',
        ];
    }

    $canceledRows = ocr_cancel_batch($pdo, $activeToken, $reason, $source);

    return [
        'had_active_batch' => true,
        'canceled_rows' => $canceledRows,
        'canceled_batch_token' => $activeToken,
    ];
}
