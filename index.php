<?php
session_status() === PHP_SESSION_NONE && session_start();
include "config.php";
include_once "helpers.php";

// Garantir colunas existem (executar apenas uma vez, depois pode remover)
$conn->query("SHOW COLUMNS FROM produtos LIKE 'categoria'") || $conn->query("ALTER TABLE produtos ADD categoria VARCHAR(50) AFTER preco, ADD disponivel TINYINT(1) DEFAULT 1 AFTER imagem, ADD INDEX idx_categoria (categoria)");

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

$sql = "SELECT * FROM produtos WHERE " . implode(" AND ", $where) . " ORDER BY nome";
$stmt = $conn->prepare($sql);
$params && $stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Categorias e contador carrinho
$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE disponivel=1 AND categoria!='' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$cart_count = $usuario_id ? $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$usuario_id")->fetch_assoc()['t'] : 0;
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
</head>

<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</div>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['usuario'])): ?>
                    <?php if ($_SESSION['tipo'] == 'cliente'): ?>
                        <a href="carrinho.php" class="btn btn-primary carrinho-badge">
                            <i class="fas fa-shopping-cart"></i> Carrinho
                            <span class="badge" id="cart-badge"
                                style="<?= $cart_count > 0 ? '' : 'display:none;' ?>"><?= $cart_count ?></span>
                        </a>
                        <a href="painel_cliente.php" class="btn btn-primary"><i class="fas fa-user"></i> Minha Conta</a>
                    <?php else: ?>
                        <a href="painel_dono.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Painel</a>
                    <?php endif; ?>
                    <span>Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</span>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                    <a href="cadastro.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Cadastrar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="hero">
            <h1>🍔 Os Melhores da Cidade!</h1>
            <p>Sabor incomparável, qualidade garantida</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?>">
                <i class="fas fa-<?= $msg_tipo == 'success' ? 'check' : 'exclamation' ?>-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET">
                <select name="categoria">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['categoria']) ?>" <?= $categoria == $cat['categoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="busca" placeholder="Buscar produtos..."
                    value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-sync"></i> Limpar</a>
            </form>
        </div>

        <div class="produtos-grid">
            <?php if (empty($produtos)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h2>Nenhum produto encontrado</h2>
                    <p>Tente buscar por outro termo ou categoria</p>
                </div>
            <?php else: ?>
                <?php foreach ($produtos as $p): ?>
                    <div class="produto-card">
                        <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                            <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>"
                                class="produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem" style="display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-hamburger" style="font-size:64px;color:#ccc;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="produto-info">
                            <?php if ($p['categoria']): ?>
                                <span class="produto-categoria"><i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($p['categoria']) ?></span>
                            <?php endif; ?>
                            <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="produto-descricao"><?= htmlspecialchars($p['descricao']) ?></div>
                            <div class="produto-footer">
                                <div class="produto-preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>
                                <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                    <button type="button" class="btn btn-success btn-adicionar"
                                        onclick="adicionarCarrinho(<?= $p['id'] ?>, this)" id="btn-add-<?= $p['id'] ?>">
                                        <i class="fas fa-cart-plus"></i> Adicionar
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
        <script>
            // Mostrar toast de notificação
            function mostrarToast(mensagem) {
                // Remover toasts existentes
                document.querySelectorAll('.toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className = 'toast';
                toast.innerHTML = `<i class="fas fa-check-circle"></i> ${mensagem}`;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(20px)';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            // Adicionar ao carrinho via AJAX
            async function adicionarCarrinho(idProduto, btn) {
                btn.classList.add('adding');
                btn.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('adicionar_carrinho', '1');
                    formData.append('id_produto', idProduto);
                    formData.append('tipo_produto', 'normal');
                    formData.append('quantidade', '1');
                    formData.append('redirect', 'index.php');

                    const response = await fetch('carrinho.php', {
                        method: 'POST',
                        body: formData
                    });

                    // Atualizar contador do carrinho
                    await atualizarContadorCarrinho();
                    mostrarToast('Produto adicionado ao carrinho!');

                } catch (error) {
                    console.error('Erro ao adicionar:', error);
                    mostrarToast('Erro ao adicionar produto');
                } finally {
                    setTimeout(() => {
                        btn.classList.remove('adding');
                        btn.disabled = false;
                    }, 500);
                }
            }

            // Atualizar contador do carrinho
            async function atualizarContadorCarrinho() {
                try {
                    const response = await fetch('ajax_handler.php?action=contar_carrinho');
                    const data = await response.json();

                    const badge = document.getElementById('cart-badge');
                    if (badge) {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                } catch (error) {
                    console.error('Erro ao atualizar contador:', error);
                }
            }

            // Atualizar contador periodicamente
            setInterval(atualizarContadorCarrinho, 10000);
        </script>
    <?php endif; ?>
</body>

</html>