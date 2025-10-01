<?php
session_start();

// Verifica se o usuário está autenticado e é admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Verifica se o ID do link útil foi passado via GET
if (!isset($_GET['id'])) {
    header("Location: administracao.php");
    exit();
}

$id = intval($_GET['id']); // Converte o ID para inteiro

// Busca o link útil no banco de dados
$query = "SELECT id, nome, url FROM links_uteis WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$link = $result->fetch_assoc();

// Verifica se o link útil existe
if (!$link) {
    header("Location: administracao.php");
    exit();
}

// Processa o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $url = $_POST['url'];

    // Atualiza o link útil no banco de dados
    $query = "UPDATE links_uteis SET nome = ?, url = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $nome, $url, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: administracao.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Link Útil</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Editar Link Útil</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <div>
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($link['nome']); ?>" required>
            </div>
            <div>
                <label for="url">URL:</label>
                <input type="url" id="url" name="url" value="<?php echo htmlspecialchars($link['url']); ?>" required>
            </div>
            <button type="submit">Salvar Alterações</button>
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