<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$id = $_GET['id'];

// Busca o menu de atendimento pelo ID
$query = "SELECT * FROM menu_atendimento WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];

    $query = "UPDATE menu_atendimento SET nome = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $nome, $id);

    if ($stmt->execute()) {
        header("Location: administracao_atendimento.php");
        exit();
    } else {
        echo "Erro ao atualizar menu de atendimento.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Menu de Atendimento</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Editar Menu de Atendimento</h1>
        <nav>
            <a href="administracao_atendimento.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($menu['nome']); ?>" required>

            <button type="submit">Salvar</button>
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