<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Busca todos os menus para o dropdown
$query_menus = "SELECT * FROM menus";
$result_menus = $conn->query($query_menus);
$menus = $result_menus->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $menu_id = $_POST['menu_id'];

    // Insere o novo submenu no banco de dados
    $query = "INSERT INTO submenus (menu_id, nome) VALUES ('$menu_id', '$nome')";
    if ($conn->query($query)) {
        header("Location: administracao.php");
        exit();
    } else {
        echo "Erro ao adicionar submenu: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Submenu</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Adicionar Submenu</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="nome">Nome do Submenu:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="menu_id">Menu:</label>
            <select id="menu_id" name="menu_id" required>
                <option value="">Selecione um menu</option>
                <?php foreach ($menus as $menu): ?>
                    <option value="<?php echo $menu['id']; ?>"><?php echo htmlspecialchars($menu['nome']); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Adicionar</button>
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