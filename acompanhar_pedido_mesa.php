<?php
session_start();
include "config.php";
include "helpers.php";

$id_pedido = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
$id_mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 0;

if ($id_pedido == 0) {
    redirecionar('index.php', 'Pedido não encontrado!', 'erro');
}

// Solicitar conta
if (isset($_POST['solicitar_conta'])) {
    $conn->query("UPDATE pedidos SET conta_solicitada=1, conta_solicitada_em=NOW() WHERE id=$id_pedido");
    redirecionar("acompanhar_pedido_mesa.php?pedido=$id_pedido&mesa=$id_mesa", 'Conta solicitada! O garçom será avisado.');
}

// Buscar dados do pedido
$pedido = $conn->query("
    SELECT p.*, m.numero as mesa_numero, u.nome as cliente_nome
    FROM pedidos p
    LEFT JOIN mesas m ON p.id_mesa = m.id
    LEFT JOIN usuarios u ON p.id_cliente = u.id
    WHERE p.id = $id_pedido
")->fetch_assoc();

if (!$pedido) {
    redirecionar('index.php', 'Pedido não encontrado!', 'erro');
}

// Buscar itens do pedido
$itens = $conn->query("
    SELECT pi.*, pr.nome as produto_nome, pr.descricao
    FROM pedido_itens pi
    JOIN produtos pr ON pi.id_produto = pr.id
    WHERE pi.id_pedido = $id_pedido
")->fetch_all(MYSQLI_ASSOC);

$status_info = [
    'pendente' => ['cor' => '#ffc107', 'icone' => 'clock', 'texto' => 'Pedido Recebido', 'desc' => 'Aguardando confirmação da cozinha'],
    'preparando' => ['cor' => '#17a2b8', 'icone' => 'fire', 'texto' => 'Em Preparo', 'desc' => 'Nossa equipe está preparando seu pedido com carinho'],
    'pronto' => ['cor' => '#28a745', 'icone' => 'check-circle', 'texto' => 'Pronto!', 'desc' => 'Seu pedido está pronto e será servido em breve'],
    'entregue' => ['cor' => '#6c757d', 'icone' => 'utensils', 'texto' => 'Servido', 'desc' => 'Bom apetite! Aproveite sua refeição'],
    'cancelado' => ['cor' => '#dc3545', 'icone' => 'times-circle', 'texto' => 'Cancelado', 'desc' => 'Este pedido foi cancelado']
];

$info = $status_info[$pedido['status']];

$msg = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? '';
$msg_tipo = isset($_SESSION['sucesso']) ? 'success' : 'error';
unset($_SESSION['sucesso'], $_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido - Mesa <?= $pedido['mesa_numero'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</div>
            <div class="mesa-badge">
                <i class="fas fa-chair"></i> MESA <?= $pedido['mesa_numero'] ?>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?>">
                <i class="fas fa-<?= $msg_tipo == 'success' ? 'check' : 'exclamation' ?>-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- STATUS DO PEDIDO -->
        <div class="card" style="text-align:center;">
            <div class="status-grande" style="color: <?= $info['cor'] ?>;">
                <i class="fas fa-<?= $info['icone'] ?>" style="font-size: 80px; margin-bottom: 20px;"></i>
                <h1 style="margin: 0;"><?= $info['texto'] ?></h1>
                <p style="font-size: 18px; color: #666; margin-top: 10px;"><?= $info['desc'] ?></p>
            </div>
        </div>

        <!-- INFORMAÇÕES DO PEDIDO -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Informações do Pedido</h2>
            
            <div class="pedido-info-grid">
                <div class="info-item">
                    <i class="fas fa-hashtag"></i>
                    <div>
                        <strong>Número do Pedido</strong>
                        <p>#<?= $pedido['id'] ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong>Cliente</strong>
                        <p><?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Horário</strong>
                        <p id="horario"><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-chair"></i>
                    <div>
                        <strong>Mesa</strong>
                        <p><?= $pedido['mesa_numero'] ?></p>
                    </div>
                </div>
            </div>
            
            <?php if ($pedido['observacoes']): ?>
                <div class="observacoes-box">
                    <i class="fas fa-comment"></i>
                    <strong>Observações:</strong> <?= htmlspecialchars($pedido['observacoes']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ITENS DO PEDIDO -->
        <div class="card">
            <h2><i class="fas fa-utensils"></i> Itens do Pedido</h2>
            
            <div class="lista-itens-pedido">
                <?php foreach ($itens as $item): ?>
                    <div class="item-pedido-card">
                        <div class="item-qtd"><?= $item['quantidade'] ?>x</div>
                        <div class="item-detalhes">
                            <strong><?= htmlspecialchars($item['produto_nome']) ?></strong>
                            <p><?= htmlspecialchars($item['descricao']) ?></p>
                        </div>
                        <div class="item-valor">
                            <?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="total-pedido">
                <strong>TOTAL</strong>
                <strong><?= formatar_preco($pedido['total']) ?></strong>
            </div>
        </div>

        <!-- SOLICITAR CONTA -->
        <?php if (!$pedido['conta_solicitada'] && in_array($pedido['status'], ['pronto', 'entregue'])): ?>
            <div class="card">
                <h2><i class="fas fa-receipt"></i> Solicitar Conta</h2>
                <p>Finalizou sua refeição? Solicite a conta e nosso garçom levará até sua mesa!</p>
                
                <form method="POST" onsubmit="return confirm('Solicitar a conta agora?')">
                    <button type="submit" name="solicitar_conta" class="btn btn-success btn-grande">
                        <i class="fas fa-hand-paper"></i> Solicitar Conta
                    </button>
                </form>
            </div>
        <?php elseif ($pedido['conta_solicitada']): ?>
            <div class="card conta-solicitada-box">
                <div style="text-align:center;">
                    <i class="fas fa-check-circle" style="font-size: 60px; color: #10b981; margin-bottom: 16px;"></i>
                    <h2 style="margin: 0; color: #10b981;">Conta Solicitada!</h2>
                    <p style="font-size: 16px; color: #666; margin-top: 10px;">
                        Nosso garçom foi notificado e levará a conta até sua mesa em instantes.
                    </p>
                    <p style="font-size: 14px; color: #999; margin-top: 8px;">
                        Solicitado em: <?= date('d/m/Y H:i', strtotime($pedido['conta_solicitada_em'])) ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- BOTÃO FAZER NOVO PEDIDO -->
        <?php if ($id_mesa > 0): ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="pedido_mesa.php?mesa=<?= $id_mesa ?>" class="btn btn-primary btn-grande">
                    <i class="fas fa-plus-circle"></i> Fazer Novo Pedido
                </a>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .mesa-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
        }

        .pedido-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 20px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            border-radius: 12px;
            border-left: 4px solid #6366f1;
        }

        .info-item i {
            font-size: 32px;
            color: #6366f1;
            min-width: 32px;
        }

        .info-item strong {
            display: block;
            color: #374151;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .info-item p {
            margin: 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }

        .observacoes-box {
            margin-top: 20px;
            padding: 16px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .observacoes-box i {
            font-size: 24px;
            color: #d97706;
        }

        .lista-itens-pedido {
            margin-top: 20px;
        }

        .item-pedido-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 12px;
            border: 2px solid #e5e7eb;
        }

        .item-qtd {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 18px;
            min-width: 70px;
            text-align: center;
        }

        .item-detalhes {
            flex: 1;
        }

        .item-detalhes strong {
            display: block;
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .item-detalhes p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .item-valor {
            font-size: 20px;
            font-weight: 800;
            color: #10b981;
        }

        .total-pedido {
            display: flex;
            justify-content: space-between;
            padding: 24px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 24px;
            font-weight: 800;
        }

        .btn-grande {
            width: 100%;
            padding: 20px;
            font-size: 20px;
            margin-top: 20px;
        }

        .conta-solicitada-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 3px solid #10b981;
        }

        @media (max-width: 768px) {
            .pedido-info-grid {
                grid-template-columns: 1fr;
            }

            .item-pedido-card {
                flex-direction: column;
                text-align: center;
            }

            .item-qtd {
                width: 100%;
            }
        }
    </style>

    <script>
        // Atualizar status do pedido automaticamente
        async function atualizarStatus() {
            try {
                const response = await fetch('ajax_handler.php?action=buscar_status_pedido&id=<?= $id_pedido ?>');
                const data = await response.json();
                
                if (data.pedido) {
                    const statusAtual = '<?= $pedido['status'] ?>';
                    const statusNovo = data.pedido.status;
                    
                    if (statusAtual !== statusNovo) {
                        location.reload();
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar status:', error);
            }
        }

        // Atualizar a cada 5 segundos
        setInterval(atualizarStatus, 5000);
    </script>
</body>
</html>
