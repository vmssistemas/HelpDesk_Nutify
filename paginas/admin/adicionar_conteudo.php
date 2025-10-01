<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Busca todos os submenus para o dropdown
$query_submenus = "SELECT * FROM submenus";
$result_submenus = $conn->query($query_submenus);
$submenus = $result_submenus->fetch_all(MYSQLI_ASSOC);

// Inicializa variável para o título do submenu
$titulo_submenu = '';

// Verifica se um submenu foi selecionado
// No trecho onde você processa o POST, modifique a validação para vídeos:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];
    $conteudo = $_POST['conteudo']; // Conteúdo formatado pelo CKEditor
    $submenu_id = $_POST['submenu_id'];

    // Validações básicas
    if (empty($tipo) || empty($conteudo) || empty($submenu_id)) {
        echo "Todos os campos são obrigatórios!";
        exit();
    }

    // Se o tipo for "video", faz uma limpeza mais rigorosa
    if ($tipo === 'video') {
        // Remove todas as tags HTML
        $conteudo = strip_tags($conteudo);
        // Remove possíveis espaços em branco no início e fim
        $conteudo = trim($conteudo);
        // Remove qualquer caractere que não seja alfanumérico, traço ou underline (ajuste conforme necessário)
        $conteudo = preg_replace('/[^a-zA-Z0-9-_]/', '', $conteudo);
    }

    // Busca o nome do submenu selecionado
    $query_submenu_nome = "SELECT nome FROM submenus WHERE id = ?";
    $stmt_submenu_nome = $conn->prepare($query_submenu_nome);
    $stmt_submenu_nome->bind_param("i", $submenu_id);
    $stmt_submenu_nome->execute();
    $stmt_submenu_nome->bind_result($submenu_nome);
    $stmt_submenu_nome->fetch();
    $stmt_submenu_nome->close();

    $titulo_submenu = $submenu_nome;

    // Insere o novo conteúdo no banco de dados
    $query = "INSERT INTO conteudos (submenu_id, tipo, conteudo) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $submenu_id, $tipo, $conteudo);

    if ($stmt->execute()) {
        header("Location: administracao.php");
        exit();
    } else {
        echo "Erro ao adicionar conteúdo: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Conteúdo</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <!-- Integrando o CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <header>
        <h1>Adicionar Conteúdo</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <!-- Exibe o título do submenu -->
        <?php if ($titulo_submenu): ?>
            <h2>Cadastro &gt; <?php echo htmlspecialchars($titulo_submenu); ?></h2>
        <?php endif; ?>

        <form method="POST">
            <label for="tipo">Tipo:</label>
            <select id="tipo" name="tipo" required>
                <option value="video">Vídeo</option>
                <option value="documentacao">Documentação</option>
            </select>

            <label for="conteudo">Conteúdo:</label>
            <textarea id="conteudo" name="conteudo" style="display:none;"></textarea>
            <div id="editor"></div>

            <label for="submenu_id">Submenu:</label>
            <select id="submenu_id" name="submenu_id" required>
                <option value="">Selecione um submenu</option>
                <?php foreach ($submenus as $submenu): ?>
                    <option value="<?php echo $submenu['id']; ?>"><?php echo htmlspecialchars($submenu['nome']); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Adicionar</button>
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