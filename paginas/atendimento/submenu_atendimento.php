<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $menu_id = $_POST['menu_id'];
    $nome = $_POST['nome'];

    $stmt = $conn->prepare("INSERT INTO submenu_atendimento (menu_id, nome) VALUES (?, ?)");
    $stmt->bind_param("is", $menu_id, $nome);
    $stmt->execute();
    $stmt->close();
}

$submenus = $conn->query("SELECT submenu_atendimento.*, menu_atendimento.nome as menu_nome FROM submenu_atendimento JOIN menu_atendimento ON submenu_atendimento.menu_id = menu_atendimento.id");
$menus = $conn->query("SELECT * FROM menu_atendimento");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Submenu de Atendimento</title>
</head>
<body>
    <h1>Submenu de Atendimento</h1>
    <form method="POST">
        <select name="menu_id" required>
            <?php while ($menu = $menus->fetch_assoc()): ?>
                <option value="<?php echo $menu['id']; ?>"><?php echo $menu['nome']; ?></option>
            <?php endwhile; ?>
        </select>
        <input type="text" name="nome" placeholder="Nome do Submenu" required>
        <button type="submit">Cadastrar</button>
    </form>

    <h2>Lista de Submenus</h2>
    <ul>
        <?php while ($submenu = $submenus->fetch_assoc()): ?>
            <li><?php echo $submenu['menu_nome']; ?> - <?php echo $submenu['nome']; ?></li>
        <?php endwhile; ?>
    </ul>
</body>
</html>