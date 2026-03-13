-- Tabela para sessões de assinatura
CREATE TABLE IF NOT EXISTS sessoes_assinatura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    documento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NULL,
    documento_assinado_id INT NULL,
    INDEX idx_token (token),
    INDEX idx_documento (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para logs de assinatura
CREATE TABLE IF NOT EXISTS logs_assinatura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_hora DATETIME NOT NULL,
    detalhes JSON NULL,
    INDEX idx_documento (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;