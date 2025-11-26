<?php

// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//require_once 'fpdi260/autoload.php';
//use setasign\Fpdi\Fpdi;

// Definir conexão com o banco de dados
define('DB_SERVER', 'localhost');
define('DB_SERVER_USERNAME', 'u578749560_carlosbotelho');
define('DB_SERVER_PASSWORD', 'r:UuBeNTFPH5');
define('DB_DATABASE', 'u578749560_infodoc');

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

function saveRegistro($pdo, $parent_id, $parent_item_id, $linked_id, $date_added, $date_updated, $created_by, $sort_order, $field_432, $field_433, $field_434, $field_436, $field_437) {
    $stmt = $pdo->prepare("INSERT INTO app_entity_41 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_432, field_433, field_434, field_436, field_437) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$parent_id, $parent_item_id, $linked_id, $date_added, $date_updated, $created_by, $sort_order, $field_432, $field_433, $field_434, $field_436, $field_437]);
    return $pdo->lastInsertId();
}

function saveArquivo($pdo, $parent_item_id, $arquivos) {

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

    $stmt = $pdo->prepare("INSERT INTO app_entity_43 (parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, field_445, field_446, field_447, field_448, field_449, field_450, field_458) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($arquivos as $arquivo) {
        $originalFileName = $arquivo['nome'];
        $newFileName = str_replace("#", "_", $originalFileName);
        $target_file = $target_dir . $newFileName;
        $arquivo['coluna5'] = getFileNameWithoutExtension($arquivo['coluna5']);
        $totalPages =  count_pages($arquivo['tmp_name']);        //countPdfPages($arquivo['tmp_name']);
        
        $stmt->execute([0, $parent_item_id, 0, time(), null, 4, 0, $newFileName, $arquivo['coluna1'], $arquivo['coluna2'], $arquivo['coluna3'], $arquivo['coluna4'], $arquivo['coluna5'], $totalPages]);
    
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
    // Split the filename by the last occurrence of a period (.)
    $parts = explode('.pdf', $fileName);
    // Return the first part (filename without extension)
    return $parts[0];
}

/*
function countPdfPages($filePath) {
    $pdf = new Fpdi();
    $pageCount = $pdf->setSourceFile($filePath);
    return $pageCount;
}
*/

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

    try {
        $pdo->beginTransaction();
        
        // Salvar registro na tabela app_entity_41
        $parent_item_id = saveRegistro($pdo, 0, 0, 0, time(), null, 4, 0, 1, $secretaria, $setor, $tipo, $numero);
        
        // Preparar arquivos para salvar na tabela app_entity_43
        $arquivos = [];
        foreach ($_FILES['files']['name'] as $index => $nome) {
            $partes = explode('#', $nome);
            if(count($partes) == 4) {
                $arquivos[] = [
                    'nome' => $nome,
                    'tmp_name' => $_FILES['files']['tmp_name'][$index],
                    'coluna1' => $partes[0],
                    'coluna2' => $partes[1],
                    'coluna3' => $partes[2],
                    'coluna4' => $partes[3],
                    'coluna5' => $numero      //$partes[4]
                ];
            } else {
                throw new Exception('Formato de nome de arquivo inválido: ' . $nome);
            }
        }

        // Salvar arquivos na tabela app_entity_43
        saveArquivo($pdo, $parent_item_id, $arquivos);
        
        $pdo->commit();
        echo 'Arquivos carregados com sucesso!';
    } catch (Exception $e) {
        $pdo->rollBack();
        echo 'Erro ao carregar arquivos. Detalhes: ' . $e->getMessage();
    }
}
?>
