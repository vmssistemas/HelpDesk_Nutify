<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Ações: cadastrar, editar, excluir
$action = isset($_GET['action']) ? $_GET['action'] : 'listar';

// Cadastrar novo grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cadastrar') {
    $nome = trim($_POST['nome']);
    
    // Validação para nomes duplicados
    $query_check = "SELECT COUNT(*) as count FROM grupos WHERE nome = ?";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bind_param("s", $nome);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count = $result_check->fetch_assoc()['count'];
    $stmt_check->close();
    
    if ($count > 0) {
        header("Location: grupos.php?error=4"); // Erro de nome duplicado
        exit();
    }

    $query = "INSERT INTO grupos (nome) VALUES (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nome);

    if ($stmt->execute()) {
        header("Location: grupos.php?success=1");
        exit();
    } else {
        header("Location: grupos.php?error=1");
        exit();
    }
}

// Editar grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'editar') {
    $id = $_POST['id'];
    $nome = trim($_POST['nome']);
    
    // Validação para nomes duplicados (exceto o próprio registro)
    $query_check = "SELECT COUNT(*) as count FROM grupos WHERE nome = ? AND id != ?";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bind_param("si", $nome, $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count = $result_check->fetch_assoc()['count'];
    $stmt_check->close();
    
    if ($count > 0) {
        header("Location: grupos.php?action=editar&id=$id&error=4"); // Erro de nome duplicado
        exit();
    }

    $query = "UPDATE grupos SET nome = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $nome, $id);

    if ($stmt->execute()) {
        header("Location: grupos.php?success=2");
        exit();
    } else {
        header("Location: grupos.php?error=2");
        exit();
    }
}

// Excluir grupo
if ($action === 'excluir' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $query = "DELETE FROM grupos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: grupos.php?success=3");
        exit();
    } else {
        header("Location: grupos.php?error=3");
        exit();
    }
}

// Busca todos os grupos
$query_grupos = "SELECT * FROM grupos ORDER BY nome";
$result_grupos = $conn->query($query_grupos);
$grupos = $result_grupos->fetch_all(MYSQLI_ASSOC);

// Busca usuário logado
$email = $_SESSION['email'];
$query_usuario = "SELECT id, nome, email FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($query_usuario);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: ../../login/login.php");
    exit();
}

$usuario_id = $usuario['id'];
$usuario_nome = $usuario['nome'];
$usuario_email = $usuario['email'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Grupos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="../../assets/img/icone_verde.ico" type="image/png">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- IonIcons via jsDelivr -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
   <style>
    :root {
        --primary-color: #023324;
        --secondary-color: #6c757d;
        --success-color: #28a745;
        --info-color: #17a2b8;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f5f7fa;
        color: #333;
        margin-top: 40px; /* Espaço para o header fixo */
    }

    .container {
        max-width: 98% !important;
        padding: 0 15px;
    }

    /* Header fixo */
    #main-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        background-color: #023324;
        color: white;
        padding: 5px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: 40px;
    }

    /* Título no header */
    .header-title {
        font-weight: 600;
        font-size: 1.1em;
        display: flex;
        align-items: center;
        margin-left: 10px;
    }

    .header-title i {
        margin-right: 8px;
    }

    /* Container dos menus à direita */
    .header-menus {
        display: flex;
        align-items: center;
    }

    /* Estilos para os menus dropdown */
    .dropdown-container {
        position: relative;
        margin-right: 15px;
    }

    .dropdown-button {
        background: none;
        border: none;
        color: white;
        font-size: 0.9em;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
    }

    .dropdown-button:hover {
        transform: scale(1.05);
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 42px;
        left: 0;
        background-color: white;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        width: 180px;
    }

    .dropdown-menu a {
        color: #023324;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 0;
        font-size: 0.9em;
        transition: color 0.2s ease;
        margin-bottom: 2px;
    }

    .dropdown-menu a:hover {
        color: #8CC053;
    }

    .dropdown-menu.visible {
        display: block;
    }

    /* Estilos para o botão de logout */
    #logoutButton {
        background: none;
        border: none;
        color: white;
        font-size: 0.9em;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
    }

    #logoutButton i {
        font-size: 1.2em;
    }

    #logoutButton:hover {
        transform: scale(1.1);
    }

    /* Estilos para o perfil do usuário */
    #perfil-container {
        display: flex;
        align-items: center;
        margin-right: 30px;
        font-size: 14px;
        color: white;
    }

    /* Navbar de clientes simplificada */
    .clientes-nav {
        background-color: rgba(2, 51, 36, 0.1);
        padding: 5px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .clientes-nav .container {
        display: flex;
        align-items: center;
    }

    .clientes-nav .nav-link {
        color: #023324;
        font-size: 0.9em;
        padding: 5px 10px;
        margin-right: 10px;
        border-radius: 4px;
        transition: all 0.2s;
        text-decoration: none;
    }

    .clientes-nav .nav-link:hover {
        background-color: rgba(2, 51, 36, 0.1);
    }

    .clientes-nav .nav-link.active {
        background-color: #28a745;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .clientes-nav .nav-link.active:hover {
        background-color: #218838;
    }

    .clientes-nav .nav-link i {
        font-size: 1.1em;
        vertical-align: middle;
        margin-right: 5px;
    }

    /* Adicione isso na seção de estilos */
    .header-content {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }

    .header-title-container {
        display: flex;
        align-items: center;
        margin-right: 20px; /* Espaço entre o título e os botões */
    }

    .header-buttons {
        display: flex;
        align-items: center;
    }
    
    /* ESTILOS PARA FORMULÁRIOS E TABELAS */
    .form-section {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid #e0e0e0;
    }

    .form-section h2 {
        margin: 0 0 20px 0;
        font-size: 18px;
        color: #023324;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
        font-size: 14px;
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        background-color: #f9f9f9;
    }

    .form-group input:focus {
        outline: none;
        border-color: #8CC053;
        background-color: white;
    }

    .btn {
        background-color: #023324;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.2s ease;
        text-decoration: none;
        display: inline-block;
        margin-right: 10px;
    }

    .btn:hover {
        background-color: #01261b;
    }

    .btn-cancelar {
        background-color: #6c757d;
        color: white;
    }

    .btn-cancelar:hover {
        background-color: #5a6268;
    }

    .btn-editar {
        background-color: #17a2b8;
        color: white;
        padding: 5px 10px;
        font-size: 12px;
    }

    .btn-editar:hover {
        background-color: #138496;
    }

    .btn-excluir {
        background-color: #dc3545;
        color: white;
        padding: 5px 10px;
        font-size: 12px;
    }

    .btn-excluir:hover {
        background-color: #c82333;
    }

    /* Estilos para tabelas */
    table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    table th {
        background-color: #023324;
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }

    table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }

    table tr:hover {
        background-color: #f8f9fa;
    }

    /* Alertas */
    .alert {
        padding: 12px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .section-title {
        font-size: 18px;
        color: #023324;
        font-weight: 600;
        margin-bottom: 15px;
    }
    </style>
</head>
<body>
    <!-- Header fixo igual ao clientes.php -->
    <header id="main-header">
        <!-- Container do conteúdo principal do header -->
        <div class="header-content">
            <!-- Título -->
            <div class="header-title-container">
                <div class="header-title">
                    <i class="fas fa-users"></i> Gerenciar Grupos
                </div>
            </div>

            <!-- Container dos botões de navegação -->
            <div class="header-buttons">
                <!-- Container para Suporte -->
                <div class="dropdown-container" id="support-container">
                    <button class="dropdown-button" id="supportButton">
                        Suporte <ion-icon name="chevron-down-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu" id="supportMenu">
                        <a href="/HelpDesk_Nutify/paginas/atendimento/atendimento.php">
                            <i class="fa-solid fa-headset"></i> Atendimentos
                        </a>
                        <a href="/HelpDesk_Nutify/chamados/index.php">
                            <i class="fas fa-clipboard-list"></i> Chamados
                        </a>
                        <a href="/HelpDesk_Nutify/instalacoes/index.php">
                            <i class="fas fa-tools"></i> Instalações
                        </a>
                    </div>
                </div>

                <!-- Container para Treinamento -->
                <div class="dropdown-container" id="training-container">
                    <button class="dropdown-button" id="trainingButton">
                        Treinamento <ion-icon name="chevron-down-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu" id="trainingMenu">
                        <a href="/HelpDesk_Nutify/treinamentos/index.php">
                            <i class="fas fa-graduation-cap"></i> Treinamentos
                        </a>
                    </div>
                </div>

                <!-- Container para Cadastros -->
                <div class="dropdown-container" id="register-container">
                    <button class="dropdown-button" id="registerButton">
                        Cadastros <ion-icon name="chevron-down-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu" id="registerMenu">
                        <a href="/HelpDesk_Nutify/paginas/clientes/clientes.php">
                            <i class="fa-solid fa-user-plus"></i> Clientes
                        </a>
                    </div>
                </div>

                <!-- Container para Conhecimento -->
                <div class="dropdown-container" id="knowledge-container">
                    <button class="dropdown-button" id="knowledgeButton">
                        Principal <ion-icon name="chevron-down-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu" id="knowledgeMenu">
                        <a href="/HelpDesk_Nutify/paginas/principal.php">
                            <i class="fas fa-book"></i> Documentações
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Container dos menus à direita (usuário, configurações, logout) -->
        <div class="header-menus">
            <!-- Perfil do usuário -->
            <div id="perfil-container">
                <span><strong>Usuário:</strong> <?php echo htmlspecialchars($usuario_email ?? 'Usuário', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <!-- Logout -->
            <button id="logoutButton">
                <i class="fas fa-sign-out-alt"></i> Sair
            </button>
        </div>
    </header>

    <!-- Navbar simplificada para clientes -->
    <div class="clientes-nav">
        <div class="container">
            <a class="nav-link" href="clientes.php"><i class="fas fa-list"></i> Lista de Clientes</a>
            <a class="nav-link" href="cadastrar_cliente.php"><i class="fas fa-plus"></i> Novo Cliente</a>
            <a class="nav-link" href="contatos.php"><i class="fas fa-address-book"></i> Contatos</a>
            <a class="nav-link active" href="grupos.php"><i class="fas fa-users"></i> Grupos</a>
            <a class="nav-link" href="importar_clientes.php"><i class="fas fa-file-import"></i> Importar</a>
        </div>
    </div>

    <div class="container mb-5">
        <main>
            <!-- Mensagens de sucesso/erro -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">
                    <?php
                    switch ($_GET['success']) {
                        case 1:
                            echo "Grupo cadastrado com sucesso!";
                            break;
                        case 2:
                            echo "Grupo atualizado com sucesso!";
                            break;
                        case 3:
                            echo "Grupo excluído com sucesso!";
                            break;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert error">
                    <?php
                    switch ($_GET['error']) {
                        case 1:
                            echo "Erro ao cadastrar grupo.";
                            break;
                        case 2:
                            echo "Erro ao atualizar grupo.";
                            break;
                        case 3:
                            echo "Erro ao excluir grupo.";
                            break;
                        case 4:
                            echo "Já existe um grupo com este nome. Por favor, escolha outro nome.";
                            break;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulário de cadastro/edição -->
            <section class="form-section">
                <h2><?php echo ($action === 'editar') ? 'Editar Grupo' : 'Cadastrar Novo Grupo'; ?></h2>
                <form method="POST" action="grupos.php?action=<?php echo ($action === 'editar') ? 'editar' : 'cadastrar'; ?>">
                    <?php if ($action === 'editar' && isset($_GET['id'])): ?>
                        <?php
                        $id = $_GET['id'];
                        $query_grupo = "SELECT * FROM grupos WHERE id = ?";
                        $stmt_grupo = $conn->prepare($query_grupo);
                        $stmt_grupo->bind_param("i", $id);
                        $stmt_grupo->execute();
                        $result_grupo = $stmt_grupo->get_result();
                        $grupo = $result_grupo->fetch_assoc();
                        ?>
                        <input type="hidden" name="id" value="<?php echo $grupo['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="nome">Nome do Grupo:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo isset($grupo) ? htmlspecialchars($grupo['nome']) : ''; ?>" required>
                    </div>
                    <button type="submit" class="btn"><?php echo ($action === 'editar') ? 'Atualizar' : 'Cadastrar'; ?></button>
                    <?php if ($action === 'editar'): ?>
                        <a href="grupos.php" class="btn btn-cancelar">Cancelar</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Lista de grupos -->
            <section class="form-section">
                <h2 class="section-title">Lista de Grupos</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupos as $grupo): ?>
                            <tr>
                                <td><?php echo $grupo['id']; ?></td>
                                <td><?php echo htmlspecialchars($grupo['nome']); ?></td>
                                <td>
                                    <a href="grupos.php?action=editar&id=<?php echo $grupo['id']; ?>" class="btn btn-editar">Editar</a>
                                    <a href="grupos.php?action=excluir&id=<?php echo $grupo['id']; ?>" class="btn btn-excluir" onclick="return confirm('Tem certeza que deseja excluir este grupo?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script>
        // Função para voltar ao pressionar ESC
        document.addEventListener('keydown', function(event) {
            if (event.keyCode === 27) {
                window.history.back();
            }
        });

        // Scripts para os dropdowns do header (copiados do clientes.php)
        document.addEventListener('DOMContentLoaded', function() {
            // Função para toggle dos dropdowns
            function toggleDropdown(buttonId, menuId) {
                const button = document.getElementById(buttonId);
                const menu = document.getElementById(menuId);
                
                if (button && menu) {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        // Fecha outros dropdowns
                        document.querySelectorAll('.dropdown-menu').forEach(function(otherMenu) {
                            if (otherMenu !== menu) {
                                otherMenu.classList.remove('visible');
                            }
                        });
                        
                        // Toggle do menu atual
                        menu.classList.toggle('visible');
                    });
                }
            }

            // Inicializa os dropdowns
            toggleDropdown('supportButton', 'supportMenu');
            toggleDropdown('trainingButton', 'trainingMenu');
            toggleDropdown('registerButton', 'registerMenu');
            toggleDropdown('knowledgeButton', 'knowledgeMenu');

            // Fecha dropdowns ao clicar fora
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.classList.remove('visible');
                });
            });

            // Logout
            const logoutButton = document.getElementById('logoutButton');
            if (logoutButton) {
                logoutButton.addEventListener('click', function() {
                    if (confirm('Tem certeza que deseja sair?')) {
                        window.location.href = '../logout.php';
                    }
                });
            }
        });
    </script>
</body>
</html>