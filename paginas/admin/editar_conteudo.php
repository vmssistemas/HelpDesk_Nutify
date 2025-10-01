<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$conteudo_id = $_GET['id'];

// Busca o conteúdo atual
$query_conteudo = "SELECT * FROM conteudos WHERE id = $conteudo_id";
$result_conteudo = $conn->query($query_conteudo);
$conteudo = $result_conteudo->fetch_assoc();

// Busca todos os submenus para o dropdown
$query_submenus = "SELECT * FROM submenus";
$result_submenus = $conn->query($query_submenus);
$submenus = $result_submenus->fetch_all(MYSQLI_ASSOC);

// No trecho onde você processa o POST, adicione a mesma validação:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];
    $conteudo_texto = $_POST['conteudo'];
    $submenu_id = $_POST['submenu_id'];

    // Validações básicas
    if (empty($tipo) || empty($conteudo_texto) || empty($submenu_id)) {
        echo "Todos os campos são obrigatórios!";
        exit();
    }

    // Se o tipo for "video", faz uma limpeza mais rigorosa
    if ($tipo === 'video') {
        // Remove todas as tags HTML
        $conteudo_texto = strip_tags($conteudo_texto);
        // Remove possíveis espaços em branco no início e fim
        $conteudo_texto = trim($conteudo_texto);
        // Remove qualquer caractere que não seja alfanumérico, traço ou underline
        $conteudo_texto = preg_replace('/[^a-zA-Z0-9-_]/', '', $conteudo_texto);
    }

    // Atualiza o conteúdo no banco de dados
    $query = "UPDATE conteudos SET tipo = ?, conteudo = ?, submenu_id = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $tipo, $conteudo_texto, $submenu_id, $conteudo_id);
    
    if ($stmt->execute()) {
        header("Location: administracao.php");
        exit();
    } else {
        echo "Erro ao atualizar conteúdo: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Conteúdo</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <!-- Integrando o CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <header>
        <h1>Editar Conteúdo</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <form method="POST">
            <label for="tipo">Tipo:</label>
            <select id="tipo" name="tipo" required>
                <option value="video" <?php echo ($conteudo['tipo'] === 'video') ? 'selected' : ''; ?>>Vídeo</option>
                <option value="documentacao" <?php echo ($conteudo['tipo'] === 'documentacao') ? 'selected' : ''; ?>>Documentação</option>
            </select>

            <label for="conteudo">Conteúdo:</label>
            <textarea id="conteudo" name="conteudo" style="display:none;"><?php echo htmlspecialchars($conteudo['conteudo']); ?></textarea>
            <div id="editor"><?php echo $conteudo['conteudo']; ?></div>

            <label for="submenu_id">Submenu:</label>
            <select id="submenu_id" name="submenu_id" required>
                <option value="">Selecione um submenu</option>
                <?php foreach ($submenus as $submenu): ?>
                    <option value="<?php echo $submenu['id']; ?>" <?php echo ($submenu['id'] == $conteudo['submenu_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($submenu['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Salvar</button>
        </form>
    </main>

    <script>
        // Inicializa o CKEditor
        ClassicEditor
            .create(document.querySelector('#editor'), {
                toolbar: [
                    'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                    'undo', 'redo'
                ]
            })
            .then(editor => {
                editor.model.document.on('change:data', () => {
                    document.querySelector('#conteudo').value = editor.getData();
                });
            })
            .catch(error => {
                console.error(error);
            });
    </script>
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