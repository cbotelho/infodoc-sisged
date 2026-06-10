<?php
 
// Em producao, registre erros sem expor warnings diretamente na resposta.
$debugMode = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
 
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/object_storage_helper.php';

// Definir conexão com o banco de dados dinamicamente usando as constantes
if (!defined('DB_SERVER')) {
    $configFile = __DIR__ . '/../config/database.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    }
}

function create_pdo_connection() {
    $host = defined('DB_SERVER') ? DB_SERVER : '195.200.4.41';
    $db   = defined('DB_DATABASE') ? DB_DATABASE : 'sisged_gea';
    $user = defined('DB_SERVER_USERNAME') ? DB_SERVER_USERNAME : 'admin';
    $pass = defined('DB_SERVER_PASSWORD') ? DB_SERVER_PASSWORD : '';

    $dsn = "mysql:host=" . $host . ";dbname=" . $db . ";charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
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

function normalize_cpf($value) {
    return preg_replace('/\D+/', '', (string) $value);
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

function count_pdf_pages_safe($filePath) {
    try {
        return count_pdf_pages($filePath);
    } catch (Throwable $e) {
        error_log('SEFAZ RH: falha ao contar paginas do PDF "' . $filePath . '": ' . $e->getMessage());
        return 0;
    }
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

function describe_upload_error($errorCode) {
    switch ((int) $errorCode) {
        case UPLOAD_ERR_OK:
            return null;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'o arquivo excede o limite de tamanho permitido';
        case UPLOAD_ERR_PARTIAL:
            return 'o upload foi enviado apenas parcialmente';
        case UPLOAD_ERR_NO_FILE:
            return 'nenhum arquivo foi recebido';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'o servidor esta sem diretorio temporario para upload';
        case UPLOAD_ERR_CANT_WRITE:
            return 'o servidor nao conseguiu gravar o arquivo temporario';
        case UPLOAD_ERR_EXTENSION:
            return 'uma extensao do PHP interrompeu o upload';
        default:
            return 'ocorreu um erro desconhecido no upload';
    }
}

function table_has_column(PDO $pdo, $tableName, $columnName) {
    static $cache = [];

    $cacheKey = $tableName . '.' . $columnName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$tableName, $columnName]);

    $cache[$cacheKey] = ((int) $stmt->fetchColumn()) > 0;

    return $cache[$cacheKey];
}

function get_registro_by_id($pdo, $registroId) {
    $stmt = $pdo->prepare("SELECT id, field_524, field_525, field_526, field_527 FROM app_entity_48 WHERE id = ? LIMIT 1");
    $stmt->execute([$registroId]);

    return $stmt->fetch();
}

function resolve_registro_id_by_numero($pdo, $numero, $secretaria = null, $setor = null, $tipo = null) {
    $numero = trim((string) $numero);

    if ($numero === '') {
        throw new InvalidArgumentException('Informe o numero da Caixa/Pasta.');
    }

    $conditions = ['TRIM(field_527) = ?'];
    $params = [$numero];

    if ($secretaria !== null && $secretaria !== '') {
        $conditions[] = 'field_524 = ?';
        $params[] = trim((string) $secretaria);
    }

    if ($setor !== null && $setor !== '') {
        $conditions[] = 'field_525 = ?';
        $params[] = trim((string) $setor);
    }

    if ($tipo !== null && $tipo !== '') {
        $conditions[] = 'field_526 = ?';
        $params[] = trim((string) $tipo);
    }

    $sql = 'SELECT id FROM app_entity_48 WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registro = $stmt->fetch();

    if (!$registro) {
        throw new InvalidArgumentException('Nenhuma Caixa/Pasta foi encontrada na entidade 48 com os filtros informados.');
    }

    return (int) $registro['id'];
}

function validate_selected_registro($pdo, $registroId, $numero, $secretaria = null, $setor = null, $tipo = null) {
    $registro = get_registro_by_id($pdo, $registroId);

    if (!$registro) {
        throw new InvalidArgumentException('O registro selecionado para a Caixa/Pasta e invalido ou nao existe.');
    }

    if ($numero !== null && trim((string) $numero) !== '' && trim((string) ($registro['field_527'] ?? '')) !== trim((string) $numero)) {
        throw new InvalidArgumentException('O numero informado nao corresponde ao registro pai selecionado na entidade 48.');
    }

    if ($secretaria !== null && $secretaria !== '' && trim((string) ($registro['field_524'] ?? '')) !== trim((string) $secretaria)) {
        throw new InvalidArgumentException('A secretaria informada nao corresponde ao registro pai selecionado na entidade 48.');
    }

    if ($setor !== null && $setor !== '' && trim((string) ($registro['field_525'] ?? '')) !== trim((string) $setor)) {
        throw new InvalidArgumentException('O setor informado nao corresponde ao registro pai selecionado na entidade 48.');
    }

    // Nao valida tipo de forma estrita para manter compatibilidade com registros
    // legados em que o numero pode existir sem vinculo consistente com field_526.
    // O combo de numero ja aplica o melhor filtro possivel e fallback controlado.

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

function get_file_name_parts($fileName) {
    $nameWithoutExtension = pathinfo((string) $fileName, PATHINFO_FILENAME);

    if ($nameWithoutExtension === '') {
        return [];
    }

    return explode('#', $nameWithoutExtension);
}
 
function resolve_document_fields(array $arquivo) {
    $matricula = trim((string) ($arquivo['coluna1'] ?? ''));
    $interessado = trim((string) ($arquivo['coluna2'] ?? ''));
    $cpf = normalize_cpf($arquivo['coluna3'] ?? '');

    return [$matricula, $interessado, $cpf];
}

function prepare_upload_entries(array $arquivos) {
    foreach ($arquivos as &$arquivo) {
        $arquivo['stored_name'] = str_replace('#', '_', $arquivo['nome']);
        $arquivo['total_paginas'] = 0;

        if (strtolower(pathinfo($arquivo['nome'], PATHINFO_EXTENSION)) === 'pdf') {
            $arquivo['total_paginas'] = count_pdf_pages_safe($arquivo['tmp_name']);
        }
    }

    unset($arquivo);

    return $arquivos;
}

function saveArquivo($pdo, $parent_item_id, $arquivos, $tipodoc, $doctipo, $numero, $assunto) {
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

    $hasField563 = table_has_column($pdo, 'app_entity_49', 'field_563');

    if ($hasField563) {
        $stmt = $pdo->prepare("INSERT INTO app_entity_49 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_542, field_543, field_544, field_545, field_546, field_548, field_552, field_553, field_563) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO app_entity_49 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_542, field_543, field_544, field_545, field_546, field_548, field_552, field_553) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    }
    
    foreach ($arquivos as $arquivo) {
        $newFileName = $arquivo['stored_name'];
        list($matricula, $interessado, $cpf) = resolve_document_fields($arquivo);
 
        $params = [
            0,
            $parent_item_id,
            0,
            time(),
            null,
            $arquivo['coluna5'],
            0,
            $newFileName,
            $matricula,
            $interessado,
            $cpf,
            $tipodoc,
            $assunto,
            $arquivo['total_paginas'],
            $numero,
        ];

        if ($hasField563) {
            $params[] = $doctipo;
        }

        $stmt->execute($params);

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
        $requiredFields = require_post_fields(['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc', 'doctipo', 'assunto']);

        $pdo = create_pdo_connection();

        $registroId = isset($_POST['id_registro']) && trim((string) $_POST['id_registro']) !== '' ? (int) $_POST['id_registro'] : 0;
        $numero = $requiredFields['numero'];
        $tratadoPorId = $requiredFields['tratado_por'];
        $padraoRenomeio = (int) $requiredFields['padrao_renomeio'];
        $tipodoc = (int) $requiredFields['tipodoc'];
        $doctipo = (int) $requiredFields['doctipo'];
        $assunto = $requiredFields['assunto'];
        $secretaria = isset($_POST['secretaria']) ? trim((string) $_POST['secretaria']) : null;
        $setor = isset($_POST['setor']) ? trim((string) $_POST['setor']) : null;
        $tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : null;

        if ($padraoRenomeio < 1 || $padraoRenomeio > 4) {
            throw new InvalidArgumentException('O campo Padrao de Renomeio deve estar entre 1 e 4.');
        }

        if ($registroId <= 0) {
            $registroId = resolve_registro_id_by_numero($pdo, $numero, $secretaria, $setor, $tipo);
        }

        validate_selected_registro($pdo, $registroId, $numero, $secretaria, $setor, $tipo);

        // Evita deixar a conexao aberta e ociosa durante o preprocessamento de PDFs grandes.
        $pdo = null;

        if (!isset($_FILES['files']['name']) || !is_array($_FILES['files']['name']) || count($_FILES['files']['name']) === 0) {
            throw new InvalidArgumentException('Nenhum arquivo foi recebido na requisicao.');
        }

        $arquivos = [];
        $arquivosComErro = [];
        $arquivosComErroUpload = [];

        foreach ($_FILES['files']['name'] as $index => $nome) {
            $uploadError = $_FILES['files']['error'][$index] ?? UPLOAD_ERR_OK;

            if ($uploadError !== UPLOAD_ERR_OK) {
                $arquivosComErroUpload[] = [
                    'nome' => $nome,
                    'motivo' => describe_upload_error($uploadError),
                ];
                continue;
            }

            if (!isset($_FILES['files']['tmp_name'][$index]) || $_FILES['files']['tmp_name'][$index] === '') {
                $arquivosComErroUpload[] = [
                    'nome' => $nome,
                    'motivo' => 'o servidor nao disponibilizou arquivo temporario para processamento',
                ];
                continue;
            }

            $partes = get_file_name_parts($nome);

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

        if (!empty($arquivosComErroUpload) || !empty($arquivosComErro)) {
            $mensagens = [];

            if (!empty($arquivosComErroUpload)) {
                $mensagens[] = "Erro ao carregar arquivos. Os seguintes arquivos falharam no upload antes da validacao do nome:";
                foreach ($arquivosComErroUpload as $arquivoErro) {
                    $mensagens[] = '- ' . $arquivoErro['nome'] . ' (' . $arquivoErro['motivo'] . ')';
                }
            }

            if (!empty($arquivosComErro)) {
                $mensagens[] = "Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:";
                foreach ($arquivosComErro as $arquivoErro) {
                    $mensagens[] = '- ' . $arquivoErro;
                }
            }

            echo implode("\n", $mensagens);
        } else {
            $arquivos = prepare_upload_entries($arquivos);
            $pdo = create_pdo_connection();
            $pdo->beginTransaction();
            saveArquivo($pdo, $registroId, $arquivos, $tipodoc, $doctipo, $numero, $assunto);
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