<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// Atualizar status do pedido
if (isset($_POST['atualizar_status'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $status = $_POST['status'];
    $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");
    redirecionar('painel_dono.php', 'Status atualizado!');
}

// Confirmar entrega da conta
if (isset($_POST['confirmar_conta_entregue'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $conn->query("UPDATE pedidos SET conta_solicitada=0, conta_solicitada_em=NULL, local_conta=NULL WHERE id=$id_pedido");
    redirecionar('painel_dono.php', 'Conta marcada como entregue!');
}

// Verificar se as colunas do sistema de conta existem
$tem_sistema_conta = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'conta_solicitada'")->num_rows > 0;

// Buscar estatísticas
if ($tem_sistema_conta) {
    $stats = $conn->query("
        SELECT 
            COUNT(DISTINCT id) as total_pedidos,
            SUM(total) as faturamento_total,
            SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
            SUM(CASE WHEN conta_solicitada=1 THEN 1 ELSE 0 END) as contas_solicitadas
        FROM pedidos
    ")->fetch_assoc();
} else {
    $stats = $conn->query("
        SELECT 
            COUNT(DISTINCT id) as total_pedidos,
            SUM(total) as faturamento_total,
            SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
            0 as contas_solicitadas
        FROM pedidos
    ")->fetch_assoc();
}

$total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
$total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];

// Buscar pedidos recentes
$pedidos = $conn->query("
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
    FROM pedidos p 
    JOIN usuarios u ON p.id_cliente = u.id
    ORDER BY p.criado_em DESC 
    LIMIT 50
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

    <!-- ALERTA DE CONTAS SOLICITADAS (FIXO NO TOPO) -->
    <div id="alerta-contas" class="alerta-contas-fixo" style="display:none;">
        <div class="alerta-contas-content">
            <i class="fas fa-bell"></i>
            <span id="alerta-contas-texto"></span>
            <button onclick="fecharAlerta()" class="btn-fechar-alerta">
                <i class="fas fa-times"></i>
            </button>
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

            <?php if ($tem_sistema_conta): ?>
                <div class="stat-card conta-solicitada-stat">
                    <i class="fas fa-hand-paper"></i>
                    <div class="stat-numero" id="stat-contas"><?= $stats['contas_solicitadas'] ?></div>
                    <div class="stat-label">Contas Solicitadas</div>
                </div>
            <?php endif; ?>

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
                        <div class="pedido <?= ($tem_sistema_conta && isset($pedido['conta_solicitada']) && $pedido['conta_solicitada']) ? 'conta-solicitada-pedido' : '' ?>"
                            id="pedido-<?= $pedido['id'] ?>"
                            data-conta-solicitada="<?= ($tem_sistema_conta && isset($pedido['conta_solicitada'])) ? $pedido['conta_solicitada'] : 0 ?>">
                            <div class="pedido-header">
                                <div style="flex:1;">
                                    <div class="pedido-numero">
                                        Pedido #<?= $pedido['id'] ?>
                                        <?php if ($tem_sistema_conta && isset($pedido['conta_solicitada']) && $pedido['conta_solicitada']): ?>
                                            <span class="badge-conta-solicitada pulsando">
                                                <i class="fas fa-hand-paper"></i> CONTA SOLICITADA
                                                <?php if (isset($pedido['local_conta']) && $pedido['local_conta']): ?>
                                                    - <?= $pedido['local_conta'] == 'mesa' ? 'NA MESA' : 'NO BALCÃO' ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
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
                                        <?php if ($tem_sistema_conta && isset($pedido['conta_solicitada']) && $pedido['conta_solicitada']): ?>
                                            <strong style="color: #ef4444;"><i class="fas fa-clock"></i> Conta solicitada
                                                em:</strong>
                                            <strong
                                                style="color: #ef4444;"><?= date('d/m/Y H:i', strtotime($pedido['conta_solicitada_em'])) ?></strong><br>
                                        <?php endif; ?>
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
                                        <option value="pronto" <?= $pedido['status'] == 'pronto' ? 'selected' : '' ?>>Pronto
                                        </option>
                                        <option value="entregue" <?= $pedido['status'] == 'entregue' ? 'selected' : '' ?>>Servido
                                        </option>
                                        <option value="cancelado" <?= $pedido['status'] == 'cancelado' ? 'selected' : '' ?>>
                                            Cancelado</option>
                                    </select>

                                    <?php if ($tem_sistema_conta && isset($pedido['conta_solicitada']) && $pedido['conta_solicitada']): ?>
                                        <button onclick="confirmarContaEntregue(<?= $pedido['id'] ?>)" class="btn btn-success"
                                            style="margin-top: 10px; width: 100%;">
                                            <i class="fas fa-check"></i> Conta Entregue
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        /* ALERTA FIXO DE CONTAS */
        .alerta-contas-fixo {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                top: -100px;
                opacity: 0;
            }

            to {
                top: 80px;
                opacity: 1;
            }
        }

        .alerta-contas-content {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.5);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 400px;
            animation: pulse 2s ease-in-out infinite;
        }

        .alerta-contas-content i.fa-bell {
            font-size: 32px;
            animation: shake 1s ease-in-out infinite;
        }

        @keyframes shake {

            0%,
            100% {
                transform: rotate(0deg);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: rotate(-10deg);
            }

            20%,
            40%,
            60%,
            80% {
                transform: rotate(10deg);
            }
        }

        .btn-fechar-alerta {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-fechar-alerta:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .badge-conta-solicitada {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
        }

        .pulsando {
            animation: pulse 1.5s ease-in-out infinite;
        }

        .conta-solicitada-pedido {
            border-left: 6px solid #ef4444;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 30%, #fff 100%);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3);
        }

        .conta-solicitada-stat {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 3px solid #ef4444;
        }

        .conta-solicitada-stat i {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .alerta-contas-content {
                min-width: 90%;
                padding: 15px;
            }
        }
    </style>

    <script>
        const statusCores = {
            'pendente': '#ffc107',
            'preparando': '#17a2b8',
            'pronto': '#28a745',
            'entregue': '#6c757d',
            'cancelado': '#dc3545'
        };

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

        async function confirmarContaEntregue(idPedido) {
            if (!confirm('Confirmar que a conta foi entregue ao cliente?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'confirmar_conta_entregue');
                formData.append('id_pedido', idPedido);

                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.sucesso) {
                    mostrarToast('Conta marcada como entregue!');

                    // Atualizar visualmente SEM recarregar a página
                    const pedidoDiv = document.getElementById(`pedido-${idPedido}`);
                    if (pedidoDiv) {
                        pedidoDiv.classList.remove('conta-solicitada-pedido');
                        pedidoDiv.setAttribute('data-conta-solicitada', '0');

                        // Remover badge
                        const badge = pedidoDiv.querySelector('.badge-conta-solicitada');
                        if (badge) badge.remove();

                        // Remover botão
                        const btn = pedidoDiv.querySelector('.btn-success');
                        if (btn) btn.remove();

                        // Remover info de conta solicitada
                        const infos = pedidoDiv.querySelectorAll('.pedido-info strong');
                        infos.forEach(info => {
                            if (info.textContent.includes('Conta solicitada em:')) {
                                info.parentElement.remove();
                            }
                        });
                    }

                    atualizarStats();
                    verificarContasSolicitadas();
                } else {
                    mostrarToast('Erro ao confirmar', 'error');
                }
            } catch (error) {
                mostrarToast('Erro de conexão', 'error');
            }
        }

        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function formatarData(dataStr) {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        async function atualizarStats() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_stats_dono');
                const data = await response.json();

                if (data.stats) {
                    document.getElementById('stat-total-pedidos').textContent = data.stats.total_pedidos || 0;
                    document.getElementById('stat-faturamento').textContent = formatarPreco(data.stats.faturamento_total || 0);
                    document.getElementById('stat-pendentes').textContent = data.stats.pedidos_pendentes || 0;

                    const statContas = document.getElementById('stat-contas');
                    if (statContas) {
                        statContas.textContent = data.stats.contas_solicitadas || 0;
                    }

                    document.getElementById('stat-clientes').textContent = data.total_clientes || 0;
                    document.getElementById('stat-produtos').textContent = data.total_produtos || 0;
                }
            } catch (error) {
                console.error('Erro ao atualizar stats:', error);
            }
        }

        // Verificar contas solicitadas e mostrar alerta
        function verificarContasSolicitadas() {
            const pedidos = document.querySelectorAll('.pedido');
            const contasSolicitadas = [];

            pedidos.forEach(pedido => {
                const contaSolicitada = pedido.getAttribute('data-conta-solicitada') == '1';
                if (contaSolicitada) {
                    const numero = pedido.id.replace('pedido-', '');
                    const badge = pedido.querySelector('.badge-conta-solicitada');
                    const local = badge ? badge.textContent : '';
                    contasSolicitadas.push({ numero, local });
                }
            });

            const alerta = document.getElementById('alerta-contas');
            const alertaTexto = document.getElementById('alerta-contas-texto');

            if (contasSolicitadas.length > 0) {
                const plural = contasSolicitadas.length > 1 ? 's' : '';
                const pedidosNumeros = contasSolicitadas.map(c => `#${c.numero}`).join(', ');
                alertaTexto.innerHTML = `<strong>${contasSolicitadas.length} conta${plural} solicitada${plural}!</strong> Pedido${plural}: ${pedidosNumeros}`;
                alerta.style.display = 'block';
            } else {
                alerta.style.display = 'none';
            }
        }

        function fecharAlerta() {
            document.getElementById('alerta-contas').style.display = 'none';
        }

        let pedidosAtuais = new Set([<?= implode(',', array_column($pedidos, 'id')) ?>]);

        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Verificar contas solicitadas ao carregar
        verificarContasSolicitadas();

        // Atualizar automaticamente a cada 5 segundos
        setInterval(() => {
            atualizarStats();
            verificarContasSolicitadas();
        }, 5000);

        setTimeout(() => {
            atualizarStats();
        }, 1000);
    </script>
</body>

</html>