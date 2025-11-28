<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

// ADICIONAR AO CARRINHO
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = (int) $_POST['id_produto'];
    $quantidade = (int) $_POST['quantidade'];
    $tipo = $_POST['tipo_produto'] ?? 'normal';

    $produto = $conn->query("SELECT * FROM produtos WHERE id=$id_produto AND disponivel=1")->fetch_assoc();

    if ($produto) {
        $existe = $conn->query("SELECT * FROM carrinho WHERE id_cliente=$id_cliente AND id_produto=$id_produto AND tipo_produto='$tipo'")->fetch_assoc();

        if ($existe) {
            $nova_qtd = $existe['quantidade'] + $quantidade;
            $conn->query("UPDATE carrinho SET quantidade=$nova_qtd WHERE id={$existe['id']}");
        } else {
            $conn->query("INSERT INTO carrinho (id_cliente, id_produto, tipo_produto, quantidade) VALUES ($id_cliente, $id_produto, '$tipo', $quantidade)");
        }

        // Se for AJAX, não redirecionar
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['sucesso' => true]);
            exit;
        }

        redirecionar($_POST['redirect'] ?? 'carrinho.php', 'Produto adicionado!');
    } else {
        redirecionar('index.php', 'Produto não disponível!', 'erro');
    }
}

// ATUALIZAR QUANTIDADE VIA AJAX
if (isset($_POST['ajax_atualizar_quantidade'])) {
    header('Content-Type: application/json');

    $id_carrinho = (int) $_POST['id_carrinho'];
    $quantidade = (int) $_POST['quantidade'];

    if ($quantidade > 0) {
        $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente");

        // Buscar preço do item
        $item = $conn->query("
            SELECT c.quantidade, p.preco 
            FROM carrinho c 
            JOIN produtos p ON c.id_produto = p.id 
            WHERE c.id = $id_carrinho
        ")->fetch_assoc();

        // Calcular novo total geral
        $itens = $conn->query("
            SELECT c.quantidade, p.preco 
            FROM carrinho c 
            JOIN produtos p ON c.id_produto = p.id 
            WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
        ")->fetch_all(MYSQLI_ASSOC);

        $total = 0;
        $total_itens = 0;
        foreach ($itens as $i) {
            $total += $i['preco'] * $i['quantidade'];
            $total_itens += $i['quantidade'];
        }

        echo json_encode([
            'sucesso' => true,
            'subtotal_item' => $item['preco'] * $quantidade,
            'subtotal_item_formatado' => 'R$ ' . number_format($item['preco'] * $quantidade, 2, ',', '.'),
            'total' => $total,
            'total_formatado' => 'R$ ' . number_format($total, 2, ',', '.'),
            'total_itens' => $total_itens
        ]);
    } else {
        // Remover item se quantidade for 0
        $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");

        echo json_encode([
            'sucesso' => true,
            'removido' => true
        ]);
    }
    exit;
}

// ATUALIZAR QUANTIDADE (form tradicional)
if (isset($_POST['atualizar_quantidade'])) {
    $id_carrinho = (int) $_POST['id_carrinho'];
    $quantidade = (int) $_POST['quantidade'];

    if ($quantidade > 0) {
        $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente");
    } else {
        $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");
    }
    redirecionar('carrinho.php');
}

// REMOVER ITEM
if (isset($_GET['remover'])) {
    $id_carrinho = (int) $_GET['remover'];
    $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Item removido!');
}

// LIMPAR CARRINHO
if (isset($_GET['limpar'])) {
    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");
    redirecionar('carrinho.php', 'Carrinho limpo!');
}

// FINALIZAR PEDIDO
if (isset($_POST['finalizar_pedido'])) {
    $observacoes = sanitizar_texto($_POST['observacoes'] ?? '');
    $tipo_retirada = $_POST['tipo_retirada'] ?? 'balcao';
    $numero_mesa = '';

    // Se for retirada na mesa, validar número da mesa
    if ($tipo_retirada == 'mesa') {
        $numero_mesa = sanitizar_texto($_POST['numero_mesa'] ?? '');
        if (empty($numero_mesa)) {
            redirecionar('carrinho.php', 'Por favor, informe o número da mesa!', 'erro');
        }
    }

    $itens = $conn->query("
        SELECT c.*, p.nome, p.preco 
        FROM carrinho c 
        JOIN produtos p ON c.id_produto = p.id 
        WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
    ")->fetch_all(MYSQLI_ASSOC);

    if (empty($itens)) {
        redirecionar('carrinho.php', 'Carrinho vazio!', 'erro');
    }

    $total = 0;
    foreach ($itens as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }

    // Criar pedido com os novos campos
    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, total, observacoes, numero_mesa, tipo_retirada) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $id_cliente, $total, $observacoes, $numero_mesa, $tipo_retirada);
    $stmt->execute();
    $id_pedido = $conn->insert_id;

    // Adicionar itens
    foreach ($itens as $item) {
        $stmt = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_pedido, $item['id_produto'], $item['quantidade'], $item['preco']);
        $stmt->execute();
    }

    // Limpar carrinho
    $conn->query("DELETE FROM carrinho WHERE id_cliente=$id_cliente");

    $msg_retirada = $tipo_retirada == 'mesa' ? "Entregaremos na mesa $numero_mesa!" : "Retire no balcão quando estiver pronto!";
    redirecionar('painel_cliente.php', "Pedido #$id_pedido realizado com sucesso! $msg_retirada");
}

// BUSCAR ITENS DO CARRINHO
$itens = $conn->query("
    SELECT c.*, p.nome, p.descricao, p.preco, p.imagem, p.categoria
    FROM carrinho c 
    JOIN produtos p ON c.id_produto = p.id 
    WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
")->fetch_all(MYSQLI_ASSOC);

$total = 0;
$total_itens = 0;
foreach ($itens as $item) {
    $total += $item['preco'] * $item['quantidade'];
    $total_itens += $item['quantidade'];
}

// Buscar dados do cliente para endereço
$cliente = $conn->query("SELECT * FROM usuarios WHERE id=$id_cliente")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
        }

        .cart-item {
            display: flex;
            gap: 16px;
            padding: 20px;
            background: var(--bg);
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            align-items: center;
            transition: var(--transition);
        }

        .cart-item:hover {
            box-shadow: var(--shadow-md);
        }

        .cart-item.updating {
            opacity: 0.6;
            pointer-events: none;
        }

        .cart-item.removed {
            animation: slideOut 0.3s ease forwards;
        }

        @keyframes slideOut {
            to {
                opacity: 0;
                transform: translateX(-100%);
                height: 0;
                padding: 0;
                margin: 0;
            }
        }

        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--radius-md);
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-nome {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .cart-item-categoria {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .cart-item-preco {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .qty-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--white);
            padding: 6px;
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-sm);
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--light-gray);
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
        }

        .qty-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .qty-btn:active {
            transform: scale(0.95);
        }

        .qty-btn.minus:hover {
            background: var(--danger);
        }

        .qty-value {
            width: 50px;
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            color: var(--dark);
        }

        .qty-value.changed {
            animation: pop 0.3s ease;
        }

        @keyframes pop {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.3);
                color: var(--primary);
            }

            100% {
                transform: scale(1);
            }
        }

        .remove-btn {
            width: 44px;
            height: 44px;
            border: none;
            background: #fee2e2;
            color: var(--danger);
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .remove-btn:hover {
            background: var(--danger);
            color: white;
            transform: scale(1.1);
        }

        .subtotal-value {
            font-weight: 800;
            font-size: 18px;
            color: var(--success);
            min-width: 100px;
            text-align: right;
            transition: var(--transition);
        }

        .subtotal-value.changed {
            animation: pop 0.3s ease;
        }

        .checkout-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 100px;
        }

        .checkout-card h3 {
            font-family: 'Space Grotesk', sans-serif;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkout-line {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .checkout-line:last-of-type {
            border-bottom: none;
        }

        .checkout-total {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            padding-top: 16px;
            margin-top: 16px;
            border-top: 2px dashed var(--light-gray);
        }

        /* Opções de Retirada */
        .retirada-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .retirada-option {
            position: relative;
        }

        .retirada-option input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .retirada-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px 16px;
            background: var(--bg);
            border: 3px solid var(--light-gray);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .retirada-option label i {
            font-size: 28px;
            color: var(--gray);
            transition: var(--transition);
        }

        .retirada-option label span {
            font-weight: 600;
            color: var(--dark);
        }

        .retirada-option label small {
            font-size: 11px;
            color: var(--gray);
        }

        .retirada-option input:checked+label {
            border-color: var(--primary);
            background: rgba(255, 107, 53, 0.05);
        }

        .retirada-option input:checked+label i {
            color: var(--primary);
        }

        .retirada-option label:hover {
            border-color: var(--primary-light);
        }

        /* Campo de Mesa */
        .mesa-field {
            display: none;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }

        .mesa-field.visible {
            display: block;
        }

        .mesa-input-wrapper {
            display: flex;
            gap: 8px;
        }

        .mesa-input-wrapper input {
            flex: 1;
            font-size: 18px;
            font-weight: 700;
            text-align: center;
        }

        .mesa-input-wrapper .mesa-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-fire);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        /* Toast de atualização */
        .update-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius-full);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-wrap: wrap;
            }

            .cart-item img {
                width: 80px;
                height: 80px;
            }

            .cart-item-info {
                flex-basis: calc(100% - 100px);
            }

            .qty-controls,
            .subtotal-value,
            .remove-btn {
                margin-top: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</a>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Continuar Comprando</a>
                <a href="painel_cliente.php" class="btn btn-primary"><i class="fas fa-user"></i> Minha Conta</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card" style="background: var(--gradient-fire); color: white;">
            <h1 style="color: white; margin-bottom: 8px;">
                <i class="fas fa-shopping-cart"></i> Meu Carrinho
            </h1>
            <p style="opacity: 0.9;" id="header-itens"><?= $total_itens ?> item(ns) no carrinho</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['erro'] ?></div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="card empty">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione deliciosos hambúrgueres ao seu pedido!</p>
                <br>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-utensils"></i> Ver Cardápio
                </a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <!-- Itens do Carrinho -->
                <div id="cart-items-container">
                    <?php foreach ($itens as $item): ?>
                        <div class="cart-item" id="item-<?= $item['id'] ?>" data-id="<?= $item['id'] ?>"
                            data-preco="<?= $item['preco'] ?>">
                            <?php if ($item['imagem'] && file_exists($item['imagem'])): ?>
                                <img src="<?= $item['imagem'] ?>" alt="<?= htmlspecialchars($item['nome']) ?>">
                            <?php else: ?>
                                <div
                                    style="width:100px;height:100px;background:var(--light-gray);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-hamburger" style="font-size:32px;color:#ccc;"></i>
                                </div>
                            <?php endif; ?>

                            <div class="cart-item-info">
                                <div class="cart-item-categoria">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($item['categoria']) ?>
                                </div>
                                <div class="cart-item-nome"><?= htmlspecialchars($item['nome']) ?></div>
                                <div class="cart-item-preco">
                                    <?= formatar_preco($item['preco']) ?>
                                    <span style="font-size: 14px; color: var(--gray); font-weight: 400;">/ unidade</span>
                                </div>
                            </div>

                            <div class="qty-controls">
                                <button type="button" class="qty-btn minus" onclick="alterarQtd(<?= $item['id'] ?>, -1, this)"
                                    title="Diminuir">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="qty-value" id="qty-<?= $item['id'] ?>"><?= $item['quantidade'] ?></span>
                                <button type="button" class="qty-btn plus" onclick="alterarQtd(<?= $item['id'] ?>, 1, this)"
                                    title="Aumentar">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <div class="subtotal-value" id="subtotal-<?= $item['id'] ?>">
                                <?= formatar_preco($item['preco'] * $item['quantidade']) ?>
                            </div>

                            <button type="button" class="remove-btn" onclick="removerItem(<?= $item['id'] ?>)"
                                title="Remover item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>

                    <div
                        style="display: flex; justify-content: space-between; margin-top: 16px; flex-wrap: wrap; gap: 12px;">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Continuar Comprando
                        </a>
                        <a href="?limpar" class="btn btn-danger" onclick="return confirm('Limpar todo o carrinho?')">
                            <i class="fas fa-trash"></i> Limpar Carrinho
                        </a>
                    </div>
                </div>

                <!-- Resumo e Checkout -->
                <div class="checkout-card">
                    <h3><i class="fas fa-receipt"></i> Finalizar Pedido</h3>

                    <form method="POST" id="checkout-form">
                        <!-- Tipo de Retirada -->
                        <label style="display: block; margin-bottom: 12px; font-weight: 600; color: var(--dark);">
                            <i class="fas fa-hand-holding"></i> Como deseja receber?
                        </label>

                        <div class="retirada-options">
                            <div class="retirada-option">
                                <input type="radio" name="tipo_retirada" id="retirada-balcao" value="balcao" checked>
                                <label for="retirada-balcao">
                                    <i class="fas fa-store"></i>
                                    <span>No Balcão</span>
                                    <small>Chamaremos pelo número</small>
                                </label>
                            </div>

                            <div class="retirada-option">
                                <input type="radio" name="tipo_retirada" id="retirada-mesa" value="mesa">
                                <label for="retirada-mesa">
                                    <i class="fas fa-chair"></i>
                                    <span>Na Mesa</span>
                                    <small>Entregamos pra você</small>
                                </label>
                            </div>
                        </div>

                        <!-- Campo Número da Mesa -->
                        <div class="mesa-field" id="mesa-field">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                                <i class="fas fa-hashtag"></i> Número da Mesa *
                            </label>
                            <div class="mesa-input-wrapper">
                                <div class="mesa-icon">
                                    <i class="fas fa-chair"></i>
                                </div>
                                <input type="text" name="numero_mesa" id="numero_mesa" placeholder="Ex: 5" maxlength="10"
                                    pattern="[0-9A-Za-z]+" title="Informe o número ou código da mesa">
                            </div>
                        </div>

                        <!-- Resumo de Valores -->
                        <div
                            style="background: var(--bg); padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                            <div class="checkout-line">
                                <span>Subtotal (<span id="qtd-itens"><?= $total_itens ?></span> itens)</span>
                                <span id="subtotal-geral"><?= formatar_preco($total) ?></span>
                            </div>
                            <div class="checkout-line">
                                <span>Taxa de serviço</span>
                                <span style="color: var(--success);">Grátis</span>
                            </div>

                            <div class="checkout-line checkout-total">
                                <span>Total</span>
                                <span id="total-geral"><?= formatar_preco($total) ?></span>
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Observações (opcional)</label>
                            <textarea name="observacoes" rows="3"
                                placeholder="Ex: Sem cebola, ponto da carne bem passado, alergia a amendoim..."></textarea>
                        </div>

                        <button type="submit" name="finalizar_pedido" class="btn btn-success btn-lg" style="width: 100%;">
                            <i class="fas fa-check"></i> Confirmar Pedido
                        </button>
                    </form>

                    <p style="text-align: center; margin-top: 16px; font-size: 13px; color: var(--gray);">
                        <i class="fas fa-credit-card"></i> Pagamento na retirada
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Dados dos itens para cálculo local
        let itens = <?= json_encode(array_map(function ($i) {
            return ['id' => $i['id'], 'preco' => floatval($i['preco']), 'quantidade' => intval($i['quantidade'])];
        }, $itens)) ?>;

        // Controle do campo de mesa
        const retiradaMesa = document.getElementById('retirada-mesa');
        const retiradaBalcao = document.getElementById('retirada-balcao');
        const mesaField = document.getElementById('mesa-field');
        const numeroMesaInput = document.getElementById('numero_mesa');

        function toggleMesaField() {
            if (retiradaMesa && retiradaMesa.checked) {
                mesaField.classList.add('visible');
                numeroMesaInput.required = true;
            } else {
                mesaField.classList.remove('visible');
                numeroMesaInput.required = false;
                numeroMesaInput.value = '';
            }
        }

        if (retiradaMesa) {
            retiradaMesa.addEventListener('change', toggleMesaField);
            retiradaBalcao.addEventListener('change', toggleMesaField);
        }

        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',');
        }

        function mostrarToast(mensagem) {
            // Remover toasts existentes
            document.querySelectorAll('.update-toast').forEach(t => t.remove());

            const toast = document.createElement('div');
            toast.className = 'update-toast';
            toast.innerHTML = `<i class="fas fa-check-circle"></i> ${mensagem}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-50%) translateY(50px)';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }

        async function alterarQtd(idCarrinho, delta, btn) {
            const item = itens.find(i => i.id == idCarrinho);
            if (!item) return;

            const novaQtd = item.quantidade + delta;
            const cartItem = document.getElementById('item-' + idCarrinho);
            const qtyValue = document.getElementById('qty-' + idCarrinho);
            const subtotalEl = document.getElementById('subtotal-' + idCarrinho);

            // Se for diminuir para 0, confirmar remoção
            if (novaQtd <= 0) {
                if (confirm('Remover este item do carrinho?')) {
                    removerItem(idCarrinho);
                }
                return;
            }

            // Feedback visual imediato
            cartItem.classList.add('updating');
            item.quantidade = novaQtd;

            // Atualizar visual com animação
            qtyValue.textContent = novaQtd;
            qtyValue.classList.add('changed');
            setTimeout(() => qtyValue.classList.remove('changed'), 300);

            const novoSubtotal = item.preco * novaQtd;
            subtotalEl.textContent = formatarPreco(novoSubtotal);
            subtotalEl.classList.add('changed');
            setTimeout(() => subtotalEl.classList.remove('changed'), 300);

            atualizarTotalGeral();

            // Enviar para o servidor
            try {
                const formData = new FormData();
                formData.append('ajax_atualizar_quantidade', '1');
                formData.append('id_carrinho', idCarrinho);
                formData.append('quantidade', novaQtd);

                const response = await fetch('carrinho.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.sucesso) {
                    mostrarToast('Quantidade atualizada!');
                }
            } catch (error) {
                console.error('Erro:', error);
            } finally {
                cartItem.classList.remove('updating');
            }
        }

        function atualizarTotalGeral() {
            let total = 0;
            let qtdTotal = 0;

            itens.forEach(item => {
                total += item.preco * item.quantidade;
                qtdTotal += item.quantidade;
            });

            document.getElementById('subtotal-geral').textContent = formatarPreco(total);
            document.getElementById('total-geral').textContent = formatarPreco(total);
            document.getElementById('qtd-itens').textContent = qtdTotal;
            document.getElementById('header-itens').textContent = qtdTotal + ' item(ns) no carrinho';
        }

        async function removerItem(idCarrinho) {
            const cartItem = document.getElementById('item-' + idCarrinho);

            // Animação de remoção
            cartItem.classList.add('removed');

            // Remover do array local
            itens = itens.filter(i => i.id != idCarrinho);

            // Atualizar totais
            atualizarTotalGeral();

            // Aguardar animação e redirecionar
            setTimeout(() => {
                window.location.href = '?remover=' + idCarrinho;
            }, 300);
        }

        // Validação do formulário
        document.getElementById('checkout-form')?.addEventListener('submit', function (e) {
            if (retiradaMesa.checked && !numeroMesaInput.value.trim()) {
                e.preventDefault();
                numeroMesaInput.focus();
                alert('Por favor, informe o número da sua mesa!');
            }
        });
    </script>
</body>

</html>