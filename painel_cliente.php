<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('cliente');

$id_cliente = $_SESSION['id_usuario'];

// Atualizar dados do cliente
if (isset($_POST['atualizar_dados'])) {
    $nome = sanitizar_texto($_POST['nome']);
    $telefone = sanitizar_texto($_POST['telefone']);
    $endereco = sanitizar_texto($_POST['endereco']);

    $stmt = $conn->prepare("UPDATE usuarios SET nome=?, telefone=?, endereco=? WHERE id=?");
    $stmt->bind_param("sssi", $nome, $telefone, $endereco, $id_cliente);
    $stmt->execute();

    $_SESSION['usuario'] = $nome;
    redirecionar('painel_cliente.php', 'Dados atualizados com sucesso!');
}

// Buscar dados do cliente
$cliente = $conn->query("SELECT * FROM usuarios WHERE id=$id_cliente")->fetch_assoc();

// Buscar pedidos com itens
$pedidos = $conn->query("
    SELECT p.* 
    FROM pedidos p 
    WHERE p.id_cliente = $id_cliente 
    ORDER BY p.criado_em DESC
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

// Contar carrinho
$cart_count = $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$id_cliente")->fetch_assoc()['t'];

$status_config = [
    'pendente' => ['cor' => '#f39c12', 'icone' => 'clock', 'label' => 'Aguardando', 'ordem' => 1],
    'preparando' => ['cor' => '#3498db', 'icone' => 'fire', 'label' => 'Preparando', 'ordem' => 2],
    'pronto' => ['cor' => '#2ecc71', 'icone' => 'check-circle', 'label' => 'Pronto', 'ordem' => 3],
    'entregue' => ['cor' => '#95a5a6', 'icone' => 'check-double', 'label' => 'Entregue', 'ordem' => 4],
    'cancelado' => ['cor' => '#e74c3c', 'icone' => 'times-circle', 'label' => 'Cancelado', 'ordem' => 0]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .pedido-em-andamento {
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
            border: 2px solid var(--warning);
            animation: pulse 3s ease-in-out infinite;
        }
        
        .pedido-pronto {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid var(--success);
        }
        
        .welcome-card {
            background: var(--gradient-fire);
            color: white;
            text-align: center;
            padding: 40px;
        }
        
        .welcome-card h1 {
            color: white;
            font-size: 28px;
        }
        
        .welcome-card p {
            opacity: 0.9;
            margin-top: 8px;
        }
        
        .stats-cliente {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        
        .stat-mini {
            background: var(--white);
            padding: 20px;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-mini i {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .stat-mini .numero {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
        }
        
        .stat-mini .label {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Info de retirada */
        .retirada-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            margin: 12px 0;
        }
        
        .retirada-info.mesa {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        
        .retirada-info.balcao {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 2px solid #f59e0b;
        }
        
        .retirada-info i {
            font-size: 20px;
        }
        
        .mesa-numero {
            font-size: 20px;
            font-weight: 800;
        }
        
        .pronto-alert {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .pronto-alert i {
            font-size: 28px;
            color: #28a745;
        }
        
        .pronto-alert-text {
            flex: 1;
        }
        
        .pronto-alert-text strong {
            display: block;
            color: #155724;
            font-size: 16px;
        }
        
        .pronto-alert-text span {
            color: #155724;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</a>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-store"></i> Cardápio</a>
                <a href="carrinho.php" class="btn btn-primary carrinho-badge">
                    <i class="fas fa-shopping-cart"></i> Carrinho
                    <?php if ($cart_count > 0): ?>
                        <span class="badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Bem-vindo -->
        <div class="card welcome-card">
            <h1><i class="fas fa-user-circle"></i> Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</h1>
            <p>Acompanhe seus pedidos em tempo real</p>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <!-- Mini Stats -->
        <?php 
        $total_pedidos = count($pedidos);
        $total_gasto = array_sum(array_column($pedidos, 'total'));
        ?>
        <div class="stats-cliente">
            <div class="stat-mini">
                <i class="fas fa-receipt"></i>
                <div class="numero"><?= $total_pedidos ?></div>
                <div class="label">Pedidos Feitos</div>
            </div>
            <div class="stat-mini">
                <i class="fas fa-coins"></i>
                <div class="numero"><?= formatar_preco($total_gasto) ?></div>
                <div class="label">Total Gasto</div>
            </div>
            <div class="stat-mini">
                <i class="fas fa-star"></i>
                <div class="numero">⭐</div>
                <div class="label">Cliente VIP</div>
            </div>
        </div>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="mudarTab('pedidos', this)">
                    <i class="fas fa-list"></i> Meus Pedidos
                    <span class="auto-update-badge"><i class="fas fa-sync-alt"></i></span>
                </button>
                <button class="tab" onclick="mudarTab('dados', this)">
                    <i class="fas fa-user-edit"></i> Meus Dados
                </button>
            </div>

            <!-- ABA PEDIDOS -->
            <div id="pedidos" class="tab-content active">
                <?php if (empty($pedidos)): ?>
                    <div class="empty">
                        <i class="fas fa-hamburger"></i>
                        <h2>Nenhum pedido ainda</h2>
                        <p>Que tal fazer seu primeiro pedido?</p>
                        <br>
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-utensils"></i> Ver Cardápio
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php 
                        $cfg = $status_config[$pedido['status']];
                        $em_andamento = in_array($pedido['status'], ['pendente', 'preparando', 'pronto']);
                        $classe_extra = '';
                        if ($pedido['status'] == 'pendente' || $pedido['status'] == 'preparando') {
                            $classe_extra = 'pedido-em-andamento';
                        } elseif ($pedido['status'] == 'pronto') {
                            $classe_extra = 'pedido-pronto';
                        }
                        
                        $tipo_retirada = $pedido['tipo_retirada'] ?? 'balcao';
                        $numero_mesa = $pedido['numero_mesa'] ?? '';
                        ?>
                        <div class="pedido <?= $classe_extra ?>" id="pedido-<?= $pedido['id'] ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                                <div>
                                    <div class="pedido-numero">
                                        Pedido #<?= $pedido['id'] ?>
                                    </div>
                                    <div class="pedido-info">
                                        <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge" style="background: <?= $cfg['cor'] ?>;">
                                    <i class="fas fa-<?= $cfg['icone'] ?>"></i>
                                    <?= strtoupper($cfg['label']) ?>
                                </span>
                            </div>

                            <!-- Info de Retirada -->
                            <div class="retirada-info <?= $tipo_retirada ?>">
                                <?php if ($tipo_retirada == 'mesa' && $numero_mesa): ?>
                                    <i class="fas fa-chair"></i>
                                    <span>Entrega na <span class="mesa-numero">Mesa <?= htmlspecialchars($numero_mesa) ?></span></span>
                                <?php else: ?>
                                    <i class="fas fa-store"></i>
                                    <span>Retirar no <strong>Balcão</strong></span>
                                <?php endif; ?>
                            </div>

                            <!-- Timeline de Status -->
                            <?php if ($pedido['status'] != 'cancelado'): ?>
                            <div class="order-timeline">
                                <?php 
                                $ordem_atual = $cfg['ordem'];
                                $steps = [
                                    ['icone' => 'clock', 'label' => 'Recebido', 'ordem' => 1],
                                    ['icone' => 'fire', 'label' => 'Preparando', 'ordem' => 2],
                                    ['icone' => 'check-circle', 'label' => 'Pronto', 'ordem' => 3],
                                    ['icone' => 'check-double', 'label' => 'Entregue', 'ordem' => 4],
                                ];
                                ?>
                                <?php foreach ($steps as $step): ?>
                                    <?php 
                                    $is_active = $ordem_atual >= $step['ordem'];
                                    $is_current = $ordem_atual == $step['ordem'];
                                    ?>
                                    <div class="timeline-step <?= $is_active ? 'active' : '' ?> <?= $is_current ? 'current' : '' ?>">
                                        <div class="timeline-icon">
                                            <i class="fas fa-<?= $step['icone'] ?>"></i>
                                        </div>
                                        <span class="timeline-label"><?= $step['label'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Alerta de Pronto -->
                            <?php if ($pedido['status'] == 'pronto'): ?>
                                <div class="pronto-alert">
                                    <i class="fas fa-bell"></i>
                                    <div class="pronto-alert-text">
                                        <strong>Seu pedido está pronto!</strong>
                                        <?php if ($tipo_retirada == 'mesa' && $numero_mesa): ?>
                                            <span>Estamos levando até a mesa <?= htmlspecialchars($numero_mesa) ?>!</span>
                                        <?php else: ?>
                                            <span>Por favor, retire no balcão.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <div style="text-align: center; padding: 20px; background: #fee2e2; border-radius: 12px; margin: 16px 0;">
                                <i class="fas fa-times-circle" style="font-size: 32px; color: #dc2626;"></i>
                                <p style="color: #dc2626; font-weight: 600; margin-top: 8px;">Pedido Cancelado</p>
                            </div>
                            <?php endif; ?>

                            <!-- Itens do Pedido -->
                            <div class="pedido-itens">
                                <strong><i class="fas fa-utensils"></i> Itens do Pedido:</strong>
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

                            <?php if ($pedido['observacoes']): ?>
                                <div style="margin-top: 12px; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px;">
                                    <i class="fas fa-comment" style="color: var(--primary);"></i>
                                    <strong>Observações:</strong> <?= htmlspecialchars($pedido['observacoes']) ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid rgba(0,0,0,0.05);">
                                <span style="font-size: 24px; font-weight: 800; color: var(--success);">
                                    Total: <?= formatar_preco($pedido['total']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ABA DADOS -->
            <div id="dados" class="tab-content">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nome Completo</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" value="<?= htmlspecialchars($cliente['email']) ?>" disabled style="background:#f5f5f5; cursor: not-allowed;">
                            <small style="color: var(--gray);">O email não pode ser alterado</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefone</label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($cliente['telefone']) ?>" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
                        <textarea name="endereco" rows="3" placeholder="Rua, número, bairro, cidade, complemento..."><?= htmlspecialchars($cliente['endereco']) ?></textarea>
                    </div>

                    <button type="submit" name="atualizar_dados" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Som de Notificação -->
    <audio id="notification-sound" preload="auto">
        <source src="https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3" type="audio/mpeg">
    </audio>

    <script>
        const statusConfig = <?= json_encode($status_config) ?>;
        let statusAtual = {};
        <?php foreach ($pedidos as $pedido): ?>
            statusAtual[<?= $pedido['id'] ?>] = '<?= $pedido['status'] ?>';
        <?php endforeach; ?>

        function mudarTab(tab, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tab).classList.add('active');
        }

        function mostrarToast(mensagem, tipo = 'success') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${tipo}`;
            toast.innerHTML = `<i class="fas fa-info-circle"></i> ${mensagem}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function playNotificationSound() {
            const audio = document.getElementById('notification-sound');
            audio.currentTime = 0;
            audio.play().catch(e => console.log('Audio blocked'));
        }

        // Atualizar pedidos em tempo real
        async function atualizarPedidos() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_pedidos_cliente');
                const data = await response.json();

                if (data.pedidos) {
                    data.pedidos.forEach(pedido => {
                        const statusAntigo = statusAtual[pedido.id];
                        const statusNovo = pedido.status;
                        
                        if (statusAntigo && statusAntigo !== statusNovo) {
                            playNotificationSound();
                            
                            const cfg = statusConfig[statusNovo];
                            let msg = `Pedido #${pedido.id}: ${cfg.label.toUpperCase()}!`;
                            
                            if (statusNovo === 'pronto') {
                                msg = `🔔 Pedido #${pedido.id} está PRONTO!`;
                            }
                            
                            mostrarToast(msg, 'success');
                            
                            // Notificação do navegador
                            if (Notification.permission === 'granted') {
                                new Notification('🍔 Burger House', {
                                    body: msg,
                                    icon: '🍔'
                                });
                            }
                            
                            statusAtual[pedido.id] = statusNovo;
                            
                            // Recarregar para atualizar visual
                            setTimeout(() => location.reload(), 2000);
                        }
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Atualizar a cada 5 segundos
        setInterval(atualizarPedidos, 5000);
    </script>
</body>
</html>
