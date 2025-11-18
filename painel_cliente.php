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
                            <div class="pedido" id="pedido-<?= $pedido['id'] ?>">
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

        // Estado atual dos pedidos
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

        // Mostrar toast de notificação
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

        // Formatar preço
        function formatarPreco(valor) {
            return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Formatar data
        function formatarData(dataStr) {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        // Atualizar pedidos
        async function atualizarPedidos() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_pedidos_cliente');
                const data = await response.json();

                if (data.pedidos) {
                    const container = document.getElementById('pedidos-container');

                    if (data.pedidos.length === 0) {
                        container.innerHTML = `
                            <div class="empty">
                                <i class="fas fa-receipt"></i>
                                <h2>Nenhum pedido ainda</h2>
                                <p>Faça seu primeiro pedido!</p>
                                <br>
                                <a href="index.php" class="btn btn-success"><i class="fas fa-shopping-bag"></i> Ver Produtos</a>
                            </div>
                        `;
                        return;
                    }

                    let html = '';

                    data.pedidos.forEach(pedido => {
                        // Verificar se status mudou
                        const statusAntigo = statusAtual[pedido.id];
                        const statusNovo = pedido.status;
                        const mudou = statusAntigo && statusAntigo !== statusNovo;

                        if (mudou) {
                            mostrarToast(`Pedido #${pedido.id} atualizado para: ${statusLabels[statusNovo]}`);
                            // Notificação do navegador
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('Burger House', {
                                    body: `Pedido #${pedido.id} está ${statusLabels[statusNovo]}!`,
                                    icon: '🍔'
                                });
                            }
                        }

                        statusAtual[pedido.id] = statusNovo;

                        html += `
                            <div class="pedido ${mudou ? 'status-atualizado' : ''}" id="pedido-${pedido.id}">
                                <div class="pedido-header">
                                    <div>
                                        <div class="pedido-numero">Pedido #${pedido.id}</div>
                                        <div class="pedido-info">
                                            <i class="fas fa-calendar"></i> ${formatarData(pedido.criado_em)}
                                            | <i class="fas fa-box"></i> ${pedido.total_itens} item(ns)
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge" id="status-badge-${pedido.id}" style="background: ${statusCores[pedido.status]}">
                                            ${statusLabels[pedido.status]}
                                        </span>
                                    </div>
                                </div>
                                ${pedido.observacoes ? `
                                    <div class="pedido-info">
                                        <i class="fas fa-comment"></i> <strong>Observações:</strong> ${pedido.observacoes}
                                    </div>
                                ` : ''}
                                <div class="pedido-total">Total: ${formatarPreco(pedido.total)}</div>
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                }
            } catch (error) {
                console.error('Erro ao atualizar pedidos:', error);
            }
        }

        // Atualizar contador do carrinho
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

        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Atualizar automaticamente a cada 5 segundos
        setInterval(() => {
            atualizarPedidos();
            atualizarContadorCarrinho();
        }, 5000);

        // Primeira atualização
        atualizarContadorCarrinho();
    </script>
</body>

</html>