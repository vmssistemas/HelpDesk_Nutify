<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$menu_id = $_GET['id'];

// Busca o menu atual
$query_menu = "SELECT * FROM menus WHERE id = $menu_id";
$result_menu = $conn->query($query_menu);
$menu = $result_menu->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];

    // Atualiza o menu no banco de dados
    $query = "UPDATE menus SET nome = '$nome' WHERE id = $menu_id";
    if ($conn->query($query)) {
        header("Location: administracao.php");
        exit();
    } else {
        echo "Erro ao atualizar menu: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Menu</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Editar Menu</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="nome">Nome do Menu:</label>
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