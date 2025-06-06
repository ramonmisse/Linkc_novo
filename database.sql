-- Database schema for Payment Link Management System
-- MySQL/MariaDB

-- Create database
CREATE DATABASE IF NOT EXISTS payment_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE payment_system;

-- Users table
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('admin', 'editor', 'usuario') DEFAULT 'usuario',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_nivel_acesso (nivel_acesso),
    INDEX idx_ativo (ativo)
);

-- Payment links table
CREATE TABLE payment_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    valor_original DECIMAL(10,2) NOT NULL,
    valor_juros DECIMAL(10,2) DEFAULT 0.00,
    valor_final DECIMAL(10,2) NOT NULL,
    parcelas INT NOT NULL DEFAULT 1,
    link_url TEXT,
    payment_id VARCHAR(255),
    status ENUM('Aguardando Pagamento', 'Pago', 'Crédito Gerado', 'Cancelado') DEFAULT 'Aguardando Pagamento',
    status_cielo INT DEFAULT 0,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_payment_id (payment_id),
    INDEX idx_created_at (created_at)
);

-- Audit log table (optional, for tracking changes)
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user
-- Password: admin123 (change this in production!)
INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso) VALUES 
('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample editor user
-- Password: editor123 (change this in production!)
INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso) VALUES 
('Editor', 'editor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'editor');

-- Create indexes for better performance
CREATE INDEX idx_payment_links_composite ON payment_links (user_id, status, created_at DESC);
CREATE INDEX idx_usuarios_login ON usuarios (email, ativo);

-- Views for reporting (optional)
CREATE VIEW vw_payment_summary AS
SELECT 
    u.nome as usuario_nome,
    u.email as usuario_email,
    u.nivel_acesso,
    COUNT(pl.id) as total_links,
    SUM(pl.valor_original) as total_valor_original,
    SUM(pl.valor_final) as total_valor_final,
    SUM(CASE WHEN pl.status = 'Pago' THEN 1 ELSE 0 END) as links_pagos,
    SUM(CASE WHEN pl.status = 'Aguardando Pagamento' THEN 1 ELSE 0 END) as links_pendentes,
    SUM(CASE WHEN pl.status = 'Crédito Gerado' THEN 1 ELSE 0 END) as links_credito_gerado
FROM usuarios u
LEFT JOIN payment_links pl ON u.id = pl.user_id
WHERE u.ativo = 1
GROUP BY u.id, u.nome, u.email, u.nivel_acesso;

-- Stored procedure for updating payment status
DELIMITER //
CREATE PROCEDURE UpdatePaymentStatus(
    IN p_link_id INT,
    IN p_new_status VARCHAR(50),
    IN p_user_id INT
)
BEGIN
    DECLARE v_current_status VARCHAR(50);
    DECLARE v_user_level VARCHAR(20);
    
    -- Get current status and user level
    SELECT pl.status, u.nivel_acesso
    INTO v_current_status, v_user_level
    FROM payment_links pl
    JOIN usuarios u ON u.id = p_user_id
    WHERE pl.id = p_link_id;
    
    -- Check permissions
    IF v_user_level NOT IN ('admin', 'editor') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acesso negado';
    END IF;
    
    -- Check if status change is valid
    IF p_new_status = 'Crédito Gerado' AND v_current_status != 'Pago' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Só é possível alterar para Crédito Gerado quando o status for Pago';
    END IF;
    
    -- Update status
    UPDATE payment_links 
    SET status = p_new_status, updated_at = NOW()
    WHERE id = p_link_id;
    
    -- Log the change
    INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (p_user_id, 'UPDATE', 'payment_links', p_link_id, 
            JSON_OBJECT('status', v_current_status),
            JSON_OBJECT('status', p_new_status));
END //
DELIMITER ;

-- Function to calculate interest
DELIMITER //
CREATE FUNCTION CalculateInterest(amount DECIMAL(10,2), installments INT) 
RETURNS DECIMAL(10,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    IF installments >= 4 AND installments <= 6 THEN
        RETURN amount * 0.04;
    END IF;
    RETURN 0.00;
END //
DELIMITER ;

-- Trigger to automatically calculate final amount
DELIMITER //
CREATE TRIGGER tr_calculate_final_amount
BEFORE INSERT ON payment_links
FOR EACH ROW
BEGIN
    SET NEW.valor_juros = CalculateInterest(NEW.valor_original, NEW.parcelas);
    SET NEW.valor_final = NEW.valor_original + NEW.valor_juros;
END //
DELIMITER ;

-- Trigger to log payment link changes
DELIMITER //
CREATE TRIGGER tr_audit_payment_links
AFTER UPDATE ON payment_links
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (NEW.user_id, 'STATUS_CHANGE', 'payment_links', NEW.id,
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status));
    END IF;
END //
DELIMITER ;

-- Grant permissions (adjust as needed for your environment)
-- CREATE USER 'payment_user'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON payment_system.* TO 'payment_user'@'localhost';
-- FLUSH PRIVILEGES;
