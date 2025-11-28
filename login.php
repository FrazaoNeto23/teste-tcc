<?php
session_start();
include "config.php";
include "helpers.php";

// Se já estiver logado, redirecionar
if (isset($_SESSION['usuario'])) {
    redirecionar($_SESSION['tipo'] == 'dono' ? 'painel_dono.php' : 'index.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizar_texto($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $usuario = $resultado->fetch_assoc();
            if (verificar_senha($senha, $usuario['senha'])) {
                $_SESSION['id_usuario'] = $usuario['id'];
                $_SESSION['usuario'] = $usuario['nome'];
                $_SESSION['tipo'] = $usuario['tipo'];
                redirecionar($usuario['tipo'] == 'dono' ? 'painel_dono.php' : 'index.php', 'Bem-vindo de volta, ' . $usuario['nome'] . '!');
            }
        }
        $erro = 'Email ou senha incorretos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">
    <div class="login-container">
        <div class="logo">🍔</div>
        <h2>Bem-vindo de volta!</h2>
        <p style="text-align: center; color: var(--gray); margin-bottom: 24px;">
            Entre na sua conta para fazer pedidos
        </p>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required placeholder="seu@email.com"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Senha</label>
                <input type="password" name="senha" required placeholder="Sua senha">
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>

        <div class="links">
            <p>Não tem conta? <a href="cadastro.php">Cadastre-se aqui</a></p>
            <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar para a loja</a></p>
        </div>
    </div>
</body>

</html>