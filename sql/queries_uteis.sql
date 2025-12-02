UPDATE pedidos
SET
    criado_em = DATE_SUB (NOW (), INTERVAL 35 DAY)
WHERE
    id IN (1, 2, 3);

UPDATE pedidos
SET
    criado_em = DATE_SUB (NOW (), INTERVAL 5 DAY)
WHERE
    id = 1;

UPDATE pedidos
SET
    criado_em = DATE_SUB (NOW (), INTERVAL 15 DAY)
WHERE
    id = 2;

UPDATE pedidos
SET
    criado_em = DATE_SUB (NOW (), INTERVAL 45 DAY)
WHERE
    id = 3;

UPDATE pedidos
SET
    criado_em = DATE_SUB (NOW (), INTERVAL 90 DAY)
WHERE
    id = 4;

SELECT
    id,
    id_cliente,
    total,
    status,
    criado_em
FROM
    pedidos
WHERE
    criado_em >= DATE_SUB (NOW (), INTERVAL 30 DAY)
ORDER BY
    criado_em DESC;

SELECT
    id,
    id_cliente,
    total,
    status,
    criado_em
FROM
    pedidos
WHERE
    criado_em < DATE_SUB (NOW (), INTERVAL 30 DAY)
ORDER BY
    criado_em DESC;

SELECT
    status,
    COUNT(*) as quantidade
FROM
    pedidos
WHERE
    criado_em < DATE_SUB (NOW (), INTERVAL 30 DAY)
GROUP BY
    status;

SELECT
    table_name AS 'Tabela',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Tamanho (MB)'
FROM
    information_schema.TABLES
WHERE
    table_schema = 'burger_house'
ORDER BY
    (data_length + index_length) DESC;

SELECT
    'pedidos' as tabela,
    COUNT(*) as total
FROM
    pedidos
UNION ALL
SELECT
    'pedido_itens',
    COUNT(*)
FROM
    pedido_itens
UNION ALL
SELECT
    'produtos',
    COUNT(*)
FROM
    produtos
UNION ALL
SELECT
    'usuarios',
    COUNT(*)
FROM
    usuarios
UNION ALL
SELECT
    'carrinho',
    COUNT(*)
FROM
    carrinho;

DELETE FROM pedido_itens
WHERE
    id_pedido IN (
        SELECT
            id
        FROM
            pedidos
        WHERE
            id_cliente = 2
    );

DELETE FROM pedidos
WHERE
    id_cliente = 2;

DELETE FROM carrinho
WHERE
    id_cliente = 2;

SELECT
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
    ) as 'Total Pedidos',
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
        WHERE
            status = 'pendente'
    ) as 'Pendentes',
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
        WHERE
            status = 'preparando'
    ) as 'Preparando',
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
        WHERE
            status = 'pronto'
    ) as 'Prontos',
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
        WHERE
            status = 'entregue'
    ) as 'Entregues',
    (
        SELECT
            COUNT(*)
        FROM
            pedidos
        WHERE
            status = 'cancelado'
    ) as 'Cancelados',
    (
        SELECT
            SUM(total)
        FROM
            pedidos
        WHERE
            status = 'entregue'
    ) as 'Faturamento',
    (
        SELECT
            COUNT(*)
        FROM
            usuarios
        WHERE
            tipo = 'cliente'
    ) as 'Clientes',
    (
        SELECT
            COUNT(*)
        FROM
            produtos
        WHERE
            disponivel = 1
    ) as 'Produtos Ativos';

SELECT
    DATE_FORMAT (criado_em, '%Y-%m') as 'Mês',
    COUNT(*) as 'Total Pedidos',
    SUM(total) as 'Faturamento'
FROM
    pedidos
GROUP BY
    DATE_FORMAT (criado_em, '%Y-%m')
ORDER BY
    criado_em DESC;

SELECT
    u.nome as 'Cliente',
    u.email as 'Email',
    COUNT(p.id) as 'Total Pedidos',
    SUM(p.total) as 'Total Gasto'
FROM
    usuarios u
    LEFT JOIN pedidos p ON u.id = p.id_cliente
WHERE
    u.tipo = 'cliente'
GROUP BY
    u.id
ORDER BY
    SUM(p.total) DESC
LIMIT
    10;

OPTIMIZE TABLE pedidos;

OPTIMIZE TABLE pedido_itens;

OPTIMIZE TABLE produtos;

OPTIMIZE TABLE usuarios;

OPTIMIZE TABLE carrinho;

REPAIR TABLE pedidos;

REPAIR TABLE pedido_itens;

CHECK TABLE pedidos;

CHECK TABLE pedido_itens;

DELETE pi
FROM
    pedido_itens pi
    INNER JOIN pedidos p ON pi.id_pedido = p.id
WHERE
    p.criado_em < DATE_SUB (NOW (), INTERVAL 30 DAY);

DELETE FROM pedidos
WHERE
    criado_em < DATE_SUB (NOW (), INTERVAL 30 DAY);

SELECT
    COUNT(*) as 'Pedidos a Remover'
FROM
    pedidos
WHERE
    criado_em < DATE_SUB (NOW (), INTERVAL 30 DAY);

SELECT
    pr.nome as 'Produto',
    pr.categoria as 'Categoria',
    SUM(pi.quantidade) as 'Quantidade Vendida',
    SUM(pi.quantidade * pi.preco_unitario) as 'Faturamento'
FROM
    pedido_itens pi
    JOIN produtos pr ON pi.id_produto = pr.id
GROUP BY
    pi.id_produto
ORDER BY
    SUM(pi.quantidade) DESC
LIMIT
    10;

SELECT
    CASE DAYOFWEEK (criado_em)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Segunda'
        WHEN 3 THEN 'Terça'
        WHEN 4 THEN 'Quarta'
        WHEN 5 THEN 'Quinta'
        WHEN 6 THEN 'Sexta'
        WHEN 7 THEN 'Sábado'
    END as 'Dia da Semana',
    COUNT(*) as 'Total Pedidos',
    AVG(total) as 'Ticket Médio',
    SUM(total) as 'Faturamento'
FROM
    pedidos
WHERE
    status = 'entregue'
GROUP BY
    DAYOFWEEK (criado_em)
ORDER BY
    DAYOFWEEK (criado_em);

SELECT
    AVG(
        TIMESTAMPDIFF (
            MINUTE,
            criado_em,
            CASE
                WHEN status = 'entregue' THEN NOW ()
                ELSE NULL
            END
        )
    ) as 'Tempo Médio (minutos)'
FROM
    pedidos
WHERE
    status = 'entregue';

CREATE INDEX idx_pedidos_criado_em ON pedidos (criado_em);

SHOW INDEX
FROM
    pedidos;

SHOW INDEX
FROM
    pedido_itens;

SHOW INDEX
FROM
    produtos;

INSERT INTO
    usuarios (nome, email, senha, tipo, telefone, endereco)
VALUES
    (
        'Cliente Teste',
        'teste@cliente.com',
        '$2y$10$vQHFzXQf5tLvXPxXyLJNk.a5gXZ3LHZcFYxGCLxKmFN6uLYm5YiCS',
        'cliente',
        '(11) 99999-9999',
        'Rua Teste, 123'
    );

INSERT INTO
    pedidos (id_cliente, total, status, observacoes)
VALUES
    (
        LAST_INSERT_ID (),
        45.90,
        'pendente',
        'Pedido de teste'
    );

INSERT INTO
    pedido_itens (id_pedido, id_produto, quantidade, preco_unitario)
VALUES
    (LAST_INSERT_ID (), 1, 2, 25.90),
    (LAST_INSERT_ID (), 7, 1, 15.90);

SELECT
    *
FROM
    pedidos
ORDER BY
    id DESC
LIMIT
    1;

SELECT
    p.nome as produto,
    pi.quantidade,
    pi.preco_unitario,
    (pi.quantidade * pi.preco_unitario) as subtotal
FROM
    pedido_itens pi
    JOIN produtos p ON pi.id_produto = p.id
WHERE
    pi.id_pedido = 1;

-- Altere o ID
SELECT
    'Pedidos órfãos' as tipo,
    COUNT(*) as quantidade
FROM
    pedidos
WHERE
    id_cliente NOT IN (
        SELECT
            id
        FROM
            usuarios
    )
UNION ALL
SELECT
    'Itens órfãos',
    COUNT(*)
FROM
    pedido_itens
WHERE
    id_pedido NOT IN (
        SELECT
            id
        FROM
            pedidos
    )
UNION ALL
SELECT
    'Carrinho órfão',
    COUNT(*)
FROM
    carrinho
WHERE
    id_cliente NOT IN (
        SELECT
            id
        FROM
            usuarios
    );