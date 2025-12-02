USE burger_house;
ALTER TABLE pedidos 
ADD COLUMN conta_solicitada TINYINT(1) DEFAULT 0 
AFTER observacoes;
DESCRIBE pedidos;