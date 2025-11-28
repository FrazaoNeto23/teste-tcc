<?php
session_start();
include "config.php";
include "helpers.php";

// Verificar se há mesa na URL
$id_mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 0;

if ($id_mesa == 0) {
    redirecionar('index.php', 'Mesa não identificada!', 'erro');
}

// Buscar dados da mesa
$mesa = $conn->query("SELECT * FROM mesas WHERE id=$id_mesa AND ativa=1")->fetch_assoc();

if (!$mesa) {
    redirecionar('index.php', 'Mesa não encontrada!', 'erro');
}

// Se não estiver logado como cliente, criar sessão de mesa temporária
if (!isset($_SESSION['usuario'])) {
    $_SESSION['mesa_temporaria'] = $id_mesa;
    $_SESSION['tipo_pedido'] = 'mesa';
}

// ADICIONAR AO CARRINHO
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = (int)$_POST['id_produto'];
    $quantidade = (int)$_POST['quantidade'];
    
    // Verificar se produto existe
    $produto = $conn->query("SELECT * FROM produtos WHERE id=$id_produto AND disponivel=1")->fetch_assoc();
    
    if ($produto) {
        // Armazenar no carrinho de sessão (não precisa de login)
        if (!isset($_SESSION['carrinho_mesa'])) {
            $_SESSION['carrinho_mesa'] = [];
        }
        
        // Verificar se já existe no carrinho
        $existe = false;
        foreach ($_SESSION['carrinho_mesa'] as $key => $item) {
            if ($item['id_produto'] == $id_produto) {
                $_SESSION['carrinho_mesa'][$key]['quantidade'] += $quantidade;
                $existe = true;
                break;
            }
        }
        
        if (!$existe) {
            $_SESSION['carrinho_mesa'][] = [
                'id_produto' => $id_produto,
                'quantidade' => $quantidade,
                'preco' => $produto['preco'],
                'nome' => $produto['nome'],
                'descricao' => $produto['descricao'],
                'imagem' => $produto['imagem']
            ];
        }
        
        redirecionar("pedido_mesa.php?mesa=$id_mesa", 'Produto adicionado!');
    }
}

// REMOVER ITEM
if (isset($_GET['remover'])) {
    $index = (int)$_GET['remover'];
    if (isset($_SESSION['carrinho_mesa'][$index])) {
        unset($_SESSION['carrinho_mesa'][$index]);
        $_SESSION['carrinho_mesa'] = array_values($_SESSION['carrinho_mesa']); // Reindexar
    }
    redirecionar("pedido_mesa.php?mesa=$id_mesa", 'Item removido!');
}

// LIMPAR CARRINHO
if (isset($_GET['limpar'])) {
    $_SESSION['carrinho_mesa'] = [];
    redirecionar("pedido_mesa.php?mesa=$id_mesa", 'Carrinho limpo!');
}

// FINALIZAR PEDIDO
if (isset($_POST['finalizar_pedido'])) {
    $nome_cliente = sanitizar_texto($_POST['nome_cliente']);
    $observacoes = sanitizar_texto($_POST['observacoes'] ?? '');
    
    if (empty($nome_cliente)) {
        redirecionar("pedido_mesa.php?mesa=$id_mesa", 'Por favor, informe seu nome!', 'erro');
    }
    
    $carrinho = $_SESSION['carrinho_mesa'] ?? [];
    
    if (empty($carrinho)) {
        redirecionar("pedido_mesa.php?mesa=$id_mesa", 'Carrinho vazio!', 'erro');
    }
    
    // Calcular total
    $total = 0;
    foreach ($carrinho as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }
    
    // Criar cliente temporário ou usar existente
    $id_cliente = $_SESSION['id_usuario'] ?? null;
    
    if (!$id_cliente) {
        // Criar cliente temporário
        $email_temp = "mesa" . $id_mesa . "_" . time() . "@temp.com";
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
        $senha_temp = hash_senha('temp123');
        $stmt->bind_param("sss", $nome_cliente, $email_temp, $senha_temp);
        $stmt->execute();
        $id_cliente = $conn->insert_id;
    }
    
    // Criar pedido
    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, id_mesa, tipo_pedido, total, observacoes, status) VALUES (?, ?, 'mesa', ?, ?, 'pendente')");
    $stmt->bind_param("iids", $id_cliente, $id_mesa, $total, $observacoes);
    $stmt->execute();
    $id_pedido = $conn->insert_id;
    
    // Adicionar itens ao pedido
    foreach ($carrinho as $item) {
        $stmt = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_pedido, $item['id_produto'], $item['quantidade'], $item['preco']);
        $stmt->execute();
    }
    
    // Marcar mesa como ocupada
    $conn->query("UPDATE mesas SET ocupada=1 WHERE id=$id_mesa");
    
    // Limpar carrinho
    $_SESSION['carrinho_mesa'] = [];
    
    redirecionar("acompanhar_pedido_mesa.php?pedido=$id_pedido&mesa=$id_mesa", 'Pedido realizado com sucesso!');
}

// Buscar produtos
$produtos = $conn->query("SELECT * FROM produtos WHERE disponivel=1 ORDER BY categoria, nome")->fetch_all(MYSQLI_ASSOC);
$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE disponivel=1 ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);

// Calcular total do carrinho
$carrinho = $_SESSION['carrinho_mesa'] ?? [];
$total = 0;
foreach ($carrinho as $item) {
    $total += $item['preco'] * $item['quantidade'];
}

$msg = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? '';
$msg_tipo = isset($_SESSION['sucesso']) ? 'success' : 'error';
unset($_SESSION['sucesso'], $_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa <?= $mesa['numero'] ?> - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</div>
            <div class="mesa-badge">
                <i class="fas fa-chair"></i> MESA <?= $mesa['numero'] ?>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?>">
                <i class="fas fa-<?= $msg_tipo == 'success' ? 'check' : 'exclamation' ?>-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h1><i class="fas fa-utensils"></i> Bem-vindo à Mesa <?= $mesa['numero'] ?>!</h1>
            <p>Faça seu pedido diretamente da mesa - é rápido e fácil!</p>
        </div>

        <!-- CARRINHO FLUTUANTE -->
        <?php if (!empty($carrinho)): ?>
            <div class="carrinho-flutuante">
                <button onclick="toggleCarrinho()" class="btn-carrinho">
                    <i class="fas fa-shopping-cart"></i>
                    Carrinho (<?= count($carrinho) ?>) - <?= formatar_preco($total) ?>
                </button>
            </div>

            <div class="modal-carrinho" id="modalCarrinho">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-shopping-cart"></i> Seu Pedido</h2>
                        <button onclick="toggleCarrinho()" class="btn-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="modal-body">
                        <?php foreach ($carrinho as $index => $item): ?>
                            <div class="cart-item-mini">
                                <div class="item-info">
                                    <div class="item-nome"><?= htmlspecialchars($item['nome']) ?></div>
                                    <div class="item-preco"><?= $item['quantidade'] ?>x <?= formatar_preco($item['preco']) ?></div>
                                </div>
                                <a href="?mesa=<?= $id_mesa ?>&remover=<?= $index ?>" class="btn-remove">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="total-carrinho">
                            <strong>Total:</strong>
                            <strong><?= formatar_preco($total) ?></strong>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <form method="POST" style="width:100%;">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Seu Nome</label>
                                <input type="text" name="nome_cliente" required placeholder="Digite seu nome">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-comment"></i> Observações (opcional)</label>
                                <textarea name="observacoes" rows="2" placeholder="Alguma observação sobre o pedido?"></textarea>
                            </div>
                            
                            <button type="submit" name="finalizar_pedido" class="btn btn-success" style="width:100%;">
                                <i class="fas fa-check"></i> Finalizar Pedido
                            </button>
                        </form>
                        
                        <a href="?mesa=<?= $id_mesa ?>&limpar" class="btn btn-danger" style="width:100%;margin-top:10px;" onclick="return confirm('Limpar carrinho?')">
                            <i class="fas fa-trash"></i> Limpar Carrinho
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PRODUTOS POR CATEGORIA -->
        <?php foreach ($categorias as $cat): ?>
            <?php 
            $categoria = $cat['categoria'];
            $produtos_cat = array_filter($produtos, function($p) use ($categoria) {
                return $p['categoria'] == $categoria;
            });
            ?>
            
            <div class="card">
                <h2><i class="fas fa-tag"></i> <?= htmlspecialchars($categoria) ?></h2>
                
                <div class="produtos-grid">
                    <?php foreach ($produtos_cat as $p): ?>
                        <div class="produto-card">
                            <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" class="produto-imagem">
                            <?php else: ?>
                                <div class="produto-imagem" style="display:flex;align-items:center;justify-content:center;background:#f5f5f5;">
                                    <i class="fas fa-hamburger" style="font-size:64px;color:#ccc;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="produto-info">
                                <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                                <div class="produto-descricao"><?= htmlspecialchars($p['descricao']) ?></div>
                                <div class="produto-footer">
                                    <div class="produto-preco"><?= formatar_preco($p['preco']) ?></div>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="quantidade" value="1">
                                        <button type="submit" name="adicionar_carrinho" class="btn btn-success">
                                            <i class="fas fa-plus"></i> Adicionar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
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

        .carrinho-flutuante {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
        }

        .btn-carrinho {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-carrinho:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.6);
        }

        .modal-carrinho {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-carrinho.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: scaleIn 0.3s ease;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .btn-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .cart-item-mini {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f9f9f9;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            background: #fecaca;
        }

        .total-carrinho {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            border-top: 2px solid #e5e7eb;
            margin-top: 16px;
            font-size: 20px;
        }

        .modal-footer {
            padding: 24px;
            border-top: 2px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .carrinho-flutuante {
                left: 20px;
                right: 20px;
            }

            .btn-carrinho {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                max-height: 95vh;
            }
        }
    </style>

    <script>
        function toggleCarrinho() {
            const modal = document.getElementById('modalCarrinho');
            modal.classList.toggle('active');
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalCarrinho')?.addEventListener('click', function(e) {
            if (e.target === this) {
                toggleCarrinho();
            }
        });
    </script>
</body>
</html>
