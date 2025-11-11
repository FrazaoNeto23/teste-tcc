<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

// ADICIONAR AO CARRINHO
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = (int)$_POST['id_produto'];
    $quantidade = (int)$_POST['quantidade'];
    $tipo = $_POST['tipo_produto'] ?? 'normal';
    
    // Verificar se produto existe e está disponível
    $produto = $conn->query("SELECT * FROM produtos WHERE id=$id_produto AND disponivel=1")->fetch_assoc();
    
    if ($produto) {
        // Verificar se já existe no carrinho
        $existe = $conn->query("SELECT * FROM carrinho WHERE id_cliente=$id_cliente AND id_produto=$id_produto AND tipo_produto='$tipo'")->fetch_assoc();
        
        if ($existe) {
            $nova_qtd = $existe['quantidade'] + $quantidade;
            $conn->query("UPDATE carrinho SET quantidade=$nova_qtd WHERE id={$existe['id']}");
        } else {
            $conn->query("INSERT INTO carrinho (id_cliente, id_produto, tipo_produto, quantidade) VALUES ($id_cliente, $id_produto, '$tipo', $quantidade)");
        }
        redirecionar($_POST['redirect'] ?? 'carrinho.php', 'Produto adicionado ao carrinho!');
    } else {
        redirecionar('index.php', 'Produto não disponível!', 'erro');
    }
}

// ATUALIZAR QUANTIDADE
if (isset($_POST['atualizar_quantidade'])) {
    $id_carrinho = (int)$_POST['id_carrinho'];
    $quantidade = (int)$_POST['quantidade'];
    
    if ($quantidade > 0) {
        $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente");
    }
    redirecionar('carrinho.php');
}

// REMOVER ITEM
if (isset($_GET['remover'])) {
    $id_carrinho = (int)$_GET['remover'];
    $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Item removido do carrinho!');
}

// LIMPAR CARRINHO
if (isset($_GET['limpar'])) {
    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Carrinho limpo!');
}

// FINALIZAR PEDIDO
if (isset($_POST['finalizar_pedido'])) {
    $observacoes = sanitizar_texto($_POST['observacoes'] ?? '');
    
    // Buscar itens do carrinho
    $itens = $conn->query("
        SELECT c.*, p.nome, p.preco 
        FROM carrinho c 
        JOIN produtos p ON c.id_produto = p.id 
        WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
    ")->fetch_all(MYSQLI_ASSOC);
    
    if (empty($itens)) {
        redirecionar('carrinho.php', 'Carrinho vazio!', 'erro');
    }
    
    // Calcular total
    $total = 0;
    foreach ($itens as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }
    
    // Criar pedido
    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, total, observacoes) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $id_cliente, $total, $observacoes);
    $stmt->execute();
    $id_pedido = $conn->insert_id;
    
    // Adicionar itens ao pedido
    foreach ($itens as $item) {
        $stmt = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_pedido, $item['id_produto'], $item['quantidade'], $item['preco']);
        $stmt->execute();
    }
    
    // Limpar carrinho
    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");
    
    redirecionar('painel_cliente.php', 'Pedido realizado com sucesso! Acompanhe em "Meus Pedidos"');
}

// BUSCAR ITENS DO CARRINHO
$itens = $conn->query("
    SELECT c.*, p.nome, p.descricao, p.preco, p.imagem 
    FROM carrinho c 
    JOIN produtos p ON c.id_produto = p.id 
    WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
")->fetch_all(MYSQLI_ASSOC);

$total = 0;
foreach ($itens as $item) {
    $total += $item['preco'] * $item['quantidade'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</a>
            <div style="display: flex; gap: 15px;">
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Início</a>
                <a href="painel_cliente.php" class="btn btn-primary"><i class="fas fa-user"></i> Minha Conta</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="card empty">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione produtos deliciosos!</p>
                <br>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Voltar às compras</a>
            </div>
        <?php else: ?>
            <div class="card">
                <?php foreach ($itens as $item): ?>
                    <div class="cart-item">
                        <?php if ($item['imagem'] && file_exists($item['imagem'])): ?>
                            <img src="<?= $item['imagem'] ?>" alt="<?= $item['nome'] ?>">
                        <?php else: ?>
                            <div style="width:100px;height:100px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-hamburger" style="font-size:40px;color:#ccc;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-info">
                            <div class="item-nome"><?= htmlspecialchars($item['nome']) ?></div>
                            <div><?= htmlspecialchars($item['descricao']) ?></div>
                            <div class="item-preco"><?= formatar_preco($item['preco']) ?></div>
                        </div>
                        
                        <div class="item-actions">
                            <form method="POST" style="display:flex;gap:10px;align-items:center;">
                                <input type="hidden" name="id_carrinho" value="<?= $item['id'] ?>">
                                <input type="number" name="quantidade" value="<?= $item['quantidade'] ?>" min="1" class="qty-input">
                                <button type="submit" name="atualizar_quantidade" class="btn btn-primary">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </form>
                            <a href="?remover=<?= $item['id'] ?>" class="btn btn-danger" onclick="return confirm('Remover este item?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align:right;margin-top:20px;">
                    <a href="?limpar" class="btn btn-danger" onclick="return confirm('Limpar carrinho?')">
                        <i class="fas fa-trash"></i> Limpar Carrinho
                    </a>
                </div>
            </div>

            <div class="card">
                <h2>Resumo do Pedido</h2>
                <div class="resumo">
                    <div class="resumo-linha">
                        <span>Subtotal:</span>
                        <span><?= formatar_preco($total) ?></span>
                    </div>
                    <div class="resumo-linha resumo-total">
                        <span>Total:</span>
                        <span><?= formatar_preco($total) ?></span>
                    </div>
                </div>
                
                <form method="POST" style="margin-top:20px;">
                    <label style="display:block;margin-bottom:10px;font-weight:bold;">
                        <i class="fas fa-comment"></i> Observações (opcional):
                    </label>
                    <textarea name="observacoes" rows="3" placeholder="Ex: Sem cebola, ponto da carne, etc..."></textarea>
                    
                    <button type="submit" name="finalizar_pedido" class="btn btn-success" style="width:100%;margin-top:20px;font-size:18px;">
                        <i class="fas fa-check"></i> Finalizar Pedido
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
