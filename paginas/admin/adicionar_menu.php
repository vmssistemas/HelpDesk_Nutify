<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $icone = $_POST['icone'];
    $mostrar_linha = isset($_POST['mostrar_linha']) ? 1 : 0; // Verifica se o checkbox foi marcado

    // Validações básicas
    if (empty($nome)) {
        echo "O nome do menu é obrigatório!";
        exit();
    }

    // Insere o novo menu no banco de dados
    $query = "INSERT INTO menus (nome, icone, mostrar_linha) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $nome, $icone, $mostrar_linha);

    if ($stmt->execute()) {
        header("Location: administracao.php");
        exit();
    } else {
        echo "Erro ao adicionar menu: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Menu</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Adicionar Menu</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="nome">Nome do Menu:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="icone">Ícone (<a href="https://ionic.io/ionicons" target="_blank">https://ionic.io/ionicons</a>):</label>
            <input type="text" id="icone" name="icone">

            <div class="linha-separadora">
                <label for="mostrar_linha">Adicionar linha separadora?</label>
                <input type="checkbox" id="mostrar_linha" name="mostrar_linha" value="1">
            </div>

            <button type="submit">Adicionar Menu</button>
        </form>
    </main>
</body>
<script>
    // Adiciona um listener para o evento de tecla pressionada
    document.addEventListener('keydown', function(event) {
        // Verifica se a tecla pressionada é ESC (código 27)
        if (event.keyCode === 27) {
            // Volta para a página anterior
            window.history.back();
        }
    });
</script>
</html>
