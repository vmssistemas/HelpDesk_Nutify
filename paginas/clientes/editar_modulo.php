<?php
session_start();

// Verifica se o usuário está autenticado e é admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || 
    !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: administracao_modulos_planos.php");
    exit();
}

$id = $_GET['id'];

// Busca o módulo
$query = "SELECT * FROM modulos WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$modulo = $result->fetch_assoc();

if (!$modulo) {
    header("Location: administracao_modulos_planos.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    
    $query = "UPDATE modulos SET nome = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $nome, $id);
    
    if ($stmt->execute()) {
        header("Location: administracao_cliente.php");
        exit();
    } else {
        $erro = "Erro ao atualizar módulo: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Módulo</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Editar Módulo</h1>
        <nav>
            <a href="administracao_cliente.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <div>
                <label for="nome">Nome do Módulo:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($modulo['nome']); ?>" required>
            </div>
            <button type="submit">Salvar</button>
        </form>
        <?php if (isset($erro)) echo "<p>$erro</p>"; ?>
    </main>
</body>
</html>