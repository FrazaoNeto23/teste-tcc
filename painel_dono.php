<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// ============================================
// LIMPEZA AUTOMÁTICA DE PEDIDOS DO DIA ANTERIOR
// ============================================
// Verificar se já limpou hoje
$ultima_limpeza = $_SESSION['ultima_limpeza'] ?? null;
$hoje = date('Y-m-d');

if ($ultima_limpeza !== $hoje) {
    // Executar limpeza automática
    $data_corte = date('Y-m-d'); // Pedidos anteriores a hoje
    
    // Contar pedidos antigos
    $pedidos_antigos = $conn->query("
        SELECT COUNT(*) as total FROM pedidos 
        WHERE DATE(criado_em) < '$data_corte'
    ")->fetch_assoc()['total'];
    
    if ($pedidos_antigos > 0) {
        // Buscar IDs dos pedidos antigos
        $ids_result = $conn->query("
            SELECT id FROM pedidos 
            WHERE DATE(criado_em) < '$data_corte'
        ")->fetch_all(MYSQLI_ASSOC);
        
        $ids = array_column($ids_result, 'id');
        $ids_str = implode(',', $ids);
        
        // Deletar itens e pedidos
        $conn->query("DELETE FROM pedido_itens WHERE id_pedido IN ($ids_str)");
        $conn->query("DELETE FROM pedidos WHERE id IN ($ids_str)");
        
        // Limpar carrinhos antigos também
        $conn->query("DELETE FROM carrinho WHERE DATE(adicionado_em) < '$data_corte'");
        
        $_SESSION['limpeza_info'] = "🧹 Limpeza automática: $pedidos_antigos pedidos do dia anterior foram removidos.";
    }
    
    // Marcar que já limpou hoje
    $_SESSION['ultima_limpeza'] = $hoje;
}

// Atualizar status do pedido (fallback para não-JS)
if (isset($_POST['atualizar_status'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $status = $_POST['status'];
    $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");
    redirecionar('painel_dono.php', 'Status atualizado!');
}

// Buscar estatísticas gerais
$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT id) as total_pedidos,
        SUM(total) as faturamento_total,
        SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
        SUM(CASE WHEN status='preparando' THEN 1 ELSE 0 END) as pedidos_preparando
    FROM pedidos
")->fetch_assoc();

// Vendas de hoje
$vendas_hoje = $conn->query("
    SELECT COUNT(*) as qtd, COALESCE(SUM(total), 0) as valor 
    FROM pedidos 
    WHERE DATE(criado_em) = CURDATE() AND status != 'cancelado'
")->fetch_assoc();

// Vendas da semana
$vendas_semana = $conn->query("
    SELECT COALESCE(SUM(total), 0) as valor 
    FROM pedidos 
    WHERE YEARWEEK(criado_em) = YEARWEEK(CURDATE()) AND status != 'cancelado'
")->fetch_assoc();

$total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
$total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];

// Produtos mais vendidos
$mais_vendidos = $conn->query("
    SELECT p.nome, SUM(pi.quantidade) as qtd 
    FROM pedido_itens pi 
    JOIN produtos p ON pi.id_produto = p.id 
    GROUP BY pi.id_produto 
    ORDER BY qtd DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Buscar pedidos recentes
$pedidos = $conn->query("
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
    FROM pedidos p 
    JOIN usuarios u ON p.id_cliente = u.id
    ORDER BY 
        CASE p.status 
            WHEN 'pendente' THEN 1 
            WHEN 'preparando' THEN 2 
            WHEN 'pronto' THEN 3 
            ELSE 4 
        END,
        p.criado_em DESC 
    LIMIT 30
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

$status_config = [
    'pendente' => ['cor' => '#f39c12', 'icone' => 'clock', 'label' => 'PENDENTE'],
    'preparando' => ['cor' => '#3498db', 'icone' => 'fire', 'label' => 'PREPARANDO'],
    'pronto' => ['cor' => '#2ecc71', 'icone' => 'check-circle', 'label' => 'PRONTO'],
    'entregue' => ['cor' => '#95a5a6', 'icone' => 'check-double', 'label' => 'ENTREGUE'],
    'cancelado' => ['cor' => '#e74c3c', 'icone' => 'times-circle', 'label' => 'CANCELADO']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos do painel */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .chart-container {
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .chart-container h3 {
            font-family: 'Space Grotesk', sans-serif;
            margin-bottom: 16px;
            color: var(--dark);
        }
        
        .top-produtos {
            list-style: none;
            padding: 0;
        }
        
        .top-produtos li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .top-produtos li:last-child {
            border-bottom: none;
        }
        
        .top-produtos .rank {
            width: 28px;
            height: 28px;
            background: var(--gradient-fire);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            margin-right: 12px;
        }
        
        .top-produtos .nome {
            flex: 1;
            font-weight: 500;
        }
        
        .top-produtos .qtd {
            background: var(--bg);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 13px;
        }
        
        .sound-toggle {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 1000;
        }
        
        .sound-toggle button {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: var(--dark);
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }
        
        .sound-toggle button:hover {
            transform: scale(1.1);
        }
        
        .sound-toggle button.muted {
            background: var(--danger);
        }
        
        /* Info de retirada */
        .retirada-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }
        
        .retirada-badge.mesa {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .retirada-badge.balcao {
            background: #fef3c7;
            color: #92400e;
        }
        
        .mesa-numero {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Estilos de Impressão */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-area, .print-area * {
                visibility: visible;
            }
            
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Área de impressão do pedido */
        .pedido-print {
            display: none;
        }
    </style>
</head>
<body>
    <div class="header no-print">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-hamburger"></i> BURGER HOUSE
                <span class="auto-update-indicator">
                    <i class="fas fa-sync-alt"></i> Tempo real
                </span>
            </div>
            <div class="nav-buttons">
                <a href="gerenciar_produtos.php" class="btn btn-success"><i class="fas fa-boxes"></i> Produtos</a>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Ver Loja</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Bem-vindo -->
        <div class="card no-print" style="background: var(--gradient-fire); color: white;">
            <h1 style="color: white; margin-bottom: 8px;">
                <i class="fas fa-tachometer-alt"></i> Painel Administrativo
            </h1>
            <p style="opacity: 0.9;">Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>! Gerencie sua hamburgueria em tempo real.</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['limpeza_info'])): ?>
            <div class="alert alert-info no-print" style="background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); border-left-color: #0ea5e9;">
                <i class="fas fa-broom"></i> <?= $_SESSION['limpeza_info'] ?>
            </div>
            <?php unset($_SESSION['limpeza_info']); ?>
        <?php endif; ?>

        <!-- Alerta de Pendentes -->
        <?php if ($stats['pedidos_pendentes'] > 0): ?>
        <div class="pedidos-pendentes-alert no-print" id="alerta-pendentes">
            <i class="fas fa-bell"></i>
            <div>
                <div class="count"><?= $stats['pedidos_pendentes'] ?></div>
                <div>pedido(s) pendente(s) aguardando!</div>
            </div>
            <button class="btn btn-warning btn-sm" onclick="scrollToPedidos()">
                <i class="fas fa-arrow-down"></i> Ver Pedidos
            </button>
        </div>
        <?php endif; ?>

        <!-- ESTATÍSTICAS -->
        <div class="stats-grid no-print">
            <div class="stat-card highlight">
                <i class="fas fa-fire"></i>
                <div class="stat-numero" id="stat-vendas-hoje"><?= formatar_preco($vendas_hoje['valor']) ?></div>
                <div class="stat-label">Vendas Hoje (<?= $vendas_hoje['qtd'] ?> pedidos)</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-week"></i>
                <div class="stat-numero"><?= formatar_preco($vendas_semana['valor']) ?></div>
                <div class="stat-label">Vendas da Semana</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-numero" id="stat-pendentes"><?= $stats['pedidos_pendentes'] ?></div>
                <div class="stat-label">Pendentes</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-fire-burner"></i>
                <div class="stat-numero" id="stat-preparando"><?= $stats['pedidos_preparando'] ?></div>
                <div class="stat-label">Preparando</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-numero"><?= $total_clientes ?></div>
                <div class="stat-label">Clientes</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-hamburger"></i>
                <div class="stat-numero"><?= $total_produtos ?></div>
                <div class="stat-label">Produtos</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid no-print">
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Resumo Rápido</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div style="padding: 20px; background: #d5f5e3; border-radius: 12px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 800; color: #1e8449;"><?= $stats['total_pedidos'] ?></div>
                        <div style="color: #1e8449; font-weight: 500;">Total de Pedidos</div>
                    </div>
                    <div style="padding: 20px; background: #d4e6f1; border-radius: 12px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 800; color: #1a5276;"><?= formatar_preco($stats['faturamento_total'] ?? 0) ?></div>
                        <div style="color: #1a5276; font-weight: 500;">Faturamento Total</div>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <h3><i class="fas fa-trophy"></i> Mais Vendidos</h3>
                <?php if (empty($mais_vendidos)): ?>
                    <p style="color: var(--gray); text-align: center; padding: 20px;">Nenhuma venda ainda</p>
                <?php else: ?>
                    <ul class="top-produtos">
                        <?php foreach ($mais_vendidos as $i => $prod): ?>
                        <li>
                            <span class="rank"><?= $i + 1 ?></span>
                            <span class="nome"><?= htmlspecialchars($prod['nome']) ?></span>
                            <span class="qtd"><?= $prod['qtd'] ?> vendidos</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- PEDIDOS -->
        <div class="card" id="pedidos-section">
            <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <h2 style="margin: 0;"><i class="fas fa-receipt"></i> Pedidos de Hoje</h2>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="limpar_pedidos.php?manual=1" class="btn btn-warning btn-sm" title="Limpar pedidos antigos manualmente">
                        <i class="fas fa-broom"></i> Limpar Antigos
                    </a>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Tudo
                    </button>
                </div>
            </div>

            <div id="pedidos-container" style="margin-top: 20px;">
                <?php if (empty($pedidos)): ?>
                    <div class="empty">
                        <i class="fas fa-receipt"></i>
                        <h2>Nenhum pedido ainda</h2>
                        <p>Os pedidos aparecerão aqui automaticamente!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php $cfg = $status_config[$pedido['status']]; ?>
                        <div class="pedido" id="pedido-<?= $pedido['id'] ?>" style="border-left-color: <?= $cfg['cor'] ?>;">
                            <div class="pedido-header">
                                <div style="flex: 1;">
                                    <div class="pedido-numero">
                                        Pedido #<?= $pedido['id'] ?>
                                        <span style="font-size: 14px; color: var(--gray); font-weight: 400; margin-left: 12px;">
                                            <?= date('d/m H:i', strtotime($pedido['criado_em'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="pedido-info">
                                        <i class="fas fa-user"></i> <strong><?= htmlspecialchars($pedido['cliente_nome']) ?></strong><br>
                                        <?php if ($pedido['telefone']): ?>
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($pedido['telefone']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($pedido['observacoes']): ?>
                                            <i class="fas fa-comment"></i> <em><?= htmlspecialchars($pedido['observacoes']) ?></em><br>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Info de Retirada -->
                                    <?php 
                                    $tipo_retirada = $pedido['tipo_retirada'] ?? 'balcao';
                                    $numero_mesa = $pedido['numero_mesa'] ?? '';
                                    ?>
                                    <div class="retirada-badge <?= $tipo_retirada ?>">
                                        <?php if ($tipo_retirada == 'mesa' && $numero_mesa): ?>
                                            <i class="fas fa-chair"></i> 
                                            Entregar na <span class="mesa-numero">MESA <?= htmlspecialchars($numero_mesa) ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-store"></i> 
                                            Retirar no <strong>BALCÃO</strong>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Itens do Pedido -->
                                    <div class="pedido-itens">
                                        <strong><i class="fas fa-utensils"></i> Itens:</strong>
                                        <ul class="lista-itens">
                                            <?php foreach ($pedido['itens'] as $item): ?>
                                                <li>
                                                    <span class="item-qty"><?= $item['quantidade'] ?>x</span>
                                                    <span class="item-name"><?= htmlspecialchars($item['produto_nome']) ?></span>
                                                    <span class="item-price"><?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div style="font-size: 22px; font-weight: 800; color: var(--success); margin-top: 12px;">
                                        Total: <?= formatar_preco($pedido['total']) ?>
                                    </div>

                                    <!-- Ações Rápidas -->
                                    <?php if ($pedido['status'] != 'entregue' && $pedido['status'] != 'cancelado'): ?>
                                    <div class="pedido-actions no-print">
                                        <?php if ($pedido['status'] == 'pendente'): ?>
                                            <button class="quick-status-btn preparando" onclick="mudarStatusRapido(<?= $pedido['id'] ?>, 'preparando')">
                                                <i class="fas fa-fire"></i> Iniciar Preparo
                                            </button>
                                        <?php elseif ($pedido['status'] == 'preparando'): ?>
                                            <button class="quick-status-btn pronto" onclick="mudarStatusRapido(<?= $pedido['id'] ?>, 'pronto')">
                                                <i class="fas fa-check"></i> Marcar Pronto
                                            </button>
                                        <?php elseif ($pedido['status'] == 'pronto'): ?>
                                            <button class="quick-status-btn entregue" onclick="mudarStatusRapido(<?= $pedido['id'] ?>, 'entregue')">
                                                <i class="fas fa-check-double"></i> Confirmar Entrega
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-secondary" onclick="imprimirPedido(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-print"></i> Imprimir
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="no-print" style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                                    <span class="status-badge" style="background: <?= $cfg['cor'] ?>;">
                                        <i class="fas fa-<?= $cfg['icone'] ?>"></i>
                                        <?= $cfg['label'] ?>
                                    </span>
                                    
                                    <select id="status-<?= $pedido['id'] ?>" 
                                            onchange="atualizarStatus(<?= $pedido['id'] ?>, this.value)"
                                            style="width: 140px;">
                                        <option value="pendente" <?= $pedido['status'] == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="preparando" <?= $pedido['status'] == 'preparando' ? 'selected' : '' ?>>Preparando</option>
                                        <option value="pronto" <?= $pedido['status'] == 'pronto' ? 'selected' : '' ?>>Pronto</option>
                                        <option value="entregue" <?= $pedido['status'] == 'entregue' ? 'selected' : '' ?>>Entregue</option>
                                        <option value="cancelado" <?= $pedido['status'] == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botão de Som -->
    <div class="sound-toggle no-print">
        <button id="sound-btn" onclick="toggleSound()" title="Ativar/Desativar Sons">
            <i class="fas fa-volume-up"></i>
        </button>
    </div>

    <!-- Som de Notificação -->
    <audio id="notification-sound" preload="auto">
        <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
    </audio>

    <script>
        // Configurações
        let soundEnabled = localStorage.getItem('soundEnabled') !== 'false';
        const statusConfig = <?= json_encode($status_config) ?>;
        let pedidosAtuais = new Set([<?= implode(',', array_column($pedidos, 'id')) ?>]);
        let ultimosPendentes = <?= $stats['pedidos_pendentes'] ?>;
        
        // Atualizar ícone do som
        function updateSoundIcon() {
            const btn = document.getElementById('sound-btn');
            const icon = btn.querySelector('i');
            if (soundEnabled) {
                icon.className = 'fas fa-volume-up';
                btn.classList.remove('muted');
            } else {
                icon.className = 'fas fa-volume-mute';
                btn.classList.add('muted');
            }
        }
        
        function toggleSound() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('soundEnabled', soundEnabled);
            updateSoundIcon();
            if (soundEnabled) {
                mostrarToast('🔊 Sons ativados!', 'success');
            } else {
                mostrarToast('🔇 Sons desativados', 'warning');
            }
        }
        
        updateSoundIcon();
        
        // Tocar som de notificação
        function playNotificationSound() {
            if (soundEnabled) {
                const audio = document.getElementById('notification-sound');
                audio.currentTime = 0;
                audio.play().catch(e => console.log('Audio blocked'));
            }
        }

        // Mostrar toast
        function mostrarToast(mensagem, tipo = 'success') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${tipo}`;
            toast.innerHTML = `<i class="fas fa-${tipo === 'success' ? 'check' : tipo === 'error' ? 'times' : 'info'}-circle"></i> ${mensagem}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Scroll para pedidos
        function scrollToPedidos() {
            document.getElementById('pedidos-section').scrollIntoView({ behavior: 'smooth' });
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
                    mostrarToast(`Pedido #${idPedido} atualizado!`, 'success');
                    // Recarregar para atualizar visual
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (error) {
                mostrarToast('Erro ao atualizar', 'error');
            }
        }

        // Mudança rápida de status
        function mudarStatusRapido(idPedido, novoStatus) {
            atualizarStatus(idPedido, novoStatus);
        }

        // Imprimir pedido individual
        function imprimirPedido(idPedido) {
            const pedido = document.getElementById(`pedido-${idPedido}`);
            
            // Criar janela de impressão
            const printWindow = window.open('', '_blank', 'width=400,height=600');
            
            // Clonar conteúdo do pedido
            const conteudo = pedido.cloneNode(true);
            
            // Remover elementos não imprimíveis
            conteudo.querySelectorAll('.no-print, .pedido-actions, select, .status-badge').forEach(el => el.remove());
            
            // Extrair informações
            const pedidoNumero = conteudo.querySelector('.pedido-numero')?.textContent || '';
            const pedidoInfo = conteudo.querySelector('.pedido-info')?.innerHTML || '';
            const retiradaBadge = conteudo.querySelector('.retirada-badge')?.innerHTML || '';
            const pedidoItens = conteudo.querySelector('.pedido-itens')?.innerHTML || '';
            const total = conteudo.querySelector('[style*="font-size: 22px"]')?.textContent || '';
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Pedido #${idPedido} - Burger House</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { 
                            font-family: 'Courier New', monospace; 
                            padding: 20px;
                            font-size: 12px;
                            width: 280px;
                            margin: 0 auto;
                        }
                        .header { 
                            text-align: center; 
                            border-bottom: 2px dashed #000;
                            padding-bottom: 15px;
                            margin-bottom: 15px;
                        }
                        .header h1 { font-size: 18px; margin-bottom: 5px; }
                        .header p { font-size: 10px; color: #666; }
                        .pedido-numero {
                            font-size: 16px;
                            font-weight: bold;
                            text-align: center;
                            margin-bottom: 10px;
                            padding: 10px;
                            background: #f5f5f5;
                        }
                        .info { margin-bottom: 15px; line-height: 1.6; }
                        .info i { display: none; }
                        .retirada {
                            text-align: center;
                            padding: 10px;
                            margin: 15px 0;
                            border: 2px solid #000;
                            font-weight: bold;
                            font-size: 14px;
                        }
                        .retirada i { display: none; }
                        .itens { margin: 15px 0; }
                        .itens strong { display: block; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; }
                        .itens i { display: none; }
                        .lista-itens { list-style: none; }
                        .lista-itens li { 
                            display: flex; 
                            justify-content: space-between;
                            padding: 5px 0;
                            border-bottom: 1px dotted #ccc;
                        }
                        .item-qty {
                            font-weight: bold;
                            min-width: 30px;
                        }
                        .item-name { flex: 1; margin: 0 10px; }
                        .item-price { font-weight: bold; }
                        .total {
                            text-align: right;
                            font-size: 18px;
                            font-weight: bold;
                            margin-top: 15px;
                            padding-top: 10px;
                            border-top: 2px solid #000;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 20px;
                            padding-top: 15px;
                            border-top: 2px dashed #000;
                            font-size: 10px;
                            color: #666;
                        }
                        @media print {
                            body { width: 100%; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>🍔 BURGER HOUSE</h1>
                        <p>Sistema de Pedidos</p>
                    </div>
                    
                    <div class="pedido-numero">${pedidoNumero.trim()}</div>
                    
                    <div class="info">${pedidoInfo}</div>
                    
                    <div class="retirada">${retiradaBadge}</div>
                    
                    <div class="itens">${pedidoItens}</div>
                    
                    <div class="total">${total}</div>
                    
                    <div class="footer">
                        <p>Obrigado pela preferência!</p>
                        <p>${new Date().toLocaleString('pt-BR')}</p>
                    </div>
                    
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 500);
                        };
                    <\/script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        }

        // Formatar preço
        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',');
        }

        // Atualizar estatísticas
        async function atualizarStats() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_stats_dono');
                const data = await response.json();

                if (data.stats) {
                    document.getElementById('stat-pendentes').textContent = data.stats.pedidos_pendentes || 0;
                    document.getElementById('stat-preparando').textContent = data.stats.pedidos_preparando || 0;
                    
                    // Alerta de novos pendentes
                    const novosPendentes = parseInt(data.stats.pedidos_pendentes) || 0;
                    if (novosPendentes > ultimosPendentes) {
                        playNotificationSound();
                        mostrarToast('🔔 Novo pedido recebido!', 'warning');
                        
                        // Notificação do navegador
                        if (Notification.permission === 'granted') {
                            new Notification('🍔 Burger House', {
                                body: 'Novo pedido recebido!',
                                icon: '🍔'
                            });
                        }
                    }
                    ultimosPendentes = novosPendentes;
                }
            } catch (error) {
                console.error('Erro ao atualizar stats:', error);
            }
        }

        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Atualizar a cada 5 segundos
        setInterval(() => {
            atualizarStats();
        }, 5000);
        
        // Recarregar página a cada 30 segundos para pegar novos pedidos
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
