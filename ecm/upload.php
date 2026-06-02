<?php

// Em producao, registre erros sem expor warnings diretamente na resposta.
$debugMode = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);

ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/object_storage_helper.php';

// Definir conexão com o banco de dados
define('DB_SERVER', '195.200.4.41');
define('DB_SERVER_USERNAME', 'admin');
define('DB_SERVER_PASSWORD', '8rekXBff');
define('DB_SERVER_PORT', '');		
define('DB_DATABASE', 'sisged_gea');

function create_pdo_connection() {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, $options);
}

function parse_ini_size_to_bytes($value) {
    $value = trim((string)$value);

    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    switch ($unit) {
        case 'g':
            $number *= 1024;
        case 'm':
            $number *= 1024;
        case 'k':
            $number *= 1024;
    }

    return (int)$number;
}

function request_exceeds_post_limit() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = parse_ini_size_to_bytes(ini_get('post_max_size'));

    if ($contentLength <= 0 || $postMaxSize <= 0) {
        return false;
    }

    return $contentLength > $postMaxSize;
}

function fail_request($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo $message;
    exit;
}

function require_post_fields(array $fieldNames) {
    $values = [];
    $missingFields = [];

    foreach ($fieldNames as $fieldName) {
        $value = $_POST[$fieldName] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $missingFields[] = $fieldName;
            continue;
        }

        $values[$fieldName] = is_string($value) ? trim($value) : $value;
    }

    if (!empty($missingFields)) {
        throw new InvalidArgumentException('Campos obrigatorios ausentes: ' . implode(', ', $missingFields));
    }

    return $values;
}

function count_pdf_pages($filePath) {
    if (!is_file($filePath)) {
        throw new RuntimeException('Arquivo PDF nao encontrado para contagem de paginas.');
    }

    $autoload = __DIR__ . '/vendor/autoload.php';

    if (!is_file($autoload)) {
        throw new RuntimeException('Autoload do vendor nao encontrado para contar paginas do PDF.');
    }

    require_once $autoload;

    if (!class_exists('Smalot\\PdfParser\\Parser')) {
        throw new RuntimeException('Biblioteca PdfParser nao encontrada para contar paginas do PDF.');
    }

    try {
        $parser = new Smalot\PdfParser\Parser();
        $document = $parser->parseFile($filePath);

        return count($document->getPages());
    } catch (Throwable $e) {
        throw new RuntimeException('Falha ao contar paginas do PDF: ' . $e->getMessage(), 0, $e);
    }
}

function get_registro_by_id($pdo, $registroId) {
    $stmt = $pdo->prepare("SELECT id, field_433, field_434, field_436, field_437 FROM app_entity_41 WHERE id = ? LIMIT 1");
    $stmt->execute([$registroId]);

    return $stmt->fetch();
}

function resolve_registro_id_by_numero($pdo, $numero, $secretaria = null, $setor = null, $tipo = null) {
    $numero = trim((string) $numero);

    if ($numero === '') {
        throw new InvalidArgumentException('Informe o numero da Caixa/Pasta.');
    }

    $baseConditions = ['TRIM(field_437) = ?'];
    $baseParams = [$numero];

    if ($secretaria !== null && $secretaria !== '') {
        $baseConditions[] = 'field_433 = ?';
        $baseParams[] = $secretaria;
    }

    if ($setor !== null && $setor !== '') {
        $baseConditions[] = 'field_434 = ?';
        $baseParams[] = $setor;
    }

    $fetchRegistro = function ($useTipoFilter) use ($pdo, $baseConditions, $baseParams, $tipo) {
        $conditions = $baseConditions;
        $params = $baseParams;

        if ($useTipoFilter && $tipo !== null && $tipo !== '') {
            $conditions[] = 'field_436 = ?';
            $params[] = $tipo;
        }

        $sql = 'SELECT id FROM app_entity_41 WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    };

    $registro = $fetchRegistro(true);

    if (!$registro && $tipo !== null && $tipo !== '') {
        $registro = $fetchRegistro(false);
    }

    if (!$registro) {
        throw new InvalidArgumentException('Nenhuma Caixa/Pasta foi encontrada na entidade 41 com os filtros informados.');
    }

    return (int) $registro['id'];
}

function validate_selected_registro($pdo, $registroId, $numero, $secretaria = null, $setor = null, $tipo = null) {
    $registro = get_registro_by_id($pdo, $registroId);

    if (!$registro) {
        throw new InvalidArgumentException('O registro selecionado para a Caixa/Pasta e invalido ou nao existe.');
    }

    if (trim((string) $registro['field_437']) !== trim((string) $numero)) {
        throw new InvalidArgumentException('O numero informado nao corresponde ao registro selecionado.');
    }

    if ($secretaria !== null && $secretaria !== '' && (string) $registro['field_433'] !== (string) $secretaria) {
        throw new InvalidArgumentException('A secretaria informada nao corresponde ao registro selecionado.');
    }

    if ($setor !== null && $setor !== '' && (string) $registro['field_434'] !== (string) $setor) {
        throw new InvalidArgumentException('O setor informado nao corresponde ao registro selecionado.');
    }

    return $registro;
}

function validate_file_name_pattern($partsCount, $padraoRenomeio) {
    if ($partsCount <= 0 || $partsCount > 4) {
        return false;
    }

    switch ($padraoRenomeio) {
        case 1:
            return $partsCount >= 1;
        case 2:
            return $partsCount >= 2;
        case 3:
            return $partsCount >= 3;
        case 4:
            return $partsCount >= 4;
        default:
            return false;
    }
}

function resolve_document_fields(array $arquivo, $padraoRenomeio) {
    $field446 = $arquivo['coluna1'] ?? null;
    $field447 = null;
    $field448 = null;
    $field458 = null;

    if ($padraoRenomeio >= 2) {
        $field447 = $arquivo['coluna2'] ?? null;
    }

    if ($padraoRenomeio >= 3) {
        $field448 = $arquivo['coluna3'] ?? null;
    }

    if ($padraoRenomeio === 4) {
        $field458 = $arquivo['coluna4'] ?? null;
    }

    return [$field446, $field447, $field448, $field458];
}

function ged_inline_ocr_enabled() {
    static $enabled = null;

    if ($enabled !== null) {
        return $enabled;
    }

    $value = getenv('GED_ENABLE_INLINE_OCR');

    if ($value === false || trim((string) $value) === '') {
        $enabled = false;
        return $enabled;
    }

    $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

    return $enabled;
}

function saveArquivo($pdo, $parent_item_id, $arquivos, $tipodoc, $numero, $padraoRenomeio, $setorValue) {
    // Função para extrair metadados do arquivo
    function extract_metadata($file_path, $original_name) {
        return [
            'nome_original' => $original_name,
            'tamanho_bytes' => filesize($file_path),
            'mime_type' => mime_content_type($file_path),
            'extensao' => strtolower(pathinfo($original_name, PATHINFO_EXTENSION)),
            'data_upload' => date('Y-m-d H:i:s'),
        ];
    }

    // Função para extrair texto via OCR
    function extract_ocr($file_path, $original_name) {
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $ocr_text = '';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'gif'])) {
            $output_txt = tempnam(sys_get_temp_dir(), 'ocr_');
            @shell_exec("tesseract \"$file_path\" \"$output_txt\" -l por 2>&1");
            $ocr_text = @file_get_contents($output_txt . '.txt');
            @unlink($output_txt . '.txt');
        } elseif ($ext === 'pdf') {
            $output_txt = tempnam(sys_get_temp_dir(), 'pdftxt_');
            @shell_exec("pdftotext \"$file_path\" \"$output_txt\" 2>&1");
            $ocr_text = @file_get_contents($output_txt);
            @unlink($output_txt);
            if (empty(trim($ocr_text))) {
                $tmp_img = tempnam(sys_get_temp_dir(), 'pdfimg_') . '.png';
                @shell_exec("convert -density 300 \"$file_path\"[0] \"$tmp_img\" 2>&1");
                if (file_exists($tmp_img)) {
                    $output_txt2 = tempnam(sys_get_temp_dir(), 'ocrpdf_');
                    @shell_exec("tesseract \"$tmp_img\" \"$output_txt2\" -l por 2>&1");
                    $ocr_text = @file_get_contents($output_txt2 . '.txt');
                    @unlink($output_txt2 . '.txt');
                    @unlink($tmp_img);
                }
            }
        }
        return $ocr_text;
    }

    // Preparar a statement fora do loop para melhor performance
    $stmt = $pdo->prepare("INSERT INTO app_entity_43 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_445, field_446, field_447, field_448, field_449, field_450, field_458, field_474, field_475, field_554, field_565) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($arquivos as $arquivo) {
        $originalFileName = $arquivo['nome'];
        $newFileName = str_replace("#", "_", $originalFileName);
        $totalPaginas = 0;
        list($field446, $field447, $field448, $field458) = resolve_document_fields($arquivo, $padraoRenomeio);

        if (strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) === 'pdf') {
            $totalPaginas = count_pdf_pages($arquivo['tmp_name']);
        }

        // Extrair metadados e OCR
        $metadados = extract_metadata($arquivo['tmp_name'], $originalFileName);
        $ocr_text = '';

        if (ged_inline_ocr_enabled()) {
            $ocr_text = extract_ocr($arquivo['tmp_name'], $originalFileName);
        }
 
        // Executar o insert com os parâmetros na ordem correta
        $stmt->execute([
            0, // parent_id
            $parent_item_id, // parent_item_id
            0, // linked_id
            time(), // date_added
            null, // date_updated
            $arquivo['coluna5'], // created_by
            0, // sort_order
            $newFileName, // field_445
            $field446, // field_446
            $field447, // field_447
            $field448, // field_448
            $tipodoc, // field_449
            $numero, // field_450
            $field458, // field_458
            json_encode($metadados, JSON_UNESCAPED_UNICODE), // field_474
            $ocr_text, // field_475
            $totalPaginas, // field_554
            $setorValue // field_565
        ]);

        try {
            ged_upload_file($arquivo['tmp_name'], $newFileName, 'upload');
        } catch (Throwable $e) {
            throw new RuntimeException("Erro ao enviar o arquivo {$arquivo['nome']} para o R2. Detalhes: {$e->getMessage()}", 0, $e);
        }
    }
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (request_exceeds_post_limit()) {
        fail_request('Erro ao carregar arquivos. O tamanho total do envio excede o limite permitido pelo servidor.', 413);
    }

    try {
        $pdo = create_pdo_connection();
        $requiredFields = require_post_fields(['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc']);

        $registroId = isset($_POST['id_registro']) && trim((string) $_POST['id_registro']) !== '' ? (int) $_POST['id_registro'] : 0;
        $numero = $requiredFields['numero'];
        $tratadoPorId = $requiredFields['tratado_por'];
        $padraoRenomeio = (int) $requiredFields['padrao_renomeio'];
        $tipodoc = (int) $requiredFields['tipodoc'];
        $secretaria = isset($_POST['secretaria']) ? trim((string) $_POST['secretaria']) : null;
        $setor = isset($_POST['setor']) ? trim((string) $_POST['setor']) : null;
        $tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : null;

        if ($padraoRenomeio < 1 || $padraoRenomeio > 4) {
            throw new InvalidArgumentException('O campo Padrao de Renomeio deve estar entre 1 e 4.');
        }

        if ($registroId <= 0) {
            $registroId = resolve_registro_id_by_numero($pdo, $numero, $secretaria, $setor, $tipo);
        }

        $registro = validate_selected_registro($pdo, $registroId, $numero, $secretaria, $setor, $tipo);
        $setorValue = trim((string) ($registro['field_434'] ?? ''));
        if ($setorValue === '') {
            $setorValue = null;
        }

        if (!isset($_FILES['files']['name']) || !is_array($_FILES['files']['name']) || count($_FILES['files']['name']) === 0) {
            throw new InvalidArgumentException('Nenhum arquivo foi recebido na requisicao.');
        }

        $pdo->beginTransaction();
        
        $arquivos = [];
        $arquivosComErro = []; 

        foreach ($_FILES['files']['name'] as $index => $nome) {
            if (!isset($_FILES['files']['tmp_name'][$index]) || $_FILES['files']['tmp_name'][$index] === '') {
                $arquivosComErro[] = $nome;
                continue;
            }

            $partes = explode('#', $nome);

            if (validate_file_name_pattern(count($partes), $padraoRenomeio)) {
                $arquivos[] = [
                    'nome' => $nome,
                    'tmp_name' => $_FILES['files']['tmp_name'][$index],
                    'coluna1' => $partes[0] ?? null,
                    'coluna2' => $partes[1] ?? null,
                    'coluna3' => $partes[2] ?? null,
                    'coluna4' => $partes[3] ?? null,
                    'coluna5' => $tratadoPorId
                ];
            } else {
                $arquivosComErro[] = $nome; 
            }
        }

        if (!empty($arquivosComErro)) {
            $pdo->rollBack();
            http_response_code(400);
            echo "Erro ao carregar arquivos. Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:\n";
            foreach ($arquivosComErro as $arquivoErro) {
                echo "- " . $arquivoErro . "\n";
            }
        } else {
            saveArquivo($pdo, $registroId, $arquivos, $tipodoc, $numero, $padraoRenomeio, $setorValue);
            $contadorArquivosImportados = count($arquivos);
            $pdo->commit();
            echo "Arquivos carregados com sucesso! Total de arquivos importados: " . $contadorArquivosImportados; 
        }

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(400);
        echo 'Erro ao carregar arquivos. Detalhes: ' . $e->getMessage();
    }
}
?>