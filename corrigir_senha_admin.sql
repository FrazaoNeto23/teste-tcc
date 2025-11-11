-- ===============================================
-- CORREÇÃO: Senha do Administrador
-- ===============================================
-- Execute este arquivo para corrigir a senha do admin

USE burger_house;

-- Atualizar senha do admin para: admin123
-- Hash gerado com password_hash('admin123', PASSWORD_DEFAULT)
UPDATE usuarios 
SET senha = '$2y$10$vQHFzXQf5tLvXPxXyLJNk.a5gXZ3LHZcFYxGCLxKmFN6uLYm5YiCS'
WHERE email = 'admin@burgerhouse.com';

-- Verificar se atualizou
SELECT id, nome, email, tipo FROM usuarios WHERE tipo = 'dono';

-- ===============================================
-- ALTERNATIVA: Se ainda não funcionar
-- ===============================================
-- Delete o admin antigo e crie um novo:

-- DELETE FROM usuarios WHERE email = 'admin@burgerhouse.com';
-- INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
-- ('Administrador', 'admin@burgerhouse.com', '$2y$10$vQHFzXQf5tLvXPxXyLJNk.a5gXZ3LHZcFYxGCLxKmFN6uLYm5YiCS', 'dono');
