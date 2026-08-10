<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Simular o que ocr_queue_api.php faz
    require_once __DIR__ . '/ecm/ocr_queue_common.php';
    
    echo "✓ ocr_queue_common.php carregado com sucesso\n";
    
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Conexao com banco indisponivel.');
    }
    
    echo "✓ PDO disponível: " . get_class($pdo) . "\n";
    
    // Testar ocr_ensure_queue_table
    ocr_ensure_queue_table($pdo);
    echo "✓ Tabela OCR ensured\n";
    
    // Testar ocr_reset_active_batch_for_source
    $resetResult = ocr_reset_active_batch_for_source($pdo, 'ged', 'Teste');
    echo "✓ Reset batch: " . json_encode($resetResult) . "\n";
    
    // Testar ocr_find_active_batch_token_by_source
    $token = ocr_find_active_batch_token_by_source($pdo, 'ged');
    echo "✓ Find active batch: " . ($token === '' ? '(nenhum)' : $token) . "\n";
    
    echo "\n=== TUDO OK ===\n";
    
} catch (Throwable $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack:\n" . $e->getTraceAsString() . "\n";
}
?>
