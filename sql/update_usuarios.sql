-- Adiciona o campo de credencial na tabela de usuários
ALTER TABLE usuarios ADD COLUMN credencial_cielo ENUM('matriz', 'filial') NOT NULL DEFAULT 'matriz';

-- Atualiza os usuários existentes para usar a credencial matriz
UPDATE usuarios SET credencial_cielo = 'matriz' WHERE credencial_cielo IS NULL; 