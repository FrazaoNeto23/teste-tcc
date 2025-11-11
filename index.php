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
                            <?php if ($cart_count > 0): ?><span class="badge"><?= $cart_count ?></span><?php endif; ?>
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
            <h1>🍔 Os Melhores Hambúrgueres da Cidade!</h1>
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
                <input type="text" name="busca" placeholder="Buscar produtos..." value="<?= htmlspecialchars($busca) ?>">
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
                            <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" class="produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem" style="display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-hamburger" style="font-size:64px;color:#ccc;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="produto-info">
                            <?php if ($p['categoria']): ?>
                                <span class="produto-categoria"><i class="fas fa-tag"></i> <?= htmlspecialchars($p['categoria']) ?></span>
                            <?php endif; ?>
                            <div class="produto-nome"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="produto-descricao"><?= htmlspecialchars($p['descricao']) ?></div>
                            <div class="produto-footer">
                                <div class="produto-preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>
                                <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                    <form method="POST" action="carrinho.php">
                                        <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="tipo_produto" value="normal">
                                        <input type="hidden" name="quantidade" value="1">
                                        <input type="hidden" name="redirect" value="index.php">
                                        <button type="submit" name="adicionar_carrinho" class="btn btn-success">
                                            <i class="fas fa-cart-plus"></i> Adicionar
                                        </button>
                                    </form>
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
</body>
</html>
