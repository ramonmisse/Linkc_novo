-- Database schema for Payment Link Management System
-- PostgreSQL version

-- Users table
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    nivel_acesso VARCHAR(20) DEFAULT 'usuario' CHECK (nivel_acesso IN ('admin', 'editor', 'usuario')),
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for users
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_nivel_acesso ON usuarios(nivel_acesso);
CREATE INDEX idx_usuarios_ativo ON usuarios(ativo);
CREATE INDEX idx_usuarios_login ON usuarios(email, ativo);

-- Payment links table
CREATE TABLE payment_links (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    valor_original DECIMAL(10,2) NOT NULL,
    valor_juros DECIMAL(10,2) DEFAULT 0.00,
    valor_final DECIMAL(10,2) NOT NULL,
    parcelas INTEGER NOT NULL DEFAULT 1,
    link_url TEXT,
    payment_id VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Aguardando Pagamento' CHECK (status IN ('Aguardando Pagamento', 'Pago', 'Crédito Gerado', 'Cancelado')),
    status_cielo INTEGER DEFAULT 0,
    descricao TEXT,
    tipo_link VARCHAR(50),
    data_expiracao TIMESTAMP,
    url_completa TEXT,
    url_curta TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Create indexes for payment_links
CREATE INDEX idx_payment_links_user_id ON payment_links(user_id);
CREATE INDEX idx_payment_links_status ON payment_links(status);
CREATE INDEX idx_payment_links_payment_id ON payment_links(payment_id);
CREATE INDEX idx_payment_links_created_at ON payment_links(created_at);
CREATE INDEX idx_payment_links_composite ON payment_links(user_id, status, created_at DESC);

-- Audit log table
CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INTEGER,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Create indexes for audit_log
CREATE INDEX idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX idx_audit_log_action ON audit_log(action);
CREATE INDEX idx_audit_log_table_name ON audit_log(table_name);
CREATE INDEX idx_audit_log_created_at ON audit_log(created_at);

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_usuarios_updated_at BEFORE UPDATE ON usuarios
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payment_links_updated_at BEFORE UPDATE ON payment_links
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function to calculate interest
CREATE OR REPLACE FUNCTION calculate_interest(amount DECIMAL(10,2), installments INTEGER)
RETURNS DECIMAL(10,2) AS $$
BEGIN
    IF installments >= 4 AND installments <= 6 THEN
        RETURN amount * 0.04;
    END IF;
    RETURN 0.00;
END;
$$ LANGUAGE plpgsql;

-- Function to automatically calculate final amount
CREATE OR REPLACE FUNCTION calculate_final_amount()
RETURNS TRIGGER AS $$
BEGIN
    NEW.valor_juros = calculate_interest(NEW.valor_original, NEW.parcelas);
    NEW.valor_final = NEW.valor_original + NEW.valor_juros;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically calculate final amount
CREATE TRIGGER tr_calculate_final_amount
    BEFORE INSERT ON payment_links
    FOR EACH ROW EXECUTE FUNCTION calculate_final_amount();

-- Function to log payment link changes
CREATE OR REPLACE FUNCTION audit_payment_links_changes()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.status IS DISTINCT FROM NEW.status THEN
        INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (NEW.user_id, 'STATUS_CHANGE', 'payment_links', NEW.id,
                jsonb_build_object('status', OLD.status),
                jsonb_build_object('status', NEW.status));
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to log payment link changes
CREATE TRIGGER tr_audit_payment_links
    AFTER UPDATE ON payment_links
    FOR EACH ROW EXECUTE FUNCTION audit_payment_links_changes();

-- Insert default admin user
-- Password: admin123 (change this in production!)
INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso) VALUES 
('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample editor user
-- Password: editor123 (change this in production!)
INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso) VALUES 
('Editor', 'editor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'editor');

-- Create view for payment summary
CREATE VIEW vw_payment_summary AS
SELECT 
    u.nome as usuario_nome,
    u.email as usuario_email,
    u.nivel_acesso,
    COUNT(pl.id) as total_links,
    COALESCE(SUM(pl.valor_original), 0) as total_valor_original,
    COALESCE(SUM(pl.valor_final), 0) as total_valor_final,
    COUNT(CASE WHEN pl.status = 'Pago' THEN 1 END) as links_pagos,
    COUNT(CASE WHEN pl.status = 'Aguardando Pagamento' THEN 1 END) as links_pendentes,
    COUNT(CASE WHEN pl.status = 'Crédito Gerado' THEN 1 END) as links_credito_gerado
FROM usuarios u
LEFT JOIN payment_links pl ON u.id = pl.user_id
WHERE u.ativo = true
GROUP BY u.id, u.nome, u.email, u.nivel_acesso;