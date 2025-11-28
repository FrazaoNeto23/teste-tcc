-- ===============================================
-- ATUALIZAÇÃO: Adicionar campos de mesa e retirada
-- ===============================================
-- Execute este arquivo para adicionar os novos campos

USE burger_house;

-- Adicionar coluna de número da mesa
ALTER TABLE pedidos ADD COLUMN numero_mesa VARCHAR(10) DEFAULT NULL AFTER observacoes;

-- Adicionar coluna de tipo de retirada (mesa ou balcao)
ALTER TABLE pedidos ADD COLUMN tipo_retirada ENUM('mesa', 'balcao') DEFAULT 'balcao' AFTER numero_mesa;

-- Verificar se as colunas foram adicionadas
DESCRIBE pedidos;

-- ===============================================
-- Se der erro de coluna já existente, ignore
-- ===============================================
