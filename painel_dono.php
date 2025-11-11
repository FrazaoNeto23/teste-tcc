<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// Atualizar status do pedido
if (isset($_POST['atualizar_status'])) {
    $id_pedido = (int)$_POST['id_pedido'];
    $status = $_POST['status'];
    $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");
    redirecionar('painel_dono.php', 'Status atualizado!');
}

// Buscar estatísticas
$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT id) as total_pedidos,
        SUM(total) as faturamento_total,
        SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes
    FROM pedidos
")->fetch_assoc();

$total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
$total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];

// Buscar pedidos recentes
$pedidos = $conn->query("
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco,
           COUNT(pi.id) as total_itens
    FROM pedidos p 
    JOIN usuarios u ON p.id_cliente = u.id
    LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido
    GROUP BY p.id
    ORDER BY p.criado_em DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$status_cores = [
    'pendente' => '#ffc107',
    'preparando' => '#17a2b8',
    'pronto' => '#28a745',
    'entregue' => '#6c757d',
    'cancelado' => '#dc3545'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE - ADMIN</div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="gerenciar_produtos.php" class="btn btn-success"><i class="fas fa-boxes"></i> Produtos</a>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Ver Site</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-tachometer-alt"></i> Painel Administrativo</h1>
            <p>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario']) ?>!</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <!-- ESTATÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <div class="stat-numero"><?= $stats['total_pedidos'] ?></div>
                <div class="stat-label">Total de Pedidos</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div class="stat-numero"><?= formatar_preco($stats['faturamento_total'] ?? 0) ?></div>
                <div class="stat-label">Faturamento Total</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-numero"><?= $stats['pedidos_pendentes'] ?></div>
                <div class="stat-label">Pedidos Pendentes</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-numero"><?= $total_clientes ?></div>
                <div class="stat-label">Clientes Cadastrados</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-hamburger"></i>
                <div class="stat-numero"><?= $total_produtos ?></div>
                <div class="stat-label">Produtos Disponíveis</div>
            </div>
        </div>

        <!-- PEDIDOS RECENTES -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Pedidos Recentes</h2>
            
            <?php if (empty($pedidos)): ?>
                <p style="text-align:center;color:#999;padding:40px;">Nenhum pedido ainda</p>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido">
                        <div class="pedido-header">
                            <div style="flex:1;">
                                <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                <div class="pedido-info">
                                    <strong><i class="fas fa-user"></i> Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                    <?php if ($pedido['telefone']): ?>
                                        <strong><i class="fas fa-phone"></i> Telefone:</strong> <?= htmlspecialchars($pedido['telefone']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($pedido['endereco']): ?>
                                        <strong><i class="fas fa-map-marker-alt"></i> Endereço:</strong> <?= htmlspecialchars($pedido['endereco']) ?><br>
                                    <?php endif; ?>
                                    <strong><i class="fas fa-calendar"></i> Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?><br>
                                    <strong><i class="fas fa-box"></i> Itens:</strong> <?= $pedido['total_itens'] ?><br>
                                    <?php if ($pedido['observacoes']): ?>
                                        <strong><i class="fas fa-comment"></i> Obs:</strong> <?= htmlspecialchars($pedido['observacoes']) ?><br>
                                    <?php endif; ?>
                                    <strong style="color:#51cf66;font-size:20px;">Total: <?= formatar_preco($pedido['total']) ?></strong>
                                </div>
                            </div>
                            
                            <form method="POST" class="status-form">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                                <select name="status">
                                    <option value="pendente" <?= $pedido['status']=='pendente'?'selected':'' ?>>Pendente</option>
                                    <option value="preparando" <?= $pedido['status']=='preparando'?'selected':'' ?>>Preparando</option>
                                    <option value="pronto" <?= $pedido['status']=='pronto'?'selected':'' ?>>Pronto</option>
                                    <option value="entregue" <?= $pedido['status']=='entregue'?'selected':'' ?>>Entregue</option>
                                    <option value="cancelado" <?= $pedido['status']=='cancelado'?'selected':'' ?>>Cancelado</option>
                                </select>
                                <button type="submit" name="atualizar_status" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Atualizar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
