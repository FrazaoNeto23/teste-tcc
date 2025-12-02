<?php
/**
 * SCRIPT DE LIMPEZA AUTOMÁTICA DE PEDIDOS FINALIZADOS
 * 
 * Este script remove pedidos com status ENTREGUE ou CANCELADO do banco de dados.
 * NÃO usa data como critério - remove apenas por status.
 * 
 * COMO USAR:
 * 
 * 1. EXECUÇÃO MANUAL:
 *    - Acesse: http://seusite.com/limpar_pedidos_antigos.php
 * 
 * 2. EXECUÇÃO AUTOMÁTICA (CRON JOB):
 *    - No cPanel ou terminal do servidor, configure um cron job:
 *    - Diariamente à meia-noite: 0 0 * * * php /caminho/para/limpar_pedidos_antigos.php
 *    - Semanalmente: 0 0 * * 0 php /caminho/para/limpar_pedidos_antigos.php
 * 
 * 3. EXECUÇÃO AUTOMÁTICA (INCLUIR EM OUTRAS PÁGINAS):
 *    - Adicione no topo do painel_dono.php:
 *    - include_once "limpar_pedidos_antigos.php";
 */

include "config.php";

// CONFIGURAÇÕES
define('EXECUTAR_AUTOMATICAMENTE', true); // Se false, só executa com ?executar=1 na URL

// Verificar se deve executar
$deve_executar = EXECUTAR_AUTOMATICAMENTE || isset($_GET['executar']);

if (!$deve_executar) {
    die("Script de limpeza não executado. Use ?executar=1 para forçar execução.");
}

// =======================
// INÍCIO DA LIMPEZA
// =======================

// Contar pedidos a serem removidos (ENTREGUES e CANCELADOS)
$resultado_contagem = $conn->query("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE status IN ('entregue', 'cancelado')
");
$total_remover = $resultado_contagem->fetch_assoc()['total'];

if ($total_remover == 0) {
    echo "✅ Nenhum pedido finalizado para remover.<br>";
    echo "ℹ️ Apenas pedidos com status ENTREGUE ou CANCELADO são removidos.";
    exit;
}

// Buscar IDs dos pedidos a serem removidos
$resultado_ids = $conn->query("
    SELECT id 
    FROM pedidos 
    WHERE status IN ('entregue', 'cancelado')
");

$ids_remover = [];
while ($row = $resultado_ids->fetch_assoc()) {
    $ids_remover[] = $row['id'];
}

if (empty($ids_remover)) {
    echo "✅ Nenhum pedido finalizado para remover.";
    exit;
}

$ids_string = implode(',', $ids_remover);

// Iniciar transação
$conn->begin_transaction();

try {
    // 1. Remover itens dos pedidos
    $sql_itens = "DELETE FROM pedido_itens WHERE id_pedido IN ($ids_string)";
    $conn->query($sql_itens);
    $itens_removidos = $conn->affected_rows;

    // 2. Remover pedidos
    $sql_pedidos = "DELETE FROM pedidos WHERE id IN ($ids_string)";
    $conn->query($sql_pedidos);
    $pedidos_removidos = $conn->affected_rows;

    // Confirmar transação
    $conn->commit();

    // Registrar log de limpeza
    $log_file = 'limpeza_pedidos.log';
    $log_message = date('Y-m-d H:i:s') . " - Limpeza executada: $pedidos_removidos pedidos finalizados (entregues/cancelados) e $itens_removidos itens removidos\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);

    // Exibir resultado
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Limpeza de Pedidos</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .card {
                background: white;
                padding: 40px;
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            }
            h1 { color: #10b981; margin-bottom: 20px; }
            .stats {
                background: #f0fdf4;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .stats-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #d1fae5;
            }
            .stats-item:last-child { border-bottom: none; }
            .stats-label { font-weight: 600; }
            .stats-value { color: #10b981; font-weight: bold; }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: bold;
                margin-top: 20px;
            }
            .btn:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>✅ Limpeza Concluída com Sucesso!</h1>
            
            <div class='stats'>
                <div class='stats-item'>
                    <span class='stats-label'>📦 Pedidos removidos:</span>
                    <span class='stats-value'>$pedidos_removidos</span>
                </div>
                <div class='stats-item'>
                    <span class='stats-label'>🍔 Itens removidos:</span>
                    <span class='stats-value'>$itens_removidos</span>
                </div>
                <div class='stats-item'>
                    <span class='stats-label'>📋 Critério:</span>
                    <span class='stats-value'>Status ENTREGUE ou CANCELADO</span>
                </div>
                <div class='stats-item'>
                    <span class='stats-label'>⏱️ Executado em:</span>
                    <span class='stats-value'>" . date('d/m/Y H:i:s') . "</span>
                </div>
            </div>

            <p><strong>ℹ️ Informação:</strong> Pedidos com status ENTREGUE ou CANCELADO foram removidos automaticamente.</p>
            
            <a href='painel_dono.php' class='btn'>← Voltar ao Painel</a>
        </div>
    </body>
    </html>";

} catch (Exception $e) {
    // Reverter transação em caso de erro
    $conn->rollback();

    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>Erro na Limpeza</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }
            .card {
                background: white;
                padding: 40px;
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            }
            h1 { color: #ef4444; }
            .error {
                background: #fee2e2;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #ef4444;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>❌ Erro na Limpeza</h1>
            <div class='error'>
                <strong>Erro:</strong> " . $e->getMessage() . "
            </div>
            <p>A limpeza foi cancelada e nenhum dado foi removido.</p>
        </div>
    </body>
    </html>";

    // Registrar erro no log
    $log_file = 'limpeza_pedidos.log';
    $log_message = date('Y-m-d H:i:s') . " - ERRO: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

$conn->close();
?>
