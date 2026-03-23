<?php

// Em producao, registre erros sem expor warnings diretamente na resposta.
$debugMode = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);

ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Definir conexão com o banco de dados
define('DB_SERVER', '195.200.4.41');
define('DB_SERVER_USERNAME', 'admin');
define('DB_SERVER_PASSWORD', '8rekXBff');
define('DB_SERVER_PORT', '');		
define('DB_DATABASE', 'sisged_gea');

$dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
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

function load_r2_sdk() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $autoload = dirname(__DIR__) . '/plugins/ext/file_storage_modules/r2/vendor/autoload.php';

    if (!is_file($autoload)) {
        throw new RuntimeException('AWS SDK nao encontrada para envio ao R2.');
    }

    require_once $autoload;
    $loaded = true;
}

function build_r2_client() {
    load_r2_sdk();

    $endpoint = getenv('FILE_STORAGE_R2_ENDPOINT') ?: '';
    $region = getenv('FILE_STORAGE_R2_REGION') ?: 'auto';
    $accessKeyId = getenv('FILE_STORAGE_R2_ACCESS_KEY_ID') ?: '';
    $secretAccessKey = getenv('FILE_STORAGE_R2_SECRET_ACCESS_KEY') ?: '';
    $bucket = getenv('FILE_STORAGE_R2_BUCKET') ?: '';

    if ($endpoint === '' || $accessKeyId === '' || $secretAccessKey === '' || $bucket === '') {
        throw new RuntimeException('Configuracao R2 incompleta no ambiente.');
    }

    return new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $region,
        'endpoint' => $endpoint,
        'credentials' => [
            'key' => $accessKeyId,
            'secret' => $secretAccessKey,
        ],
        'signature_version' => 'v4',
    ]);
}

function upload_file_to_r2($localPath, $fileName) {
    $bucket = getenv('FILE_STORAGE_R2_BUCKET') ?: '';
    $prefix = trim((string)(getenv('FILE_STORAGE_R2_OBJECT_PREFIX') ?: 'ged'), '/');
    $parts = array_filter([$prefix, 'upload', $fileName], 'strlen');
    $objectKey = implode('/', $parts);

    $client = build_r2_client();

    $client->putObject([
        'Bucket' => $bucket,
        'Key' => $objectKey,
        'SourceFile' => $localPath,
        'ContentType' => mime_content_type($localPath) ?: 'application/octet-stream',
    ]);

    return $objectKey;
}

function get_registro_by_id($pdo, $registroId) {
    $stmt = $pdo->prepare("SELECT id, field_433, field_434, field_436, field_437 FROM app_entity_41 WHERE id = ? LIMIT 1");
    $stmt->execute([$registroId]);

    return $stmt->fetch();
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

    if ($tipo !== null && $tipo !== '' && (string) $registro['field_436'] !== (string) $tipo) {
        throw new InvalidArgumentException('O tipo informado nao corresponde ao registro selecionado.');
    }

    return $registro;
}

function saveArquivo($pdo, $parent_item_id, $arquivos, $tipodoc, $numero) {
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

    function count_pages($pdfname) {
        $pdftext = file_get_contents($pdfname);
        $num = preg_match_all("/\/Page\W/", $pdftext, $dummy);
        return $num;
    }

    function getFileNameWithoutExtension($fileName) {
        $parts = explode('.pdf', $fileName);
        return $parts[0];
    }

    $upload_dir = "../upload/";
    $ano = date('Y');
    $mes = date('m');
    $dia = date('d');
    $target_dir = $upload_dir;

    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            die('Falha ao criar diretório de upload...');
        }
    }

    // Preparar a statement fora do loop para melhor performance
    $stmt = $pdo->prepare("INSERT INTO app_entity_43 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_445, field_446, field_447, field_448, field_449, field_450, field_458, field_474, field_475) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($arquivos as $arquivo) {
        $originalFileName = $arquivo['nome'];
        $newFileName = str_replace("#", "_", $originalFileName);
        $target_file = $target_dir . $newFileName;
        
        $totalPages = 0;
        if (strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) === 'pdf') {
            $totalPages = count_pages($arquivo['tmp_name']);
        }

        // Extrair metadados e OCR
        $metadados = extract_metadata($arquivo['tmp_name'], $originalFileName);
        $ocr_text = extract_ocr($arquivo['tmp_name'], $originalFileName);

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
            $arquivo['coluna1'], // field_446
            $arquivo['coluna2'], // field_447
            $arquivo['coluna3'], // field_448
            $tipodoc, // field_449 - Agora recebe o valor correto do POST
            $numero, // field_450
            $arquivo['coluna4'], // field_458
            json_encode($metadados, JSON_UNESCAPED_UNICODE), // field_474
            $ocr_text // field_475
        ]);

        if (!move_uploaded_file($arquivo['tmp_name'], $target_file)) {
            throw new RuntimeException("Erro ao mover o arquivo {$arquivo['nome']} para {$target_file}. Verifique as permissões do diretório.");
        }

        if (strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) === 'pdf') {
            try {
                upload_file_to_r2($target_file, basename($target_file));
            } catch (Exception $e) {
                throw new RuntimeException("Erro ao enviar o arquivo {$arquivo['nome']} para o R2. Detalhes: {$e->getMessage()}", 0, $e);
            }
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
        $requiredFields = require_post_fields(['id_registro', 'numero', 'tratado_por', 'tipodoc']);

        $registroId = (int) $requiredFields['id_registro'];
        $numero = $requiredFields['numero'];
        $tratadoPorId = $requiredFields['tratado_por'];
        $tipodoc = (int) $requiredFields['tipodoc'];
        $secretaria = isset($_POST['secretaria']) ? trim((string) $_POST['secretaria']) : null;
        $setor = isset($_POST['setor']) ? trim((string) $_POST['setor']) : null;
        $tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : null;

        if ($registroId <= 0) {
            throw new InvalidArgumentException('O registro selecionado para a Caixa/Pasta e invalido ou nao existe.');
        }

        validate_selected_registro($pdo, $registroId, $numero, $secretaria, $setor, $tipo);

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

            if (count($partes) >= 3 && count($partes) <= 4) { 
                $arquivos[] = [
                    'nome' => $nome,
                    'tmp_name' => $_FILES['files']['tmp_name'][$index],
                    'coluna1' => $partes[0],
                    'coluna2' => $partes[1],
                    'coluna3' => $partes[2],
                    'coluna4' => $partes[3] ?? null,
                    'coluna5' => $tratadoPorId
                ];
            } else {
                $arquivosComErro[] = $nome; 
            }
        }

        if (!empty($arquivosComErro)) {
            $pdo->rollBack();
            echo "Erro ao carregar arquivos. Os seguintes arquivos possuem formato inválido. Use nomes com 3 ou 4 partes separadas por #:\n";
            foreach ($arquivosComErro as $arquivoErro) {
                echo "- " . $arquivoErro . "\n";
            }
        } else {
            saveArquivo($pdo, $registroId, $arquivos, $tipodoc, $numero);
            $contadorArquivosImportados = count($arquivos);
            $pdo->commit();
            echo "Arquivos carregados com sucesso! Total de arquivos importados: " . $contadorArquivosImportados; 
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo 'Erro ao carregar arquivos. Detalhes: ' . $e->getMessage();
    }
}
?>