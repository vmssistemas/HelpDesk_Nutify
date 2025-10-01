<?php
session_start();

if (!isset($_SESSION['authenticated'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

require_once '../../config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $query = "SELECT * FROM contatos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $contato = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($contato);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Contato não encontrado']);
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'ID não fornecido']);
}
?>