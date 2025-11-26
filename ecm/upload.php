<?php
require_once __DIR__ . '/vendor/autoload.php';

// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

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
    // Defina o diretório de upload
    $upload_dir = __DIR__ . '/../upload/';
    $mes = date('m');
    $dia = date('d');
    $ano = date('Y');
    // Você pode ajustar o diretório conforme sua necessidade
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

        // Extrair extensão
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $ocr_text = '';
        // OCR ou extração de texto
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'gif'])) {
            // Imagem: usar Tesseract OCR PHP
            try {
                if (class_exists('\\TesseractOCR')) {
                    $ocr = new \TesseractOCR($arquivo['tmp_name']);
                    $ocr->lang('por');
                    $ocr_text = $ocr->run();
                } else {
                    $ocr_text = 'Tesseract OCR não disponível';
                }
            } catch (\Exception $e) {
                $ocr_text = 'Erro no OCR: ' . $e->getMessage();
            }
        } elseif ($ext === 'pdf') {
            try {
                if (class_exists('\\Smalot\\PdfParser\\Parser')) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($arquivo['tmp_name']);
                    $ocr_text = $pdf->getText();
                } else {
                    $ocr_text = 'PDF Parser não disponível';
                }
            } catch (\Exception $e) {
                $ocr_text = 'Erro no PDF Parser: ' . $e->getMessage();
            }
        }

        // Extrair metadados do arquivo
        $metadados = [
            'nome_original' => $originalFileName,
            'tamanho_bytes' => filesize($arquivo['tmp_name']),
            'extensao' => $ext,
            'data_upload' => date('Y-m-d H:i:s')
        ];

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