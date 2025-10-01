<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once 'functions_instalacoes.php';

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
    <title>Controle de Instalações</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
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

    /* Estilos para o botão de configurações */
    #configButton {
        background: none;
        border: none;
        color: white;
        font-size: 0.9em;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        margin-left: 10px;
    }

    #configButton i {
        font-size: 1.2em;
    }

    #configButton:hover {
        transform: scale(1.05);
    }

    #configContainer {
        display: none;
        position: absolute;
        top: 50px;
        right: 20px;
        background-color: white;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        width: 180px;
    }

    #configContainer.visible {
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

    /* Estilos para o tema toggle */
    .switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 22px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.2s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: 0.2s;
        border-radius: 50%;
    }

    input:checked + .slider:before {
        transform: translateX(14px);
    }

    input:checked + .slider {
        background-color: #8CC053;
    }

    #theme-label {
        font-size: 14px;
        color: #023324;
        font-weight: 500;
        transition: color 0.2s;
    }

    /* Navbar de instalações simplificada */
    .instalacoes-nav {
        background-color: rgba(2, 51, 36, 0.1);
        padding: 5px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .instalacoes-nav .container {
        display: flex;
        align-items: center;
    }

    .instalacoes-nav .nav-link {
        color: #023324;
        font-size: 0.9em;
        padding: 5px 10px;
        margin-right: 10px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .instalacoes-nav .nav-link:hover {
        background-color: rgba(2, 51, 36, 0.1);
    }

    .instalacoes-nav .nav-link.active {
        background-color: #28a745;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .instalacoes-nav .nav-link.active:hover {
        background-color: #218838;
    }

    .instalacoes-nav .nav-link i {
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

    /* Estilos para o checklist moderno */
    .checklist-item {
        border-left: 4px solid #dee2e6;
        border-radius: 4px;
        padding: 12px 15px;
        margin-bottom: 8px;
        transition: all 0.2s ease;
        background-color: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .checklist-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transform: translateY(-1px);
    }

    .checklist-item.bg-light-success {
        border-left-color: #28a745;
        background-color: rgba(40, 167, 69, 0.05);
    }

    .checklist-item .form-check-input {
        margin-top: 0.25em;
        transform: scale(1.1);
    }

    .checklist-item .form-check-label {
        cursor: pointer;
        user-select: none;
    }

    .checklist-item .item-details {
        margin-top: 8px;
        padding-left: 25px;
    }

    /* Estilos para o select de cliente personalizado */
    .custom-select {
        position: relative;
        width: 100%;
    }

    .custom-select input {
        width: 100%;
        padding: 8px 30px 8px 12px !important;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        background-color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
        text-indent: 0 !important;
        cursor: pointer;
    }

    .custom-select input:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .custom-select::after {
        content: "▼";
        font-size: 10px;
        color: #6c757d;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .custom-select .options {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: white;
        z-index: 1000;
        display: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 5px;
    }

    .custom-select .options div {
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .custom-select .options div:hover {
        background-color: #f8f9fa;
    }

    /* Adicione isso na seção de estilos do header_instalacoes.php */

#configContainer button {
    background: none;
    border: none;
    color: #023324;
    width: 100%;
    text-align: left;
    font-size: 0.9em;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    margin: 2px 0;
    transition: color 0.2s ease;
}

#configContainer button:hover {
    color: #8CC053;
}

#configContainer button i {
    font-size: 1.1em;
    width: 20px;
    text-align: center;
}

    .custom-select .options div.selected {
        background-color: #0d6efd;
        color: white;
    }

    .no-results {
        padding: 8px 12px;
        color: #6c757d;
        font-style: italic;
    }

    .checklist-item .item-details .form-control-sm {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .checklist-item .item-details .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.2rem;
        color: #6c757d;
    }

    /* Efeito para itens concluídos */
    .checklist-item.bg-light-success .form-check-label {
        color: #28a745;
    }

    .checklist-item.bg-light-success .form-check-label strong {
        text-decoration: line-through;
        opacity: 0.8;
    }

    /* Layout compacto para inputs */
    .compact-input-group {
        display: flex;
        gap: 8px;
    }

    .compact-input-group .form-control {
        flex: 1;
    }

    /* Total de horas */
    #totalHoras {
        font-weight: bold;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    .kanban-column {
        min-height: 100px;
        max-height: calc(100vh - 250px);
        overflow-y: auto;
    }

    /* Estilos para ordenação */
    th a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: var(--dark-color);
        text-decoration: none;
    }

    th a:hover {
        color: var(--primary-color) !important;
    }

    /* Estilos para o contador */
    .total-counter {
        font-size: 0.9rem;
        padding: 5px 10px;
        border-radius: 20px;
    }

    /* Estilos para os botões no cabeçalho */
    .card-header .btn {
        margin-top: -3px;
        margin-bottom: -3px;
    }
    
    .compact-btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .compact-btn .material-icons {
        font-size: 1rem;
        vertical-align: middle;
        margin-top: -2px;
    }

    .filters-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    .filters-left {
        flex: 1;
        margin-right: 20px;
    }

    .filters-right {
        width: 300px;
    }

    a[href*="visualizar.php"]:hover {
        color: #0a58ca !important;
        text-decoration: underline !important;
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
    </style>
</head>
<body>
    <!-- Header fixo com os mesmos menus do principal.php -->
   <header id="main-header">
    <!-- Container principal que agrupa título e botões -->
    <div class="header-content">
        <!-- Título do sistema -->
        <div class="header-title-container">
            <div class="header-title">
                <i class="material-icons">build</i>
                <span>Controle de Instalações</span>
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

    <!-- Navbar simplificada para instalações -->
    <div class="instalacoes-nav">
        <div class="container">
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            ?>
            <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">
                <i class="material-icons">dashboard</i> Painel
            </a>
            <a class="nav-link <?= ($current_page == 'criar.php') ? 'active' : '' ?>" href="criar.php">
                <i class="material-icons">add</i> Nova Instalação
            </a>
        </div>
    </div>

    <div class="container mb-5">