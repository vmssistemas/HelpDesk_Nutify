<?php
session_start();  // Certifique-se de que session_start() esteja no topo de tudo

// Gerar o CSRF Token se não existir na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inicializa as variáveis para mensagens
$error_message = '';
$success_message = '';
$nome = '';
$email = '';

// Verifica o CSRF Token e se houve envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erro: CSRF token inválido!");
    }

    // Dados de conexão com o banco de dados
    require_once '../config/db.php';

    // Verifica conexão com o banco
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Função para validar a senha
    function validarSenha($senha) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', trim($senha));
    }

    // Sanear e validar nome
    $nome = trim(filter_var($_POST['nome'], FILTER_SANITIZE_STRING));
    $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validação do nome
    if (empty($nome)) {
        $error_message = "O nome é obrigatório.";
    } elseif (strlen($nome) < 3) {
        $error_message = "O nome deve ter pelo menos 3 caracteres.";
    } elseif (!preg_match("/^[a-zA-ZÀ-ú\s]+$/", $nome)) {
        $error_message = "O nome deve conter apenas letras e espaços.";
    }

    $dominio_permitido = "@nutifysistemas.com.br";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "E-mail inválido.";
    } elseif (substr($email, -strlen($dominio_permitido)) !== $dominio_permitido) {
        $error_message = "O e-mail não condiz com o domínio da equipe.";
    }

    // Verificação de e-mail duplicado
    if (empty($error_message)) {
        $stmt = $conn->prepare("SELECT email FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Esse e-mail já está cadastrado.";
        }
        $stmt->close();
    }

    // Validação da senha
    if (empty($error_message) && !validarSenha($senha)) {
        $error_message = "A senha deve ter no mínimo 8 caracteres, uma letra maiúscula, uma letra minúscula e um número.";
    }

    // Verificar se as senhas coincidem
    if (empty($error_message) && $senha !== $confirmar_senha) {
        $error_message = "As senhas não coincidem.";
    }

    if (empty($error_message)) {
        // Hash da senha
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        // Inserção no banco de dados
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $email, $hash);

        if ($stmt->execute()) {
            $success_message = "Usuário criado com sucesso!";
        } else {
            $error_message = "Erro ao criar usuário: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap"
        rel="stylesheet"
    />
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Criar Novo Usuário - Nutify Sistemas</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/x-icon">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f7f7f7;
        }
        #container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .input-group {
            margin-bottom: 15px;
        }
        .input-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border-radius: 4px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        .input-group input:focus {
            border-color: #8CC053;
            outline: none;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #8CC053;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color:#7FB449;
        }
        .error-message {
            text-align: center;
            color: red;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }
        .success-message {
            text-align: center;
            color: green;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }
        .login-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: transparent;
            color: #023324;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .login-link:hover {
            background-color: transparent;
            color: #023324;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div id="container">
        <form id="create-user-form" action="criar_usuario.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <div class="input-group">
                <label for="nome">Nome</label>
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    placeholder="Digite seu nome"
                    value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>"
                    required
                />
            </div>

            <div class="input-group">
                <label for="email">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Digite seu e-mail"
                    value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                    required
                />
            </div>

            <div class="input-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required />
            </div>

            <div class="input-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme sua senha" required />
            </div>

            <button type="submit" id="createButton">Criar Usuário</button>

            <!-- Link estilizado para a tela de login -->
            <div style="text-align: center; margin-top: 15px;">
                <a href="../login/login.php" class="login-link">
                    Já tem uma conta? Faça login aqui
                </a>
            </div>
        </form>
    </div>
</body>
</html>