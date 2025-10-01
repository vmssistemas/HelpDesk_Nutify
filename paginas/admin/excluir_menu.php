<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$menu_id = $_GET['id'];

// Exclui o menu e seus submenus associados
$query = "DELETE FROM menus WHERE id = $menu_id";
if ($conn->query($query)) {
    header("Location: administracao.php");
    exit();
} else {
    echo "Erro ao excluir menu: " . $conn->error;
}
?>