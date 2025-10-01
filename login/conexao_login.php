<?php
session_start();

// Incluir o arquivo de conexão
include('../config/db.php');  // Caminho para o arquivo db.php

// Verificar se o formulário foi enviado
if (isset($_POST['email']) && isset($_POST['senha'])) {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Buscar usuário no banco de dados
    $sql = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verifica a senha com password_verify()
        if (password_verify($senha, $user['senha'])) {
            $_SESSION['email'] = $email;
            $_SESSION['authenticated'] = true;
            echo json_encode(["success" => true]); // Sucesso no login
            exit();
        } else {
            echo json_encode(["success" => false, "message" => "Senha incorreta!"]); // Senha incorreta
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Usuário não encontrado!"]); // Usuário não encontrado
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>