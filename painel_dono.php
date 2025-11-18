<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// Atualizar status do pedido (fallback para não-JS)
if (isset($_POST['atualizar_status'])) {
    $id_pedido = (int) $_POST['id_pedido'];
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
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
    FROM pedidos p 
    JOIN usuarios u ON p.id_cliente = u.id
    ORDER BY p.criado_em DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Buscar itens de cada pedido
foreach ($pedidos as &$pedido) {
    $itens = $conn->query("
        SELECT pi.*, pr.nome as produto_nome 
        FROM pedido_itens pi 
        JOIN produtos pr ON pi.id_produto = pr.id 
        WHERE pi.id_pedido = {$pedido['id']}
    ")->fetch_all(MYSQLI_ASSOC);
    $pedido['itens'] = $itens;
    $pedido['total_itens'] = count($itens);
}
unset($pedido);

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
            <div class="logo">
                <i class="fas fa-hamburger"></i> BURGER HOUSE - ADMIN
                <span class="auto-update-indicator">
                    <i class="fas fa-sync-alt"></i> Auto-atualização
                </span>
            </div>
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
                <div class="stat-numero" id="stat-total-pedidos"><?= $stats['total_pedidos'] ?></div>
                <div class="stat-label">Total de Pedidos</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div class="stat-numero" id="stat-faturamento"><?= formatar_preco($stats['faturamento_total'] ?? 0) ?>
                </div>
                <div class="stat-label">Faturamento Total</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-numero" id="stat-pendentes"><?= $stats['pedidos_pendentes'] ?></div>
                <div class="stat-label">Pedidos Pendentes</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-numero" id="stat-clientes"><?= $total_clientes ?></div>
                <div class="stat-label">Clientes Cadastrados</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-hamburger"></i>
                <div class="stat-numero" id="stat-produtos"><?= $total_produtos ?></div>
                <div class="stat-label">Produtos Disponíveis</div>
            </div>
        </div>

        <!-- PEDIDOS RECENTES -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Pedidos Recentes</h2>

            <div id="pedidos-container">
                <?php if (empty($pedidos)): ?>
                    <p style="text-align:center;color:#999;padding:40px;">Nenhum pedido ainda</p>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="pedido" id="pedido-<?= $pedido['id'] ?>">
                            <div class="pedido-header">
                                <div style="flex:1;">
                                    <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                    <div class="pedido-info">
                                        <strong><i class="fas fa-user"></i> Cliente:</strong>
                                        <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                        <?php if ($pedido['telefone']): ?>
                                            <strong><i class="fas fa-phone"></i> Telefone:</strong>
                                            <?= htmlspecialchars($pedido['telefone']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($pedido['endereco']): ?>
                                            <strong><i class="fas fa-map-marker-alt"></i> Endereço:</strong>
                                            <?= htmlspecialchars($pedido['endereco']) ?><br>
                                        <?php endif; ?>
                                        <strong><i class="fas fa-calendar"></i> Data:</strong>
                                        <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?><br>
                                        <?php if ($pedido['observacoes']): ?>
                                            <strong><i class="fas fa-comment"></i> Obs:</strong>
                                            <?= htmlspecialchars($pedido['observacoes']) ?><br>
                                        <?php endif; ?>
                                    </div>

                                    <!-- ITENS DO PEDIDO -->
                                    <div class="pedido-itens">
                                        <strong><i class="fas fa-utensils"></i> Itens do Pedido:</strong>
                                        <ul class="lista-itens">
                                            <?php foreach ($pedido['itens'] as $item): ?>
                                                <li>
                                                    <span class="item-qty"><?= $item['quantidade'] ?>x</span>
                                                    <span class="item-name"><?= htmlspecialchars($item['produto_nome']) ?></span>
                                                    <span
                                                        class="item-price"><?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <strong style="color:#51cf66;font-size:20px;">Total:
                                        <?= formatar_preco($pedido['total']) ?></strong>
                                </div>

                                <div class="status-form">
                                    <select id="status-<?= $pedido['id'] ?>"
                                        onchange="atualizarStatus(<?= $pedido['id'] ?>, this.value)">
                                        <option value="pendente" <?= $pedido['status'] == 'pendente' ? 'selected' : '' ?>>Pendente
                                        </option>
                                        <option value="preparando" <?= $pedido['status'] == 'preparando' ? 'selected' : '' ?>>
                                            Preparando</option>
                                        <option value="pronto" <?= $pedido['status'] == 'pronto' ? 'selected' : '' ?>>Pronto</option>
                                        <option value="entregue" <?= $pedido['status'] == 'entregue' ? 'selected' : '' ?>>Entregue
                                        </option>
                                        <option value="cancelado" <?= $pedido['status'] == 'cancelado' ? 'selected' : '' ?>>Cancelado
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Cores dos status
        const statusCores = {
            'pendente': '#ffc107',
            'preparando': '#17a2b8',
            'pronto': '#28a745',
            'entregue': '#6c757d',
            'cancelado': '#dc3545'
        };

        // Mostrar toast de notificação
        function mostrarToast(mensagem, tipo = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${tipo}`;
            toast.innerHTML = `<i class="fas fa-${tipo === 'success' ? 'check' : 'exclamation'}-circle"></i> ${mensagem}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Atualizar status via AJAX
        async function atualizarStatus(idPedido, status) {
            try {
                const formData = new FormData();
                formData.append('action', 'atualizar_status');
                formData.append('id_pedido', idPedido);
                formData.append('status', status);

                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.sucesso) {
                    mostrarToast('Status atualizado!');
                    atualizarStats();
                } else {
                    mostrarToast('Erro ao atualizar status', 'error');
                }
            } catch (error) {
                mostrarToast('Erro de conexão', 'error');
            }
        }

        // Formatar preço
        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Formatar data
        function formatarData(dataStr) {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        // Atualizar estatísticas
        async function atualizarStats() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_stats_dono');
                const data = await response.json();

                if (data.stats) {
                    document.getElementById('stat-total-pedidos').textContent = data.stats.total_pedidos || 0;
                    document.getElementById('stat-faturamento').textContent = formatarPreco(data.stats.faturamento_total || 0);
                    document.getElementById('stat-pendentes').textContent = data.stats.pedidos_pendentes || 0;
                    document.getElementById('stat-clientes').textContent = data.total_clientes || 0;
                    document.getElementById('stat-produtos').textContent = data.total_produtos || 0;
                }
            } catch (error) {
                console.error('Erro ao atualizar stats:', error);
            }
        }

        // IDs dos pedidos atuais para detectar novos
        let pedidosAtuais = new Set([<?= implode(',', array_column($pedidos, 'id')) ?>]);

        // Atualizar lista de pedidos
        async function atualizarPedidos() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_pedidos_dono');
                const data = await response.json();

                if (data.pedidos && data.pedidos.length > 0) {
                    const container = document.getElementById('pedidos-container');
                    let html = '';
                    let novosPedidos = false;

                    data.pedidos.forEach(pedido => {
                        const isNovo = !pedidosAtuais.has(pedido.id);
                        if (isNovo) {
                            novosPedidos = true;
                            pedidosAtuais.add(pedido.id);
                        }

                        // Gerar lista de itens
                        let itensHtml = '';
                        if (pedido.itens && pedido.itens.length > 0) {
                            pedido.itens.forEach(item => {
                                const subtotal = item.preco_unitario * item.quantidade;
                                itensHtml += `
                                    <li>
                                        <span class="item-qty">${item.quantidade}x</span>
                                        <span class="item-name">${item.produto_nome}</span>
                                        <span class="item-price">${formatarPreco(subtotal)}</span>
                                    </li>
                                `;
                            });
                        }

                        html += `
                            <div class="pedido ${isNovo ? 'pedido-novo' : ''}" id="pedido-${pedido.id}">
                                <div class="pedido-header">
                                    <div style="flex:1;">
                                        <div class="pedido-numero">Pedido #${pedido.id}</div>
                                        <div class="pedido-info">
                                            <strong><i class="fas fa-user"></i> Cliente:</strong> ${pedido.cliente_nome}<br>
                                            ${pedido.telefone ? `<strong><i class="fas fa-phone"></i> Telefone:</strong> ${pedido.telefone}<br>` : ''}
                                            ${pedido.endereco ? `<strong><i class="fas fa-map-marker-alt"></i> Endereço:</strong> ${pedido.endereco}<br>` : ''}
                                            <strong><i class="fas fa-calendar"></i> Data:</strong> ${formatarData(pedido.criado_em)}<br>
                                            ${pedido.observacoes ? `<strong><i class="fas fa-comment"></i> Obs:</strong> ${pedido.observacoes}<br>` : ''}
                                        </div>
                                        
                                        <div class="pedido-itens">
                                            <strong><i class="fas fa-utensils"></i> Itens do Pedido:</strong>
                                            <ul class="lista-itens">
                                                ${itensHtml}
                                            </ul>
                                        </div>
                                        
                                        <strong style="color:#51cf66;font-size:20px;">Total: ${formatarPreco(pedido.total)}</strong>
                                    </div>
                                    
                                    <div class="status-form">
                                        <select id="status-${pedido.id}" onchange="atualizarStatus(${pedido.id}, this.value)">
                                            <option value="pendente" ${pedido.status === 'pendente' ? 'selected' : ''}>Pendente</option>
                                            <option value="preparando" ${pedido.status === 'preparando' ? 'selected' : ''}>Preparando</option>
                                            <option value="pronto" ${pedido.status === 'pronto' ? 'selected' : ''}>Pronto</option>
                                            <option value="entregue" ${pedido.status === 'entregue' ? 'selected' : ''}>Entregue</option>
                                            <option value="cancelado" ${pedido.status === 'cancelado' ? 'selected' : ''}>Cancelado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    container.innerHTML = html;

                    if (novosPedidos) {
                        mostrarToast('Novo pedido recebido!');
                        if ('Notification' in window && Notification.permission === 'granted') {
                            new Notification('Burger House', {
                                body: 'Novo pedido recebido!',
                                icon: '🍔'
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar pedidos:', error);
            }
        }

        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Atualizar automaticamente a cada 5 segundos
        setInterval(() => {
            atualizarPedidos();
            atualizarStats();
        }, 5000);

        // Primeira atualização
        setTimeout(() => {
            atualizarPedidos();
            atualizarStats();
        }, 1000);
    </script>
</body>

</html>