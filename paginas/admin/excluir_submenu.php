<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$submenu_id = $_GET['id'];

// Exclui o submenu e seus conteúdos associados
$query = "DELETE FROM submenus WHERE id = $submenu_id";
if ($conn->query($query)) {
    header("Location: administracao.php");
    exit();
} else {
    echo "Erro ao excluir submenu: " . $conn->error;
}
?>