<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
// Desabilitar exibição de erros para evitar interferir no JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/db.php';

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated'])) {
    header("Location: /HelpDesk_Nutify/login/login.php");
    exit();
}

// Verifica se o usuário é admin
$email = $_SESSION['email'];
$query = "SELECT admin FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

// Busca usuário logado
$query_usuario = "SELECT id, nome, email FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($query_usuario);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: ../../../login/login.php");
    exit();
}

$usuario_id = $usuario['id'];
$usuario_nome = $usuario['nome'];
$usuario_email = $usuario['email'];
$_SESSION['id'] = $usuario['id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Atendimento</title>
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

    /* Container esquerdo do header */
    .header-left {
        display: flex;
        align-items: center;
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

    /* Container dos botões de navegação */
    .header-buttons {
        display: flex;
        align-items: center;
        margin-left: 20px;
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

    /* Navbar de atendimento simplificada */
    .atendimento-nav {
        background-color: rgba(2, 51, 36, 0.1);
        padding: 5px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .atendimento-nav .container {
        display: flex;
        align-items: center;
    }

    .atendimento-nav .nav-link {
        color: #023324;
        font-size: 0.9em;
        padding: 5px 10px;
        margin-right: 10px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .atendimento-nav .nav-link:hover {
        background-color: rgba(2, 51, 36, 0.1);
    }

    .atendimento-nav .nav-link.active {
        background-color: #28a745;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .atendimento-nav .nav-link.active:hover {
        background-color: #218838;
    }


    .atendimento-nav .nav-link i {
        font-size: 1.1em;
        vertical-align: middle;
        margin-right: 5px;
    }

    /* Restante dos estilos existentes... */
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .status-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .cursor-pointer {
        cursor: pointer;
    }
    </style>
</head>
<body>

<script>
// Função para toggle dos dropdowns
function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    const allMenus = document.querySelectorAll('.dropdown-menu');
    
    // Fecha todos os outros menus
    allMenus.forEach(m => {
        if (m.id !== menuId) {
            m.classList.remove('visible');
        }
    });
    
    // Toggle do menu atual
    menu.classList.toggle('visible');
}

// Fecha dropdowns ao clicar fora
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('visible');
        });
    }
});

// Função de logout
function logout() {
    if (confirm('Tem certeza que deseja sair?')) {
        window.location.href = '/HelpDesk_Nutify/paginas/logout.php';
    }
}
</script>

<!-- Header principal -->
<header id="main-header">
    <div class="header-left">
        <div class="header-title">
            <i class="fas fa-headset"></i>
            Sistema de Atendimento
        </div>
        
        <div class="header-buttons">
            <!-- Container para Suporte -->
            <div class="dropdown-container" id="support-container">
                <button class="dropdown-button" id="supportButton" onclick="toggleDropdown('supportMenu')">
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
                <button class="dropdown-button" id="trainingButton" onclick="toggleDropdown('trainingMenu')">
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
                <button class="dropdown-button" id="registerButton" onclick="toggleDropdown('registerMenu')">
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
                <button class="dropdown-button" id="knowledgeButton" onclick="toggleDropdown('knowledgeMenu')">
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
        <button id="logoutButton" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i> Sair
        </button>
    </div>
</header>

<!-- Navbar de atendimento simplificada -->
<div class="atendimento-nav">
    <div class="container">
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'atendimento.php') ? 'active' : ''; ?>" href="atendimento.php">
            <i class="material-icons">dashboard</i> Painel
        </a>
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'incluir_atendimento.php') ? 'active' : ''; ?>" href="incluir_atendimento.php">
            <i class="material-icons">add</i> Novo Atendimento
        </a>
        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'relatorios_atendimento.php') ? 'active' : ''; ?>" href="relatorios_atendimento.php">
            <i class="material-icons">assessment</i> Relatórios
        </a>
    </div>
</div>

<div class="container mb-5">