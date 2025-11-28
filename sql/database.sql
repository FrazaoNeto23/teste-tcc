-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS burger_house;
USE burger_house;

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'dono') DEFAULT 'cliente',
    telefone VARCHAR(20),
    endereco TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela de produtos
CREATE TABLE produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    categoria VARCHAR(50),
    imagem VARCHAR(255),
    disponivel TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB;

-- Tabela de carrinho
CREATE TABLE carrinho (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cliente INT NOT NULL,
    id_produto INT NOT NULL,
    tipo_produto ENUM('normal', 'especial') DEFAULT 'normal',
    quantidade INT DEFAULT 1,
    adicionado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de pedidos
CREATE TABLE pedidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cliente INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'preparando', 'pronto', 'entregue', 'cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Tabela de itens do pedido
CREATE TABLE pedido_itens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id)
) ENGINE=InnoDB;

-- Inserir usuário admin padrão (senha: admin123)
-- Hash gerado com password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'admin@burgerhouse.com', '$2y$10$vQHFzXQf5tLvXPxXyLJNk.a5gXZ3LHZcFYxGCLxKmFN6uLYm5YiCS', 'dono');

-- Inserir produtos de exemplo
INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES 
('X-Burger Classic', 'Hambúrguer clássico com queijo, alface, tomate e molho especial', 25.90, 'Clássicos', 1),
('X-Bacon', 'Hambúrguer com bacon crocante, queijo cheddar e cebola caramelizada', 29.90, 'Clássicos', 1),
('X-Salad', 'Opção saudável com hambúrguer grelhado, alface, tomate e cenoura', 27.90, 'Saudáveis', 1),
('Monster Burger', 'Duplo hambúrguer, duplo queijo, bacon e molho barbecue', 39.90, 'Premium', 1),
('Chicken Burger', 'Hambúrguer de frango empanado com maionese temperada', 26.90, 'Aves', 1),
('Veggie Burger', 'Hambúrguer vegetariano de grão de bico e especiarias', 28.90, 'Vegetarianos', 1),
('Batata Frita', 'Batata frita crocante porção grande', 15.90, 'Acompanhamentos', 1),
('Onion Rings', 'Anéis de cebola empanados e fritos', 18.90, 'Acompanhamentos', 1),
('Refrigerante Lata', 'Coca-Cola, Guaraná ou Fanta 350ml', 6.90, 'Bebidas', 1),
('Milkshake', 'Milkshake cremoso sabores: chocolate, morango ou baunilha', 16.90, 'Bebidas', 1);
