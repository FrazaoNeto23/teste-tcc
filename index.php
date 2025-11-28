<?php
session_status() === PHP_SESSION_NONE && session_start();
include "config.php";
include_once "helpers.php";

// Filtros
$categoria = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$usuario_id = $_SESSION['id_usuario'] ?? 0;

// Query dinâmica com filtros
$where = ["disponivel = 1"];
$params = [];
if ($categoria) {
    $where[] = "categoria = ?";
    $params[] = $categoria;
}
if ($busca) {
    $where[] = "(nome LIKE ? OR descricao LIKE ?)";
    $busca_param = "%$busca%";
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$sql = "SELECT * FROM produtos WHERE " . implode(" AND ", $where) . " ORDER BY categoria, nome";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Agrupar por categoria
$produtos_por_categoria = [];
foreach ($produtos as $p) {
    $cat = $p['categoria'] ?: 'Outros';
    $produtos_por_categoria[$cat][] = $p;
}

// Categorias e contador carrinho
$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE disponivel=1 AND categoria IS NOT NULL AND categoria!='' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$cart_count = $usuario_id ? $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$usuario_id")->fetch_assoc()['t'] : 0;

// Mensagens
$msg = $_SESSION['sucesso'] ?? $_SESSION['erro'] ?? '';
$msg_tipo = isset($_SESSION['sucesso']) ? 'success' : 'error';
unset($_SESSION['sucesso'], $_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burger House - Os Melhores Hambúrgueres</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .categoria-section {
            margin-bottom: 40px;
        }

        .categoria-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .categoria-title::after {
            content: '';
            flex: 1;
            height: 2px;
            background: var(--light-gray);
        }

        .categoria-icon {
            width: 44px;
            height: 44px;
            background: var(--gradient-fire);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .filters-bar {
            background: var(--white);
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid var(--light-gray);
            background: var(--white);
            border-radius: var(--radius-full);
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 13px;
            color: var(--gray);
            transition: var(--transition);
        }

        .filter-btn:hover,
        .filter-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 16px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-full);
            font-family: 'Outfit', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .produto-card .add-btn {
            position: relative;
            overflow: hidden;
        }

        .produto-card .add-btn.adding {
            pointer-events: none;
        }

        .produto-card .add-btn.added {
            background: var(--success) !important;
        }

        .produto-card .add-btn.added i::before {
            content: "\f00c";
        }

        .promo-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--danger);
            color: white;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .quick-add-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 16px 32px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            animation: slideInUp 0.4s ease;
        }

        @keyframes slideInUp {
            from {
                transform: translateX(-50%) translateY(100px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        .floating-cart {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }

        .floating-cart a {
            width: 64px;
            height: 64px;
            background: var(--gradient-fire);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            text-decoration: none;
            position: relative;
        }

        .floating-cart a:hover {
            transform: scale(1.1);
        }

        .floating-cart .count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-container">
            <a href="index.php" class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</a>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['usuario'])): ?>
                    <?php if ($_SESSION['tipo'] == 'cliente'): ?>
                        <a href="carrinho.php" class="btn btn-primary carrinho-badge">
                            <i class="fas fa-shopping-cart"></i> Carrinho
                            <?php if ($cart_count > 0): ?>
                                <span class="badge" id="cart-badge"><?= $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="painel_cliente.php" class="btn btn-secondary"><i class="fas fa-user"></i> Minha Conta</a>
                    <?php else: ?>
                        <a href="painel_dono.php" class="btn btn-success"><i class="fas fa-tachometer-alt"></i> Painel Admin</a>
                    <?php endif; ?>
                    <span style="color: var(--gray);">Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</span>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i></a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                    <a href="cadastro.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Cadastrar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Hero -->
        <div class="hero">
            <h1>🍔 Os Melhores Hambúrgueres da Cidade!</h1>
            <p>Ingredientes frescos, sabor incomparável. Peça agora!</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?>">
                <i class="fas fa-<?= $msg_tipo == 'success' ? 'check' : 'exclamation' ?>-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters-bar">
            <a href="index.php" class="filter-btn <?= !$categoria ? 'active' : '' ?>">
                <i class="fas fa-th"></i> Todos
            </a>
            <?php foreach ($categorias as $cat): ?>
                <a href="?categoria=<?= urlencode($cat['categoria']) ?>"
                    class="filter-btn <?= $categoria == $cat['categoria'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['categoria']) ?>
                </a>
            <?php endforeach; ?>

            <form method="GET" style="display: flex; gap: 8px; flex: 1; min-width: 250px;">
                <?php if ($categoria): ?>
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria) ?>">
                <?php endif; ?>
                <input type="text" name="busca" class="search-input" placeholder="🔍 Buscar produtos..."
                    value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <!-- Produtos -->
        <?php if (empty($produtos)): ?>
            <div class="card empty">
                <i class="fas fa-search"></i>
                <h2>Nenhum produto encontrado</h2>
                <p>Tente buscar por outro termo ou categoria</p>
                <br>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-sync"></i> Ver Todos</a>
            </div>
        <?php else: ?>
            <?php foreach ($produtos_por_categoria as $cat => $prods): ?>
                <div class="categoria-section">
                    <div class="categoria-title">
                        <span class="categoria-icon">
                            <?php
                            $icons = [
                                'Clássicos' => 'hamburger',
                                'Premium' => 'crown',
                                'Aves' => 'drumstick-bite',
                                'Vegetarianos' => 'leaf',
                                'Acompanhamentos' => 'french-fries',
                                'Bebidas' => 'glass-water',
                                'Sobremesas' => 'ice-cream',
                                'Saudáveis' => 'heart',
                            ];
                            $icon = $icons[$cat] ?? 'utensils';
                            ?>
                            <i class="fas fa-<?= $icon ?>"></i>
                        </span>
                        <?= htmlspecialchars($cat) ?>
                    </div>

                    <div class="produtos-grid">
                        <?php foreach ($prods as $p): ?>
                            <div class="produto-card">
                                <div style="position: relative; overflow: hidden;">
                                    <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                        <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>"
                                            class="produto-imagem">
                                    <?php else: ?>
                                        <div class="produto-imagem"
                                            style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f5f5f5,#e0e0e0);">
                                            <i class="fas fa-hamburger" style="font-size:64px;color:#ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="produto-info">
                                    <span class="produto-categoria">
                                        <i class="fas fa-<?= $icon ?>"></i> <?= htmlspecialchars($p['categoria']) ?>
                                    </span>
                                    <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                                    <div class="produto-descricao"><?= htmlspecialchars($p['descricao']) ?></div>

                                    <div class="produto-footer">
                                        <div class="produto-preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>

                                        <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                            <button type="button" class="btn btn-success add-btn"
                                                onclick="adicionarCarrinho(<?= $p['id'] ?>, this)" data-id="<?= $p['id'] ?>">
                                                <i class="fas fa-cart-plus"></i> Adicionar
                                            </button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-primary">
                                                <i class="fas fa-sign-in-alt"></i> Entrar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Carrinho Flutuante (apenas para clientes logados com itens) -->
    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
        <div class="floating-cart" id="floating-cart" style="<?= $cart_count > 0 ? '' : 'display:none;' ?>">
            <a href="carrinho.php">
                <i class="fas fa-shopping-cart"></i>
                <span class="count" id="floating-count"><?= $cart_count ?></span>
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
        <script>
            // Mostrar toast
            function mostrarToast(mensagem, tipo = 'success') {
                document.querySelectorAll('.toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className = `toast ${tipo}`;
                toast.innerHTML = `<i class="fas fa-check-circle"></i> ${mensagem}`;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(20px)';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            // Adicionar ao carrinho
            async function adicionarCarrinho(idProduto, btn) {
                btn.classList.add('adding');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const formData = new FormData();
                    formData.append('adicionar_carrinho', '1');
                    formData.append('id_produto', idProduto);
                    formData.append('tipo_produto', 'normal');
                    formData.append('quantidade', '1');
                    formData.append('redirect', 'index.php');

                    await fetch('carrinho.php', {
                        method: 'POST',
                        body: formData
                    });

                    // Feedback visual
                    btn.classList.remove('adding');
                    btn.classList.add('added');
                    btn.innerHTML = '<i class="fas fa-check"></i> Adicionado!';

                    mostrarToast('Produto adicionado ao carrinho!');

                    // Atualizar contador
                    await atualizarContador();

                    // Voltar ao normal após 2 segundos
                    setTimeout(() => {
                        btn.classList.remove('added');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                    }, 2000);

                } catch (error) {
                    console.error('Erro:', error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Adicionar';
                    mostrarToast('Erro ao adicionar', 'error');
                }
            }

            // Atualizar contador do carrinho
            async function atualizarContador() {
                try {
                    const response = await fetch('ajax_handler.php?action=contar_carrinho');
                    const data = await response.json();

                    const badge = document.getElementById('cart-badge');
                    const floatingCart = document.getElementById('floating-cart');
                    const floatingCount = document.getElementById('floating-count');

                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                            badge.style.display = 'flex';
                        }
                        if (floatingCart) {
                            floatingCart.style.display = 'block';
                            floatingCount.textContent = data.count;
                        }
                    } else {
                        if (badge) badge.style.display = 'none';
                        if (floatingCart) floatingCart.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Erro:', error);
                }
            }

            // Atualizar periodicamente
            setInterval(atualizarContador, 10000);
        </script>
    <?php endif; ?>
</body>

</html>