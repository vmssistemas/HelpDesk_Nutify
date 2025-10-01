<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];

    $stmt = $conn->prepare("INSERT INTO menu_atendimento (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $stmt->close();
}

$menus = $conn->query("SELECT * FROM menu_atendimento");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Menu de Atendimento</title>
</head>
<body>
    <h1>Menu de Atendimento</h1>
    <form method="POST">
        <input type="text" name="nome" placeholder="Nome do Menu" required>
        <button type="submit">Cadastrar</button>
    </form>

    <h2>Lista de Menus</h2>
    <ul>
        <?php while ($menu = $menus->fetch_assoc()): ?>
            <li><?php echo $menu['nome']; ?></li>
        <?php endwhile; ?>
    </ul>
</body>
</html>