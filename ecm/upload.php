<?php

// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require_once 'fpdi260/autoload.php';
// use setasign\Fpdi\Fpdi;

// Definir conexão com o banco de dados
define('DB_SERVER', 'localhost');
define('DB_SERVER_USERNAME', 'u578749560_botelho_sisged');
define('DB_SERVER_PASSWORD', '@#Botelho751953#@');
define('DB_DATABASE', 'u578749560_sisged');

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

function saveRegistro($pdo, $parent_id, $parent_item_id, $linked_id, $date_added, $date_updated, $created_by, $sort_order, $field_432, $field_433, $field_434, $field_436, $field_437, $field_463) {
    $stmt = $pdo->prepare("INSERT INTO app_entity_41 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_432, field_433, field_434, field_436, field_437, field_463) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$parent_id, $parent_item_id, $linked_id, $date_added, $date_updated, $created_by, $sort_order, $field_432, $field_433, $field_434, $field_436, $field_437, $field_463]);
    return $pdo->lastInsertId();
}

function saveArquivo($pdo, $parent_item_id, $arquivos) {

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
        // Imagem: usar tesseract
        $output_txt = tempnam(sys_get_temp_dir(), 'ocr_');
        @shell_exec("tesseract \"$file_path\" \"$output_txt\" -l por 2>&1");
        $ocr_text = @file_get_contents($output_txt . '.txt');
        @unlink($output_txt . '.txt');
    } elseif ($ext === 'pdf') {
        // PDF: tentar extrair texto com pdftotext, se não, converter páginas para imagem e rodar tesseract
        $output_txt = tempnam(sys_get_temp_dir(), 'pdftxt_');
        @shell_exec("pdftotext \"$file_path\" \"$output_txt\" 2>&1");
        $ocr_text = @file_get_contents($output_txt);
        @unlink($output_txt);
        if (empty(trim($ocr_text))) {
            // fallback: converter páginas para imagens e rodar tesseract (requer imagemagick e tesseract instalados)
            $tmp_img = tempnam(sys_get_temp_dir(), 'pdfimg_') . '.png';
            @shell_exec("convert -density 300 \"$file_path\"[0] \"$tmp_img\" 2>&1"); // só a primeira página
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


    $upload_dir = "../upload/";

    // Obter data atual
    $ano = date('Y');
    $mes = date('m');
    $dia = date('d');

    // Construir caminho completo para o diretório de destino
    $target_dir = $upload_dir; // . "{$ano}/{$mes}/{$dia}/";

    // Verificar se o diretório de destino existe, se não, criá-lo recursivamente
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            die('Falha ao criar diretório de upload...');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO app_entity_43 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_445, field_446, field_447, field_448, field_449, field_450, field_458, field_474, field_475) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($arquivos as $arquivo) {
        $originalFileName = $arquivo['nome'];
        $newFileName = str_replace("#", "_", $originalFileName);
        $target_file = $target_dir . $newFileName;
        $arquivo['coluna5'] = getFileNameWithoutExtension($arquivo['coluna5']);
        $totalPages = count_pages($arquivo['tmp_name']); 

        // Extrair metadados e OCR do arquivo temporário antes de mover
        $metadados = extract_metadata($arquivo['tmp_name'], $originalFileName);
        $ocr_text = extract_ocr($arquivo['tmp_name'], $originalFileName);

        // Salvar no banco
        $stmt->execute([
            0, // parent_id
            $parent_item_id,
            0, // linked_id
            time(), // date_added
            null, // date_updated
            $arquivo['coluna6'], // created_by
            0, // sort_order
            $newFileName, // field_445
            $arquivo['coluna1'],
            $arquivo['coluna2'],
            $arquivo['coluna3'],
            $arquivo['coluna4'],
            $arquivo['coluna5'],
            $totalPages,
            json_encode($metadados, JSON_UNESCAPED_UNICODE), // field_474 - Metadados
            $ocr_text // field_475 - OCR
        ]);

        if (move_uploaded_file($arquivo['tmp_name'], $target_file)) {
            // Arquivo movido com sucesso
        } else {
            // Tratar erro se o arquivo não puder ser movido
            echo "Erro ao mover o arquivo {$arquivo['nome']} para {$target_file}. Verifique as permissões do diretório e se o caminho está correto.";
        }
    }
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function getFileNameWithoutExtension($fileName) {
    $parts = explode('.pdf', $fileName);
    return $parts[0];
}


function count_pages($pdfname) {
    $pdftext = file_get_contents($pdfname);
    $num = preg_match_all("/\/Page\W/", $pdftext, $dummy);
    return $num;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secretaria = $_POST['secretaria'];
    $setor = $_POST['setor'];
    $tipo = $_POST['tipo'];
    $numero = $_POST['numero'];
    $tratadoPorId = $_POST['tratado_por'];

    try {
        $pdo->beginTransaction();

        $parent_item_id = saveRegistro($pdo, 0, 0, 0, time(), null, $tratadoPorId, 0, 1, $secretaria, $setor, $tipo, $numero, $tratadoPorId);
        
        $arquivos = [];
        $arquivosComErro = []; 

        foreach ($_FILES['files']['name'] as $index => $nome) {
            $partes = explode('#', $nome);

            if (count($partes) == 4) { 
                $arquivos[] = [
                    'nome' => $nome,
                    'tmp_name' => $_FILES['files']['tmp_name'][$index],
                    'coluna1' => $partes[0],
                    'coluna2' => $partes[1],
                    'coluna3' => $partes[2],
                    'coluna4' => $partes[3],
                    'coluna5' => $numero,
                    'coluna6' => $tratadoPorId
                ];
            } else {
                $arquivosComErro[] = $nome; 
            }
        }

        if (!empty($arquivosComErro)) {
            $pdo->rollBack();

            echo "Erro ao carregar arquivos. Os seguintes arquivos possuem formato inválido:\n";
            foreach ($arquivosComErro as $arquivoErro) {
                echo "- " . $arquivoErro . "\n";
            }

        } else {
            $contadorArquivosImportados = 0; // Inicializa o contador
            saveArquivo($pdo, $parent_item_id, $arquivos);

            $contadorArquivosImportados = count($arquivos); // Conta os arquivos importados

            $pdo->commit();
            echo "Arquivos carregados com sucesso! Total de arquivos importados: " . $contadorArquivosImportados; 
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo 'Erro ao carregar arquivos. Detalhes: ' . $e->getMessage();
    }
}
?>