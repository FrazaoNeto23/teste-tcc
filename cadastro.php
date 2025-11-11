<?php
session_start();
include "config.php";
include "helpers.php";

if (isset($_SESSION['usuario'])) {
    redirecionar('index.php');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = sanitizar_texto($_POST['nome']);
    $email = sanitizar_texto($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];
    $telefone = sanitizar_texto($_POST['telefone']);
    $endereco = sanitizar_texto($_POST['endereco']);
    
    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } elseif (!validar_email($email)) {
        $erro = 'Email inválido!';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres!';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem!';
    } else {
        // Verificar se email já existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $erro = 'Este email já está cadastrado!';
        } else {
            // Inserir novo usuário
            $senha_hash = hash_senha($senha);
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, telefone, endereco) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nome, $email, $senha_hash, $telefone, $endereco);
            
            if ($stmt->execute()) {
                redirecionar('login.php', 'Cadastro realizado com sucesso! Faça login.', 'sucesso');
            } else {
                $erro = 'Erro ao cadastrar. Tente novamente!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="cadastro-container">
        <div class="logo"><i class="fas fa-hamburger"></i> BURGER HOUSE</div>
        <h2>Criar sua conta</h2>
        
        <?php if ($erro): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $erro ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nome Completo *</label>
                <input type="text" name="nome" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telefone <small>(opcional)</small></label>
                <input type="tel" name="telefone" placeholder="(00) 00000-0000">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Endereço <small>(opcional)</small></label>
                <textarea name="endereco" placeholder="Rua, número, bairro, cidade"></textarea>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Senha * <small>(mínimo 6 caracteres)</small></label>
                <input type="password" name="senha" required minlength="6">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirmar Senha *</label>
                <input type="password" name="confirmar_senha" required>
            </div>
            
            <button type="submit" class="btn btn-success" style="width: 100%; padding: 14px; font-size: 16px; margin-top: 8px;">
                <i class="fas fa-user-plus"></i> Cadastrar
            </button>
        </form>
        
        <div class="links">
            Já tem conta? <a href="login.php">Faça login aqui</a><br>
            <a href="index.php">← Voltar para página inicial</a>
        </div>
    </div>
</body>
</html>
