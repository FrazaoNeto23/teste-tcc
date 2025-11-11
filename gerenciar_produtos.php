<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// ADICIONAR PRODUTO
if (isset($_POST['adicionar_produto'])) {
    $nome = sanitizar_texto($_POST['nome']);
    $descricao = sanitizar_texto($_POST['descricao']);
    $preco = (float)$_POST['preco'];
    $categoria = sanitizar_texto($_POST['categoria']);
    
    $imagem = '';
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $resultado = upload_imagem($_FILES['imagem']);
        if ($resultado['sucesso']) {
            $imagem = $resultado['caminho'];
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem);
    $stmt->execute();
    redirecionar('gerenciar_produtos.php', 'Produto adicionado com sucesso!');
}

// EDITAR PRODUTO
if (isset($_POST['editar_produto'])) {
    $id = (int)$_POST['id'];
    $nome = sanitizar_texto($_POST['nome']);
    $descricao = sanitizar_texto($_POST['descricao']);
    $preco = (float)$_POST['preco'];
    $categoria = sanitizar_texto($_POST['categoria']);
    $disponivel = isset($_POST['disponivel']) ? 1 : 0;
    
    // Buscar imagem atual
    $produto = $conn->query("SELECT imagem FROM produtos WHERE id=$id")->fetch_assoc();
    $imagem = $produto['imagem'];
    
    // Processar nova imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $resultado = upload_imagem($_FILES['imagem']);
        if ($resultado['sucesso']) {
            // Deletar imagem antiga
            if ($imagem && file_exists($imagem)) {
                unlink($imagem);
            }
            $imagem = $resultado['caminho'];
        }
    }
    
    $stmt = $conn->prepare("UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=?, imagem=?, disponivel=? WHERE id=?");
    $stmt->bind_param("ssdssii", $nome, $descricao, $preco, $categoria, $imagem, $disponivel, $id);
    $stmt->execute();
    redirecionar('gerenciar_produtos.php', 'Produto atualizado com sucesso!');
}

// DELETAR PRODUTO
if (isset($_GET['deletar'])) {
    $id = (int)$_GET['deletar'];
    $produto = $conn->query("SELECT imagem FROM produtos WHERE id=$id")->fetch_assoc();
    
    if ($produto['imagem'] && file_exists($produto['imagem'])) {
        unlink($produto['imagem']);
    }
    
    $conn->query("DELETE FROM produtos WHERE id=$id");
    redirecionar('gerenciar_produtos.php', 'Produto removido!');
}

// BUSCAR PRODUTOS
$busca = $_GET['busca'] ?? '';
$where = $busca ? "WHERE nome LIKE '%$busca%' OR categoria LIKE '%$busca%'" : '';
$produtos = $conn->query("SELECT * FROM produtos $where ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

// EDITAR - Buscar produto específico
$produto_editar = null;
if (isset($_GET['editar'])) {
    $produto_editar = $conn->query("SELECT * FROM produtos WHERE id=" . (int)$_GET['editar'])->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-boxes"></i> GERENCIAR PRODUTOS</div>
            <div style="display: flex; gap: 15px;">
                <a href="painel_dono.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <!-- FORMULÁRIO -->
        <div class="card">
            <h2><i class="fas fa-<?= $produto_editar ? 'edit' : 'plus' ?>"></i> <?= $produto_editar ? 'Editar' : 'Adicionar' ?> Produto</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <?php if ($produto_editar): ?>
                    <input type="hidden" name="id" value="<?= $produto_editar['id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-hamburger"></i> Nome do Produto *</label>
                        <input type="text" name="nome" value="<?= $produto_editar['nome'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Preço *</label>
                        <input type="number" name="preco" step="0.01" value="<?= $produto_editar['preco'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Categoria *</label>
                        <input type="text" name="categoria" value="<?= $produto_editar['categoria'] ?? '' ?>" 
                               placeholder="Ex: Clássicos, Premium, Bebidas" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Imagem</label>
                        <input type="file" name="imagem" accept="image/*">
                        <?php if ($produto_editar && $produto_editar['imagem']): ?>
                            <small style="color:#999;">Imagem atual: <?= basename($produto_editar['imagem']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descrição *</label>
                    <textarea name="descricao" required><?= $produto_editar['descricao'] ?? '' ?></textarea>
                </div>
                
                <?php if ($produto_editar): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="disponivel" <?= $produto_editar['disponivel'] ? 'checked' : '' ?>>
                            Produto disponível para venda
                        </label>
                    </div>
                <?php endif; ?>
                
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="<?= $produto_editar ? 'editar_produto' : 'adicionar_produto' ?>" class="btn btn-success">
                        <i class="fas fa-save"></i> <?= $produto_editar ? 'Atualizar' : 'Adicionar' ?>
                    </button>
                    <?php if ($produto_editar): ?>
                        <a href="gerenciar_produtos.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA DE PRODUTOS -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Produtos Cadastrados (<?= count($produtos) ?>)</h2>
            
            <div class="search-bar">
                <form method="GET" style="display:flex;gap:10px;width:100%;">
                    <input type="text" name="busca" placeholder="Buscar produto ou categoria..." value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <a href="gerenciar_produtos.php" class="btn btn-primary"><i class="fas fa-sync"></i></a>
                </form>
            </div>

            <?php if (empty($produtos)): ?>
                <p style="text-align:center;color:#999;padding:40px;">Nenhum produto cadastrado</p>
            <?php else: ?>
                <table class="produtos-table">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['imagem'] && file_exists($p['imagem'])): ?>
                                        <img src="<?= $p['imagem'] ?>" alt="<?= $p['nome'] ?>">
                                    <?php else: ?>
                                        <div style="width:60px;height:60px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-image" style="color:#ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($p['categoria']) ?></td>
                                <td><strong><?= formatar_preco($p['preco']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $p['disponivel'] ? 'success' : 'danger' ?>">
                                        <?= $p['disponivel'] ? 'Disponível' : 'Indisponível' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?editar=<?= $p['id'] ?>" class="btn btn-warning" style="padding:5px 10px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?deletar=<?= $p['id'] ?>" class="btn btn-danger" style="padding:5px 10px;" 
                                       onclick="return confirm('Deletar este produto?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
