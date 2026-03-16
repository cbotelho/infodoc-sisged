<?php
// processar_assinatura.php
require_once 'functions_python.php';
header('Content-Type: application/json');

session_start();

try {
    // Receber dados JSON
    $dados = json_decode(file_get_contents('php://input'), true);
    
    $documento_id = $dados['documento_id'] ?? 0;
    $token = $dados['token'] ?? '';
    $certificado_id = $dados['certificado_id'] ?? 0;
    $senha = $dados['senha'] ?? '';
    $posicao = $dados['posicao'] ?? [];
    $usarCLI = $dados['usar_cli'] ?? false;
    
    // Validar sessão
    $pdo = getAssinadorPdo();
    $stmt = $pdo->prepare("
        SELECT s.*, d.caminho as doc_path, c.caminho as cert_path
        FROM sessoes_assinatura s
        JOIN documentos d ON s.documento_id = d.id
        JOIN certificados c ON c.id = ?
        WHERE s.token = ? AND s.data_fim IS NULL
    ");
    $stmt->execute([$certificado_id, $token]);
    $sessao = $stmt->fetch();
    
    if (!$sessao) {
        throw new Exception('Sessão inválida');
    }
    
    // Escolher método de assinatura
    if (!$usarCLI && pythonServiceStatus()) {
        // Usar API REST
        $resultado = assinarComPython(
            $token,
            $certificado_id,
            $senha,
            $posicao
        );
    } else {
        // Usar CLI como fallback
        $resultado = assinarComPythonCLI(
            $sessao['doc_path'],
            $sessao['cert_path'],
            $senha,
            $posicao
        );
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}