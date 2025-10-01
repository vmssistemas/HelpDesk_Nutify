<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'] ?? null;
    $observacoes = $_POST['observacoes'] ?? null;

    // Remove formatação do telefone
    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    // Validação do telefone (deve ter 11 dígitos)
    if (strlen($telefone) !== 11) {
        echo json_encode(['status' => 'error', 'message' => 'Telefone deve conter 11 dígitos.']);
        exit();
    }

    // Verifica se o telefone já existe
    $query_verifica = "SELECT id FROM contatos WHERE telefone = ?";
    $stmt_verifica = $conn->prepare($query_verifica);
    $stmt_verifica->bind_param("s", $telefone);
    $stmt_verifica->execute();
    $stmt_verifica->store_result();

    if ($stmt_verifica->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Já existe um contato cadastrado com este telefone.']);
        exit();
    }

    // Insere o novo contato
    $query_inserir = "INSERT INTO contatos (nome, telefone, email, observacoes) VALUES (?, ?, ?, ?)";
    $stmt_inserir = $conn->prepare($query_inserir);
    $stmt_inserir->bind_param("ssss", $nome, $telefone, $email, $observacoes);

    if ($stmt_inserir->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Contato cadastrado com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar contato: ' . $stmt_inserir->error]);
    }
}
?>