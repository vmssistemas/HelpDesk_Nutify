<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];

    $stmt = $conn->prepare("INSERT INTO tipo_erro (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $stmt->close();
}

$tipos_erro = $conn->query("SELECT * FROM tipo_erro");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Tipo de Erro</title>
</head>
<body>
    <h1>Tipo de Erro</h1>
    <form method="POST">
        <input type="text" name="nome" placeholder="Nome do Tipo de Erro" required>
        <button type="submit">Cadastrar</button>
    </form>

    <h2>Lista de Tipos de Erro</h2>
    <ul>
        <?php while ($tipo = $tipos_erro->fetch_assoc()): ?>
            <li><?php echo $tipo['nome']; ?></li>
        <?php endwhile; ?>
    </ul>
</body>
</html>