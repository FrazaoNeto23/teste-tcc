<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

$id_pedido = (int) ($_GET['id'] ?? 0);

// Buscar dados do pedido
$pedido = $conn->query("
    SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
    FROM pedidos p 
    JOIN usuarios u ON p.id_cliente = u.id
    WHERE p.id = $id_pedido
")->fetch_assoc();

if (!$pedido) {
    die("Pedido não encontrado!");
}

// Buscar itens do pedido
$itens = $conn->query("
    SELECT pi.*, pr.nome as produto_nome 
    FROM pedido_itens pi 
    JOIN produtos pr ON pi.id_produto = pr.id 
    WHERE pi.id_pedido = $id_pedido
")->fetch_all(MYSQLI_ASSOC);

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
    <title>Conta - Pedido #<?= $id_pedido ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }

        .header-impressao {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .logo-impressao {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info-restaurante {
            font-size: 12px;
            line-height: 1.6;
        }

        .info-pedido {
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .info-pedido table {
            width: 100%;
            font-size: 13px;
        }

        .info-pedido td {
            padding: 5px;
        }

        .info-pedido td:first-child {
            font-weight: bold;
            width: 120px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #000;
            color: #fff;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .itens-tabela {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .itens-tabela th {
            text-align: left;
            padding: 10px;
            background: #000;
            color: #fff;
            font-weight: bold;
            font-size: 12px;
        }

        .itens-tabela td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        .itens-tabela tr:last-child td {
            border-bottom: 2px solid #000;
        }

        .total-section {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .total-linha {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .total-final {
            font-size: 24px;
            font-weight: bold;
            padding-top: 15px;
            border-top: 2px solid #000;
            margin-top: 10px;
        }

        .observacoes {
            margin: 20px 0;
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }

        .observacoes strong {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .footer-impressao {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #000;
            font-size: 12px;
            line-height: 1.8;
        }

        .botoes-tela {
            text-align: center;
            margin: 30px 0;
        }

        .btn {
            padding: 12px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-voltar {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* ESTILOS DE IMPRESSÃO */
        @media print {
            body {
                padding: 0;
            }

            .botoes-tela {
                display: none;
            }

            .info-pedido,
            .total-section {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .observacoes {
                background: #fff3cd !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .itens-tabela th {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .status-badge {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <!-- BOTÕES (não aparecem na impressão) -->
    <div class="botoes-tela">
        <button onclick="window.print()" class="btn btn-imprimir">
            🖨️ IMPRIMIR CONTA
        </button>
        <a href="painel_dono.php" class="btn btn-voltar">
            ← VOLTAR
        </a>
    </div>

    <!-- CABEÇALHO -->
    <div class="header-impressao">
        <div class="logo-impressao">🍔 BURGER HOUSE</div>
        <div class="info-restaurante">
            <strong>Os Melhores Hambúrgueres da Cidade</strong><br>
            Rua das Delícias, 123 - Centro<br>
            Telefone: (11) 98765-4321<br>
            CNPJ: 12.345.678/0001-99
        </div>
    </div>

    <!-- INFORMAÇÕES DO PEDIDO -->
    <div class="info-pedido">
        <table>
            <tr>
                <td>Pedido Nº:</td>
                <td><strong>#<?= $id_pedido ?></strong></td>
            </tr>
            <tr>
                <td>Data/Hora:</td>
                <td><?= date('d/m/Y H:i:s', strtotime($pedido['criado_em'])) ?></td>
            </tr>
            <tr>
                <td>Status:</td>
                <td><span class="status-badge"><?= $status_labels[$pedido['status']] ?></span></td>
            </tr>
            <tr>
                <td>Cliente:</td>
                <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
            </tr>
            <?php if ($pedido['telefone']): ?>
                <tr>
                    <td>Telefone:</td>
                    <td><?= htmlspecialchars($pedido['telefone']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($pedido['endereco']): ?>
                <tr>
                    <td>Endereço:</td>
                    <td><?= htmlspecialchars($pedido['endereco']) ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- ITENS DO PEDIDO -->
    <table class="itens-tabela">
        <thead>
            <tr>
                <th>QTD</th>
                <th>PRODUTO</th>
                <th style="text-align: right;">PREÇO UN.</th>
                <th style="text-align: right;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $item): ?>
                <tr>
                    <td style="text-align: center;"><strong><?= $item['quantidade'] ?>x</strong></td>
                    <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                    <td style="text-align: right;"><?= formatar_preco($item['preco_unitario']) ?></td>
                    <td style="text-align: right;">
                        <strong><?= formatar_preco($item['preco_unitario'] * $item['quantidade']) ?></strong>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- OBSERVAÇÕES -->
    <?php if ($pedido['observacoes']): ?>
        <div class="observacoes">
            <strong>⚠️ OBSERVAÇÕES DO CLIENTE:</strong>
            <?= htmlspecialchars($pedido['observacoes']) ?>
        </div>
    <?php endif; ?>

    <!-- TOTAL -->
    <div class="total-section">
        <div class="total-linha">
            <span>Subtotal:</span>
            <span><?= formatar_preco($pedido['total']) ?></span>
        </div>
        <div class="total-linha">
            <span>Taxa de Serviço (10%):</span>
            <span><?= formatar_preco($pedido['total'] * 0.10) ?></span>
        </div>
        <div class="total-linha total-final">
            <span>TOTAL A PAGAR:</span>
            <span><?= formatar_preco($pedido['total'] * 1.10) ?></span>
        </div>
    </div>

    <!-- RODAPÉ -->
    <div class="footer-impressao">
        <strong>OBRIGADO PELA PREFERÊNCIA!</strong><br>
        Volte sempre! 🍔❤️<br>
        <br>
        <small>
            Este documento não é um documento fiscal.<br>
            Consulte nosso cardápio completo em nosso site.
        </small>
    </div>

    <script>
        // Auto-imprimir ao carregar (opcional - comente se não quiser)
        // window.onload = function() { window.print(); };
    </script>
</body>

</html>
