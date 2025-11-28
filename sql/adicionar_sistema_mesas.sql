-- ===============================================
-- SISTEMA DE MESAS E SOLICITAÇÃO DE CONTA
-- ===============================================

USE burger_house;

-- Tabela de mesas
CREATE TABLE IF NOT EXISTS mesas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(10) UNIQUE NOT NULL,
    qr_code VARCHAR(255),
    ocupada TINYINT(1) DEFAULT 0,
    ativa TINYINT(1) DEFAULT 1,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_numero (numero),
    INDEX idx_ocupada (ocupada)
) ENGINE=InnoDB;

-- Adicionar colunas na tabela de pedidos
ALTER TABLE pedidos 
ADD COLUMN id_mesa INT DEFAULT NULL AFTER id_cliente,
ADD COLUMN tipo_pedido ENUM('delivery', 'mesa') DEFAULT 'delivery' AFTER id_mesa,
ADD COLUMN conta_solicitada TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN conta_solicitada_em TIMESTAMP NULL AFTER conta_solicitada,
ADD FOREIGN KEY (id_mesa) REFERENCES mesas(id) ON DELETE SET NULL;

-- Inserir mesas de exemplo
INSERT INTO mesas (numero, qr_code, ativa) VALUES 
('1', 'QR_MESA_1', 1),
('2', 'QR_MESA_2', 1),
('3', 'QR_MESA_3', 1),
('4', 'QR_MESA_4', 1),
('5', 'QR_MESA_5', 1),
('6', 'QR_MESA_6', 1),
('7', 'QR_MESA_7', 1),
('8', 'QR_MESA_8', 1),
('9', 'QR_MESA_9', 1),
('10', 'QR_MESA_10', 1);

-- Verificar
SELECT * FROM mesas;
