<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../login/login.php");
    exit();
}

// Gerar o CSRF Token se não existir na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inicializa as variáveis para mensagens
$error_message = '';
$success_message = '';

// Verifica o CSRF Token e se houve envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erro: CSRF token inválido!");
    }

    // Dados de conexão com o banco de dados
    require_once '../config/db.php';

    // Função para validar a senha (reutilizada do criar_usuario.php)
    function validarSenha($senha) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', trim($senha));
    }

    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Verifica se a nova senha e a confirmação coincidem
    if ($nova_senha !== $confirmar_senha) {
        $error_message = "As senhas não coincidem.";
    }

    // Valida a nova senha
    if (empty($error_message) && !validarSenha($nova_senha)) {
        $error_message = "A senha deve ter no mínimo 8 caracteres, uma letra maiúscula, uma letra minúscula e um número.";
    }

    // Obtém o e-mail do usuário da sessão
    $email = $_SESSION['email'];

    // Verifica a senha atual
    if (empty($error_message)) {
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();

        if (!password_verify($senha_atual, $usuario['senha'])) {
            $error_message = "Senha atual incorreta.";
        }
    }

    // Se não houver erros, atualiza a senha
    if (empty($error_message)) {
        // Hash da nova senha
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

        // Atualiza a senha no banco de dados
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->bind_param("ss", $hash, $email);

        if ($stmt->execute()) {
            // Redireciona para a tela de login após a alteração da senha
            header("Location: ../login/login.php?success=1");
            exit();
        } else {
            $error_message = "Erro ao alterar senha: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - Nutify Sistemas</title>
    <link rel="stylesheet" href="../assets/css/principal.css"> <!-- Adicione seu CSS aqui -->
</head>
<body>
    <div id="alterarSenhaForm">
        <form id="formAlterarSenha" action="alterar_senha.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="input-group">
                <label for="senha_atual">Senha Atual</label>
                <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" required />
            </div>

            <div class="input-group">
                <label for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite sua nova senha" required />
            </div>

            <div class="input-group">
                <label for="confirmar_senha">Confirmar Nova Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme sua nova senha" required />
            </div>

            <button type="submit">Salvar Nova Senha</button>
        </form>
    </div>
</body>
</html>