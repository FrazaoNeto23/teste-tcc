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

// Solicitar conta
if (isset($_POST['solicitar_conta'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $local_conta = sanitizar_texto($_POST['local_conta']); // 'mesa' ou 'balcao'

    $conn->query("UPDATE pedidos SET conta_solicitada=1, conta_solicitada_em=NOW(), local_conta='$local_conta' WHERE id=$id_pedido AND id_cliente=$id_cliente");

    $mensagem = $local_conta == 'mesa' ? 'Conta solicitada! O garçom levará a conta até você.' : 'Conta solicitada! Aguarde no balcão para pagar.';
    redirecionar('painel_cliente.php', $mensagem);
}

// Cancelar solicitação de conta
if (isset($_POST['cancelar_conta'])) {
    $id_pedido = (int) $_POST['id_pedido'];
    $conn->query("UPDATE pedidos SET conta_solicitada=0, conta_solicitada_em=NULL, local_conta=NULL WHERE id=$id_pedido AND id_cliente=$id_cliente");
    redirecionar('painel_cliente.php', 'Solicitação de conta cancelada.');
}

// Buscar dados do cliente
$cliente = $conn->query("SELECT * FROM usuarios WHERE id=$id_cliente")->fetch_assoc();

// Verificar se as colunas existem
$tem_sistema_conta = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'conta_solicitada'")->num_rows > 0;

// Buscar pedidos
$pedidos = $conn->query("
    SELECT p.*, COUNT(pi.id) as total_itens 
    FROM pedidos p 
    LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido 
    WHERE p.id_cliente = $id_cliente 
    GROUP BY p.id 
    ORDER BY p.criado_em DESC
")->fetch_all(MYSQLI_ASSOC);

$status_cores = [
    'pendente' => '#ffc107',
    'preparando' => '#17a2b8',
    'pronto' => '#28a745',
    'entregue' => '#6c757d',
    'cancelado' => '#dc3545'
];

$status_labels = [
    'pendente' => 'PENDENTE',
    'preparando' => 'PREPARANDO',
    'pronto' => 'PRONTO',
    'entregue' => 'ENTREGUE',
    'cancelado' => 'CANCELADO'
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
</head>

<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</a>
            <div style="display: flex; gap: 15px;">
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Início</a>
                <a href="carrinho.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Carrinho
                    <span class="badge" id="cart-count" style="display:none;"></span>
                </a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-user-circle"></i> Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</h1>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
                <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="mudarTab('pedidos')">
                    <i class="fas fa-list"></i> Meus Pedidos
                    <span class="auto-update-badge">
                        <i class="fas fa-sync-alt"></i> Auto
                    </span>
                </button>
                <button class="tab" onclick="mudarTab('dados')">
                    <i class="fas fa-user-edit"></i> Meus Dados
                </button>
            </div>

            <!-- ABA PEDIDOS -->
            <div id="pedidos" class="tab-content active">
                <div id="pedidos-container">
                    <?php if (empty($pedidos)): ?>
                            <div class="empty">
                                <i class="fas fa-receipt"></i>
                                <h2>Nenhum pedido ainda</h2>
                                <p>Faça seu primeiro pedido!</p>
                                <br>
                                <a href="index.php" class="btn btn-success"><i class="fas fa-shopping-bag"></i> Ver Produtos</a>
                            </div>
                    <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                    <div class="pedido <?= ($tem_sistema_conta && $pedido['conta_solicitada']) ? 'pedido-conta-solicitada' : '' ?>" id="pedido-<?= $pedido['id'] ?>">
                                        <div class="pedido-header">
                                            <div>
                                                <div class="pedido-numero">Pedido #<?= $pedido['id'] ?></div>
                                                <div class="pedido-info">
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?>
                                                    | <i class="fas fa-box"></i> <?= $pedido['total_itens'] ?> item(ns)
                                                </div>
                                            </div>
                                            <div>
                                                <span class="status-badge" id="status-badge-<?= $pedido['id'] ?>"
                                                    style="background: <?= $status_cores[$pedido['status']] ?>">
                                                    <?= $status_labels[$pedido['status']] ?>
                                                </span>
                                            </div>
                                        </div>
                                
                                        <?php if ($pedido['observacoes']): ?>
                                                <div class="pedido-info">
                                                    <i class="fas fa-comment"></i> <strong>Observações:</strong>
                                                    <?= htmlspecialchars($pedido['observacoes']) ?>
                                                </div>
                                        <?php endif; ?>
                                
                                        <div class="pedido-total">Total: <?= formatar_preco($pedido['total']) ?></div>
                                
                                        <?php if ($tem_sistema_conta): ?>
                                                <!-- SOLICITAR CONTA -->
                                                <?php if (!$pedido['conta_solicitada'] && in_array($pedido['status'], ['pronto', 'entregue'])): ?>
                                                        <div class="solicitar-conta-box">
                                                            <p><i class="fas fa-info-circle"></i> <strong>Finalizou sua refeição?</strong> Escolha onde quer pagar:</p>
                                                            <form method="POST" style="display:flex;gap:10px;margin-top:10px;">
                                                                <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                                                                <button type="submit" name="solicitar_conta" value="1" onclick="return confirm('Solicitar conta na mesa?')" class="btn btn-primary" style="flex:1;">
                                                                    <input type="hidden" name="local_conta" value="mesa">
                                                                    <i class="fas fa-chair"></i> Trazer na Mesa
                                                                </button>
                                                                <button type="submit" name="solicitar_conta" value="1" onclick="return confirm('Ir ao balcão pagar?')" class="btn btn-success" style="flex:1;">
                                                                    <input type="hidden" name="local_conta" value="balcao">
                                                                    <i class="fas fa-cash-register"></i> Pagar no Balcão
                                                                </button>
                                                            </form>
                                                        </div>
                                                <?php elseif ($pedido['conta_solicitada']): ?>
                                                        <div class="conta-solicitada-box">
                                                            <div style="text-align:center;">
                                                                <i class="fas fa-check-circle"></i>
                                                                <h3 style="margin:10px 0;">Conta Solicitada!</h3>
                                                                <p>
                                                                    <?php if ($pedido['local_conta'] == 'mesa'): ?>
                                                                            <i class="fas fa-chair"></i> O garçom levará a conta até sua mesa.
                                                                    <?php else: ?>
                                                                            <i class="fas fa-cash-register"></i> Dirija-se ao balcão para pagar.
                                                                    <?php endif; ?>
                                                                </p>
                                                                <small style="color:#666;">
                                                                    Solicitado em: <?= date('d/m/Y H:i', strtotime($pedido['conta_solicitada_em'])) ?>
                                                                </small>
                                                                <form method="POST" style="margin-top:10px;">
                                                                    <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                                                                    <button type="submit" name="cancelar_conta" class="btn btn-danger btn-sm" onclick="return confirm('Cancelar solicitação de conta?')">
                                                                        <i class="fas fa-times"></i> Cancelar Solicitação
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                            <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ABA DADOS -->
            <div id="dados" class="tab-content">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nome Completo</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" value="<?= htmlspecialchars($cliente['email']) ?>" disabled
                            style="background:#f0f0f0;">
                        <small style="color:#999;">O email não pode ser alterado</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefone</label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($cliente['telefone']) ?>"
                            placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
                        <textarea name="endereco" rows="3"
                            placeholder="Rua, número, bairro, cidade"><?= htmlspecialchars($cliente['endereco']) ?></textarea>
                    </div>

                    <button type="submit" name="atualizar_dados" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .solicitar-conta-box {
            margin-top: 15px;
            padding: 15px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
        }

        .solicitar-conta-box p {
            margin: 0 0 10px 0;
            color: #92400e;
        }

        .conta-solicitada-box {
            margin-top: 15px;
            padding: 20px;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-left: 4px solid #10b981;
            border-radius: 8px;
        }

        .conta-solicitada-box i.fa-check-circle {
            font-size: 40px;
            color: #10b981;
        }

        .conta-solicitada-box h3 {
            color: #065f46;
            margin: 10px 0;
        }

        .conta-solicitada-box p {
            color: #047857;
            margin: 8px 0;
            font-size: 15px;
        }

        .pedido-conta-solicitada {
            border-left: 4px solid #10b981;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
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

        const statusLabels = {
            'pendente': 'PENDENTE',
            'preparando': 'PREPARANDO',
            'pronto': 'PRONTO',
            'entregue': 'ENTREGUE',
            'cancelado': 'CANCELADO'
        };

        let statusAtual = {};
        <?php foreach ($pedidos as $pedido): ?>
                statusAtual[<?= $pedido['id'] ?>] = '<?= $pedido['status'] ?>';
        <?php endforeach; ?>

        function mudarTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.closest('.tab').classList.add('active');
            document.getElementById(tab).classList.add('active');
        }

        function mostrarToast(mensagem) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<i class="fas fa-info-circle"></i> ${mensagem}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function formatarData(dataStr) {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        async function atualizarPedidos() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_pedidos_cliente');
                const data = await response.json();

                if (data.pedidos) {
                    data.pedidos.forEach(pedido => {
                        const statusAntigo = statusAtual[pedido.id];
                        const statusNovo = pedido.status;

                        if (statusAntigo && statusAntigo !== statusNovo) {
                            mostrarToast(`Pedido #${pedido.id} atualizado para: ${statusLabels[statusNovo]}`);
                            
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('Burger House', {
                                    body: `Pedido #${pedido.id} está ${statusLabels[statusNovo]}!`,
                                    icon: '🍔'
                                });
                            }
                            
                            // Atualizar badge visualmente
                            const badge = document.getElementById(`status-badge-${pedido.id}`);
                            if (badge) {
                                badge.style.background = statusCores[statusNovo];
                                badge.textContent = statusLabels[statusNovo];
                            }
                        }

                        statusAtual[pedido.id] = statusNovo;
                    });
                }
            } catch (error) {
                console.error('Erro ao atualizar pedidos:', error);
            }
        }

        async function atualizarContadorCarrinho() {
            try {
                const response = await fetch('ajax_handler.php?action=contar_carrinho');
                const data = await response.json();

                const badge = document.getElementById('cart-count');
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            } catch (error) {
                console.error('Erro ao atualizar carrinho:', error);
            }
        }

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        setInterval(() => {
            atualizarPedidos();
            atualizarContadorCarrinho();
        }, 5000);

        atualizarContadorCarrinho();
    </script>
</body>

</html>