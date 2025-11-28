<?php
session_start();
include "config.php";
include "helpers.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // Buscar pedidos para o painel do dono
    case 'buscar_pedidos_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $pedidos = $conn->query("
            SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco, m.numero as mesa_numero
            FROM pedidos p 
            JOIN usuarios u ON p.id_cliente = u.id
            LEFT JOIN mesas m ON p.id_mesa = m.id
            ORDER BY p.conta_solicitada DESC, p.criado_em DESC 
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

        echo json_encode(['pedidos' => $pedidos]);
        break;

    // Buscar estatísticas para o painel do dono
    case 'buscar_stats_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $stats = $conn->query("
            SELECT 
                COUNT(DISTINCT id) as total_pedidos,
                SUM(total) as faturamento_total,
                SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
                SUM(CASE WHEN conta_solicitada=1 THEN 1 ELSE 0 END) as contas_solicitadas
            FROM pedidos
        ")->fetch_assoc();

        $total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
        $total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];

        echo json_encode([
            'stats' => $stats,
            'total_produtos' => $total_produtos,
            'total_clientes' => $total_clientes
        ]);
        break;

    // Atualizar status do pedido (AJAX)
    case 'atualizar_status':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $id_pedido = (int) $_POST['id_pedido'];
        $status = $conn->real_escape_string($_POST['status']);

        $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");

        echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado!']);
        break;

    // Confirmar entrega da conta
    case 'confirmar_conta_entregue':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $id_pedido = (int) $_POST['id_pedido'];
        
        // Marcar conta como não solicitada (foi entregue)
        $conn->query("UPDATE pedidos SET conta_solicitada=0 WHERE id=$id_pedido");

        echo json_encode(['sucesso' => true, 'mensagem' => 'Conta marcada como entregue!']);
        break;

    // Buscar pedidos do cliente
    case 'buscar_pedidos_cliente':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $id_cliente = $_SESSION['id_usuario'];

        $pedidos = $conn->query("
            SELECT p.*, COUNT(pi.id) as total_itens 
            FROM pedidos p 
            LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido 
            WHERE p.id_cliente = $id_cliente 
            GROUP BY p.id 
            ORDER BY p.criado_em DESC
        ")->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['pedidos' => $pedidos]);
        break;

    // Buscar contagem do carrinho
    case 'contar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['count' => 0]);
            exit;
        }

        $id_cliente = $_SESSION['id_usuario'];
        $count = $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$id_cliente")->fetch_assoc()['t'];

        echo json_encode(['count' => $count]);
        break;

    // Atualizar quantidade no carrinho (AJAX)
    case 'atualizar_quantidade_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $id_cliente = $_SESSION['id_usuario'];
        $id_carrinho = (int) $_POST['id_carrinho'];
        $quantidade = (int) $_POST['quantidade'];

        if ($quantidade > 0) {
            $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente");
        } else {
            $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente");
        }

        // Recalcular total
        $itens = $conn->query("
            SELECT c.quantidade, p.preco 
            FROM carrinho c 
            JOIN produtos p ON c.id_produto = p.id 
            WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
        ")->fetch_all(MYSQLI_ASSOC);

        $total = 0;
        foreach ($itens as $item) {
            $total += $item['preco'] * $item['quantidade'];
        }

        $count = count($itens);

        echo json_encode([
            'sucesso' => true,
            'total' => $total,
            'total_formatado' => 'R$ ' . number_format($total, 2, ',', '.'),
            'count' => $count
        ]);
        break;

    // Buscar itens do carrinho
    case 'buscar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']);
            exit;
        }

        $id_cliente = $_SESSION['id_usuario'];

        $itens = $conn->query("
            SELECT c.*, p.nome, p.descricao, p.preco, p.imagem 
            FROM carrinho c 
            JOIN produtos p ON c.id_produto = p.id 
            WHERE c.id_cliente = $id_cliente AND p.disponivel = 1
        ")->fetch_all(MYSQLI_ASSOC);

        $total = 0;
        foreach ($itens as $item) {
            $total += $item['preco'] * $item['quantidade'];
        }

        echo json_encode([
            'itens' => $itens,
            'total' => $total,
            'total_formatado' => 'R$ ' . number_format($total, 2, ',', '.')
        ]);
        break;

    // Buscar status do pedido
    case 'buscar_status_pedido':
        $id_pedido = (int) ($_GET['id'] ?? 0);
        
        if ($id_pedido == 0) {
            echo json_encode(['erro' => 'ID inválido']);
            exit;
        }

        $pedido = $conn->query("SELECT * FROM pedidos WHERE id=$id_pedido")->fetch_assoc();
        
        echo json_encode(['pedido' => $pedido]);
        break;

    default:
        echo json_encode(['erro' => 'Ação não reconhecida']);
}
?>
