<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Busca todos os menus de atendimento
$query_menus = "SELECT * FROM menu_atendimento ORDER BY ordem";
$result_menus = $conn->query($query_menus);
$menus = $result_menus->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $menu_id = $_POST['menu_id'];

    $query = "INSERT INTO submenu_atendimento (nome, menu_id) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $nome, $menu_id);

    if ($stmt->execute()) {
        header("Location: administracao_atendimento.php");
        exit();
    } else {
        echo "Erro ao cadastrar submenu de atendimento.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Submenu de Atendimento</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Cadastrar Submenu de Atendimento</h1>
        <nav>
            <a href="administracao_atendimento.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="menu_id">Menu:</label>
            <select id="menu_id" name="menu_id" required>
                <?php foreach ($menus as $menu): ?>
                    <option value="<?php echo $menu['id']; ?>"><?php echo htmlspecialchars($menu['nome']); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Cadastrar</button>
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