<?php
/**
 * SCRIPT DE LIMPEZA AUTOMÁTICA DE PEDIDOS
 * 
 * Este script pode ser executado de 3 formas:
 * 
 * 1. CRON JOB (recomendado):
 *    Adicione no crontab do servidor:
 *    0 0 * * * php /caminho/para/limpar_pedidos.php
 *    (Executa todo dia à meia-noite)
 * 
 * 2. AUTOMÁTICO NO PAINEL:
 *    O painel do dono já chama este script automaticamente
 *    quando detecta que é um novo dia
 * 
 * 3. MANUAL:
 *    Acesse: limpar_pedidos.php?manual=1&confirmar=SIM
 */

include "config.php";

// Configurações
$DIAS_PARA_MANTER = 0; // 0 = apaga tudo do dia anterior, 1 = mantém 1 dia, etc.
$ARQUIVAR = false; // Se true, move para tabela de histórico ao invés de deletar

// Verificar se é execução manual
$manual = isset($_GET['manual']);
$confirmar = ($_GET['confirmar'] ?? '') === 'SIM';

// Se for manual sem confirmação, mostrar página de confirmação
if ($manual && !$confirmar) {
    mostrarPaginaConfirmacao();
    exit;
}

// Executar limpeza
$resultado = limparPedidosAntigos($conn, $DIAS_PARA_MANTER, $ARQUIVAR);

// Se for manual, mostrar resultado na tela
if ($manual) {
    mostrarResultado($resultado);
    exit;
}

// Se for CRON ou chamada interna, retornar resultado
return $resultado;

// ============================================
// FUNÇÕES
// ============================================

function limparPedidosAntigos($conn, $dias_manter = 0, $arquivar = false) {
    $resultado = [
        'sucesso' => false,
        'pedidos_removidos' => 0,
        'itens_removidos' => 0,
        'mensagem' => ''
    ];
    
    try {
        // Calcular data de corte
        $data_corte = date('Y-m-d', strtotime("-$dias_manter days"));
        
        // Contar pedidos que serão afetados
        $count = $conn->query("
            SELECT COUNT(*) as total FROM pedidos 
            WHERE DATE(criado_em) < '$data_corte'
        ")->fetch_assoc()['total'];
        
        if ($count == 0) {
            $resultado['sucesso'] = true;
            $resultado['mensagem'] = 'Nenhum pedido antigo para limpar.';
            return $resultado;
        }
        
        // Buscar IDs dos pedidos antigos
        $pedidos_ids = $conn->query("
            SELECT id FROM pedidos 
            WHERE DATE(criado_em) < '$data_corte'
        ")->fetch_all(MYSQLI_ASSOC);
        
        $ids = array_column($pedidos_ids, 'id');
        $ids_str = implode(',', $ids);
        
        if ($arquivar) {
            // Criar tabela de histórico se não existir
            $conn->query("
                CREATE TABLE IF NOT EXISTS pedidos_historico LIKE pedidos
            ");
            $conn->query("
                CREATE TABLE IF NOT EXISTS pedido_itens_historico LIKE pedido_itens
            ");
            
            // Mover pedidos para histórico
            $conn->query("
                INSERT INTO pedidos_historico 
                SELECT * FROM pedidos WHERE id IN ($ids_str)
            ");
            
            // Mover itens para histórico
            $conn->query("
                INSERT INTO pedido_itens_historico 
                SELECT * FROM pedido_itens WHERE id_pedido IN ($ids_str)
            ");
        }
        
        // Contar itens que serão removidos
        $itens_count = $conn->query("
            SELECT COUNT(*) as total FROM pedido_itens 
            WHERE id_pedido IN ($ids_str)
        ")->fetch_assoc()['total'];
        
        // Deletar itens dos pedidos (CASCADE deveria fazer isso, mas garantir)
        $conn->query("DELETE FROM pedido_itens WHERE id_pedido IN ($ids_str)");
        
        // Deletar pedidos
        $conn->query("DELETE FROM pedidos WHERE id IN ($ids_str)");
        
        // Limpar carrinho de itens órfãos (opcional)
        $conn->query("DELETE FROM carrinho WHERE adicionado_em < '$data_corte'");
        
        // Registrar log
        $log_msg = date('Y-m-d H:i:s') . " - Limpeza: $count pedidos, $itens_count itens removidos\n";
        file_put_contents('logs/limpeza.log', $log_msg, FILE_APPEND);
        
        $resultado['sucesso'] = true;
        $resultado['pedidos_removidos'] = $count;
        $resultado['itens_removidos'] = $itens_count;
        $resultado['mensagem'] = "Limpeza concluída! $count pedidos e $itens_count itens removidos.";
        
    } catch (Exception $e) {
        $resultado['mensagem'] = 'Erro: ' . $e->getMessage();
    }
    
    return $resultado;
}

function mostrarPaginaConfirmacao() {
    global $conn;
    
    // Contar pedidos que serão afetados
    $hoje = date('Y-m-d');
    $count = $conn->query("
        SELECT COUNT(*) as total FROM pedidos 
        WHERE DATE(criado_em) < '$hoje'
    ")->fetch_assoc()['total'];
    
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Limpar Pedidos - Burger House</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="auth-page">
        <div class="login-container" style="max-width: 500px;">
            <div class="logo"><i class="fas fa-broom"></i></div>
            <h2>Limpar Pedidos Antigos</h2>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atenção!</strong> Esta ação é irreversível.
            </div>
            
            <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 12px; margin-bottom: 20px;">
                <div style="font-size: 48px; font-weight: 800; color: #e74c3c;"><?= $count ?></div>
                <div style="color: #666;">pedidos serão removidos</div>
                <small style="color: #999;">(do dia anterior e anteriores)</small>
            </div>
            
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Os pedidos de <strong>hoje</strong> serão mantidos.
            </p>
            
            <div style="display: flex; gap: 12px;">
                <a href="painel_dono.php" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <a href="?manual=1&confirmar=SIM" class="btn btn-danger" style="flex: 1;" 
                   onclick="return confirm('Tem certeza? Esta ação não pode ser desfeita!')">
                    <i class="fas fa-trash"></i> Confirmar Limpeza
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function mostrarResultado($resultado) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Resultado - Burger House</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="auth-page">
        <div class="login-container" style="max-width: 500px;">
            <div class="logo">
                <i class="fas fa-<?= $resultado['sucesso'] ? 'check-circle' : 'times-circle' ?>" 
                   style="color: <?= $resultado['sucesso'] ? '#2ecc71' : '#e74c3c' ?>"></i>
            </div>
            <h2><?= $resultado['sucesso'] ? 'Limpeza Concluída!' : 'Erro na Limpeza' ?></h2>
            
            <?php if ($resultado['sucesso']): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check"></i> <?= $resultado['mensagem'] ?>
                </div>
                
                <?php if ($resultado['pedidos_removidos'] > 0): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 20px; background: #d5f5e3; border-radius: 12px;">
                        <div style="font-size: 32px; font-weight: 800; color: #1e8449;">
                            <?= $resultado['pedidos_removidos'] ?>
                        </div>
                        <div style="color: #1e8449;">Pedidos</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d4e6f1; border-radius: 12px;">
                        <div style="font-size: 32px; font-weight: 800; color: #1a5276;">
                            <?= $resultado['itens_removidos'] ?>
                        </div>
                        <div style="color: #1a5276;">Itens</div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-error">
                    <i class="fas fa-times"></i> <?= $resultado['mensagem'] ?>
                </div>
            <?php endif; ?>
            
            <a href="painel_dono.php" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-arrow-left"></i> Voltar ao Painel
            </a>
        </div>
    </body>
    </html>
    <?php
}
?>
