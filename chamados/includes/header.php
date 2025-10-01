<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: /HelpDesk_Nutify/login/login.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'functions.php';

// Busca o ID do usuário logado
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

// Define os dados do usuário na sessão
$_SESSION['usuario_id'] = $usuario['id'];  // Esta linha é crucial!
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];

$usuario_id = $usuario['id'];
$usuario_nome = $usuario['nome'];
$usuario_email = $usuario['email'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Chamados</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- IonIcons via jsDelivr -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <!-- CKEditor com plugins adicionais -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/decoupled-document/ckeditor.js"></script>
    <!-- Fancybox CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.min.css"/>

<!-- Player.js para vídeos -->
<link href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css" rel="stylesheet"/>
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

    /* Base Styles */
    body {
        font-family: 'Roboto', sans-serif;
        background-color: #F4F4F4;
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

    /* Estilos adicionais para corresponder ao visual dos treinamentos */
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

    .header-content {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }

    .header-title-container {
        display: flex;
        align-items: center;
        margin-right: 20px;
    }

    .header-buttons {
        display: flex;
        align-items: center;
    }

    /* Sub-navegação dos chamados */
    .chamados-nav {
        background-color: rgba(2, 51, 36, 0.1);
        padding: 5px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .chamados-nav .container {
        display: flex;
        align-items: center;
    }

    .chamados-nav .nav-link {
        color: #023324;
        font-size: 0.9em;
        padding: 5px 10px;
        margin-right: 10px;
        border-radius: 4px;
        transition: all 0.2s;
        text-decoration: none;
        display: flex;
        align-items: center;
    }

    .chamados-nav .nav-link:hover {
        background-color: rgba(2, 51, 36, 0.1);
    }

    .chamados-nav .nav-link.active {
        background-color: #28a745;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .chamados-nav .nav-link.active:hover {
        background-color: #218838;
    }

    .chamados-nav .nav-link i {
        font-size: 1.1em;
        vertical-align: middle;
        margin-right: 5px;
    }

    /* Cards */
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .card-body .form-label {
        font-weight: 500;
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
    }

    .card-body .form-select {
        font-size: 0.9rem;
        padding: 0.4rem 1rem;
    }

    /* Buttons */
    .compact-btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .compact-btn .material-icons {
        font-size: 1rem;
        vertical-align: middle;
        margin-top: -2px;
    }

    /* Kanban Board */
    .kanban-container {
        width: 100%;
        overflow: hidden;
        margin-bottom: 20px;
        margin-top: 0;
    }

    .kanban-scroll-container {
        overflow-x: auto;
        padding-bottom: 15px;
        width: 100%;
        scroll-behavior: smooth;
    }

    /* Botões de Navegação Lateral do Kanban */
    .kanban-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1000;
        background: rgba(2, 51, 36, 0.9);
        border: none;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        opacity: 0.7;
    }

    .kanban-nav-btn:hover {
        opacity: 1;
        background: rgba(2, 51, 36, 1);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .kanban-nav-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    .kanban-nav-left {
        left: 10px;
    }

    .kanban-nav-right {
        right: 10px;
    }

    .kanban-nav-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        background: rgba(108, 117, 125, 0.5);
    }

    .kanban-nav-btn:disabled:hover {
        transform: translateY(-50%);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    /* Container com posição relativa para os botões */
    .kanban-container {
        position: relative;
    }

    .kanban-board {
        display: flex;
        gap: 15px;
        min-width: max-content;
        padding: 10px 0;
        justify-content: center;
        margin: 0 auto;
        width: fit-content;
    }

    .kanban-column-wrapper {
        width: 280px;
        min-width: 280px;
        background: #f5f7fa;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin: 0 5px;
    }

    /* Kanban Columns */
    .kanban-column-header {
        padding: 12px 15px;
        background: #fff;
        border-radius: 6px 6px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border-left: 4px solid currentColor;
    }

    .kanban-column-title {
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .kanban-count {
        background: #e9ecef;
        color: #495057;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 12px;
        font-weight: 500;
    }

    .kanban-column-actions, .kanban-bulk-actions-btn {
        display: flex;
        gap: 5px;
    }

    .kanban-add-btn, .kanban-bulk-actions-btn {
        background: transparent;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 2px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .kanban-add-btn:hover, .kanban-bulk-actions-btn:hover {
        background: #f1f3f5;
        color: #495057;
    }

    .kanban-column {
        padding: 10px;
        min-height: 100px;
        max-height: calc(100vh - 250px);
        overflow-y: auto;
        transition: background-color 0.3s ease, opacity 0.2s ease;
    }

    /* Kanban Cards */
    .kanban-card {
        background: #fff;
        border-radius: 6px;
        padding: 12px 12px 12px 35px;
        margin-bottom: 10px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        transition: all 0.3s ease, transform 0.2s ease;
        border: 1px solid #e9ecef;
        position: relative;
    }

    .kanban-card:hover {
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .kanban-card.selected {
        background-color: #f0f7ff;
        border-color: #b8daff;
    }

    .kanban-card-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .kanban-card-priority {
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 12px;
        color: white;
    }

    .kanban-card-body {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
        align-items: flex-start;
    }

    .kanban-card-icon {
        color: #6c757d;
        font-size: 18px;
        margin-top: 2px;
    }

    .kanban-card-icon .material-icons {
        font-size: 18px;
    }

    .kanban-card-title {
        font-size: 14px;
       
        color: #212529;
        text-decoration: none;
        flex-grow: 1;
        word-break: break-word;
    }

  

    .kanban-card-client {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kanban-card-type {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 10px;
        margin-top: -5px;
    }
    
    .kanban-card-type .material-icons {
        font-size: 14px;
    }

    .kanban-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
    }

    .kanban-card-responsible {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #6c757d;
    }

    /* Avatars */
    .avatar, .kanban-avatar {
        border-radius: 50%;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    .avatar {
        width: 30px;
        height: 30px;
        font-size: 12px;
        background-color: var(--secondary-color);
    }

    .kanban-avatar {
        width: 22px;
        height: 22px;
        font-size: 11px;
        background: #6c757d;
    }

    /* Empty States */
    .kanban-empty {
        text-align: center;
        padding: 20px 0;
        color: #adb5bd;
        font-size: 13px;
        transition: all 0.3s ease;
    }

    .kanban-empty .material-icons {
        font-size: 24px;
        margin-bottom: 5px;
        display: block;
        color: #dee2e6;
    }

    /* Drag and Drop */
    .kanban-ghost {
        opacity: 0.5;
        background: #e3f2fd;
        border: 1px dashed #90caf9;
    }

    .kanban-drag {
        transform: rotate(2deg);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2) !important;
    }

    /* Estilos para os badges de filtros ativos */
#activeFiltersContainer .badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35em 0.65em;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 0.25rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
}

#activeFiltersContainer .badge .material-icons {
    font-size: 14px;
    margin-right: 0.25rem;
}

/* Tooltip customizado */
.tooltip {
    font-size: 0.8rem;
}

.tooltip-inner {
    max-width: 300px;
    padding: 0.5rem 1rem;
    text-align: left;
}

    .kanban-dragging {
        z-index: 1000 !important;
    }

    .kanban-drop {
        background-color: #f0f7ff;
        transition: background-color 0.3s;
    }

    /* Loading States */
    .kanban-loading, .stat-loading {
        position: relative;
        opacity: 0.8;
    }

    .kanban-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.7) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23492656"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z" opacity=".5"/><path d="M12 2a10 10 0 0 0-10 10 10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/></svg>') no-repeat center;
        background-size: 24px;
        z-index: 1;
    }

    .stat-loading::after {
        content: '';
        position: absolute;
        top: 10px;
        right: 10px;
        width: 16px;
        height: 16px;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z" opacity=".5"/><path d="M12 2a10 10 0 0 0-10 10 10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/></svg>') no-repeat center;
        background-size: contain;
        animation: spin 1s linear infinite;
    }

    .loading-spinner {
        display: none;
        text-align: center;
        padding: 20px;
    }

    .loading-spinner.active {
        display: block;
    }

    /* Checkboxes */
    .kanban-card-checkbox {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 2;
    }

    .kanban-card-select {
        cursor: pointer;
    }

    /* Priority Badges */
    .priority-badge {
        font-size: 0.7rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
    }
    

    /* Scrollbars */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Sticky Elements */
    .sticky-filters-container {
        position: sticky;
        top: 70px;
        z-index: 1000;
        background: white;
        padding-bottom: 10px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        margin-bottom: 15px;
    }

    .global-actions-container {
        position: sticky;
        top: 70px;
        left: 0;
        padding: 10px 15px;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        z-index: 10;
        margin-top: 15px;
        margin-bottom: 15px;
        display: flex;
    }

    /* Animations */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Modal and Toast */
    #bulkActionsModal .modal-body {
        padding: 20px;
    }

    #bulkActionsModal .form-select {
        margin-bottom: 15px;
    }

    .toast {
        min-width: 250px;
    }
    .kanban-card-release {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    margin-bottom: 15px;
    margin-left: 0; /* Alinha mais à esquerda */
    padding: 3px 8px 3px 5px;
    border-radius: 12px;
    background-color: var(--release-color); /* Usa a cor dinâmica */
    color: white; /* Texto branco para melhor contraste */
    width: fit-content;
    max-width: 90%; /* Evita que ocupe toda a largura */
    transform: translateY(15px); /* Desloca para baixo */
}

.kanban-card-release .material-icons {
    font-size: 14px;
    color: inherit; /* Herda a cor do texto */
}

/* Estilos para o campo de previsão de liberação no Kanban */
.kanban-card-forecast {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    margin-bottom: 15px;
    margin-left: 0;
    padding: 3px 8px 3px 5px;
    border-radius: 12px;
    background-color: #f8f9fa;
    color: #495057;
    width: fit-content;
    max-width: 90%;
    transform: translateY(15px);
    border: 1px solid #dee2e6;
}

.kanban-card-forecast .material-icons {
    font-size: 14px;
    color: #6c757d;
}
#bulkReleaseContainer {
    transition: all 0.3s ease;
}
/* Estilos para o container de clientes vinculados - MELHORADO */
.clientes-card {
    margin-top: 20px;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    overflow: hidden;
}

.clientes-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
}



.cliente-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 10px;
    margin-bottom: 4px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.85rem;
}

.cliente-info {
    flex-grow: 1;
    padding-right: 10px;
}

.cliente-actions {
    margin-left: 8px;
    flex-shrink: 0;
}

.cliente-actions .btn {
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
}

.cliente-actions .material-icons {
    font-size: 16px;
    margin-right: 2px;
}

/* Formulário de adição de cliente - elementos lado a lado */
.form-adicionar-cliente {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.form-adicionar-cliente .mb-2 {
    flex: 1;
    margin-bottom: 0 !important;
}

.form-adicionar-cliente .custom-select {
    margin-bottom: 0;
}

.form-adicionar-cliente button {
    white-space: nowrap;
    margin-bottom: 0;
}
.updates {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.updates h2 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.update-item {
    margin-bottom: 15px;
    padding: 10px;
    background-color: white;
    border-left: 4px solid #3498db;
    border-radius: 4px;
}

.update-item h3 {
    margin: 0;
    font-size: 16px;
    color: #34495e;
}
/* Container de marcadores */
.marcadores-container {
    margin-top: 20px;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    overflow: visible; /* Alterado de hidden para visible */
    position: relative; /* Adicionado para contexto de posicionamento */
}

.marcadores-container .custom-select {
    position: relative;
    z-index: 5; /* Garante que fique acima de outros elementos */
}

.marcadores-container .custom-select .options {
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
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    margin-bottom: 5px;
}

/* Estilo para opção com foco */
#marcador_options div.focused {
    background-color: #023324;
    color: white;
}

#marcador_options div.focused .marcador-cor {
    border: 2px solid white;
}

/* Mensagem de nenhum resultado */
.no-results {
    padding: 10px;
    text-align: center;
    color: #6c757d;
    font-style: italic;
    display: none;
}

/* Garantir que o dropdown fique acima de outros elementos */
.collapsible-content {
    position: relative;
    z-index: 1;
}

.custom-select {
    z-index: 10;
}
.marcadores-container .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
}

#marcador_options div[data-value] {
    display: flex;
    align-items: center;
    padding: 6px 10px;
}


.marcador-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 10px;
    margin-bottom: 4px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.85rem;
    border-left: 4px solid currentColor;
}

.marcador-info {
    flex-grow: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 10px;
}

.marcador-cor {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.marcador-actions {
    margin-left: 8px;
    flex-shrink: 0;
}

.marcador-actions .btn {
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
}

.marcador-actions .material-icons {
    font-size: 16px;
    margin-right: 2px;
}

/* Formulários de marcadores - elementos lado a lado */
.form-marcador-existente,
.form-novo-marcador {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.form-marcador-existente .mb-3,
.form-novo-marcador .mb-3 {
    flex: 1;
    margin-bottom: 0 !important;
}

.form-marcador-existente .input-group,
.form-novo-marcador .row {
    margin-bottom: 0;
}

.form-marcador-existente button,
.form-novo-marcador button {
    white-space: nowrap;
    margin-bottom: 0;
    height: fit-content;
}

/* Ajustes para o formulário de novo marcador */
.form-novo-marcador .row.g-2 {
    margin-left: 0;
    margin-right: 0;
}

.form-novo-marcador .col-md-8,
.form-novo-marcador .col-md-2 {
    padding-left: 5px;
    padding-right: 5px;
}

.form-novo-marcador .form-control-color {
    height: 38px;
    padding: 5px;
}
/* Estilos para o carrossel de imagens */
.carrossel-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.carrossel-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.carrossel-image {
    max-width: 100%;
    max-height: 80vh;
    display: block;
    margin: 0 auto;
}

.carrossel-close {
    position: absolute;
    top: -40px;
    right: 0;
    color: white;
    font-size: 30px;
    cursor: pointer;
}

.carrossel-nav {
    position: absolute;
    top: 50%;
    width: 100%;
    display: flex;
    justify-content: space-between;
    transform: translateY(-50%);
}

.carrossel-prev, .carrossel-next {
    color: white;
    font-size: 30px;
    cursor: pointer;
    background: rgba(0, 0, 0, 0.5);
    padding: 10px 15px;
    border-radius: 50%;
}

.attachment-preview {
    cursor: pointer;
    margin-right: 5px;
}

.attachment-preview img {
    max-width: 100px;
    max-height: 100px;
    object-fit: cover;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.attachment-preview:hover img {
    border-color: #0d6efd;
}

.attachment-thumbnails {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.attachment-preview {
    display: flex;
    flex-direction: column;
    align-items: center; /* Centraliza os itens verticalmente */
    text-align: center; /* Centraliza o texto */
    width: 120px;
    padding: 10px;
    position: relative;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.attachment-preview:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #86b7fe;
}

.attachment-preview:hover .video-play-overlay {
    opacity: 1;
}

.attachment-preview img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    cursor: zoom-in;
}

/* Estilo específico para vídeos */
.attachment-preview video {
    width: 100%;
    height: 100px;
    object-fit: cover;
    background: #000;
    display: block;
}


.file-thumbnail {
    width: 100px;
    height: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
    color: #6c757d;
}

.file-thumbnail i {
    font-size: 36px;
    margin-bottom: 5px;
}

.file-thumbnail span {
    font-size: 12px;
    font-weight: 600;
}

.attachment-info {
    padding: 8px;
    font-size: 12px;
    line-height: 1.4;
}

.attachment-info small {
    display: block;
    margin-bottom: 2px;
}

.attachment-info small:first-child {
    font-weight: 500;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.attachment-info small.text-muted {
    color: #6c757d !important;
}

.video-label {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 500;
    z-index: 2;
}

/* Overlay de play para vídeos */
.video-play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.3);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 1;
}


.video-play-overlay i {
    color: white;
    font-size: 32px;
    text-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
.fancybox__container {
    --fancybox-bg: rgba(0, 0, 0, 0.95);
}

.fancybox__toolbar {
    padding: 10px;
    background: rgba(0, 0, 0, 0.5);
}

.fancybox__nav {
    --fancybox-navigation-color: #fff;
}

.fancybox__slide {
    padding: 10px;
}

.fancybox__content > .plyr {
    width: 80vw;
    height: 80vh;
    max-width: 1200px;
}

.fancybox__thumbs {
    --fancybox-thumbs-width: 60px;
    --fancybox-thumbs-height: 60px;
}

/* Player de vídeo personalizado */
.plyr {
    --plyr-color-main: #023324;
    --plyr-video-background: #000;
}

/* Ajustes para o player de vídeo */
.plyr--full-ui.plyr--video {
    border-radius: 8px;
    overflow: hidden;
    background: #000;
}

.plyr__controls {
    background: linear-gradient(transparent, rgba(0,0,0,0.7)) !important;
}

.plyr--video .plyr__control.plyr__tab-focus, 
.plyr--video .plyr__control:hover, 
.plyr--video .plyr__control[aria-expanded="true"] {
    background: var(--primary-color) !important;
}

.plyr--video .plyr__controls {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
}
/* Estilo específico para PDF */
.file-thumbnail.pdf {
    background-color: #f8f0f0;
    color: #d9534f;
}

/* Truncamento de texto */
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Espaçamento adicional */
.py-3 {
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.ck-editor__editable {
    min-height: 150px;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6 !important;
    border-radius: 0.375rem !important;
    padding: 0.5rem 1rem !important;
}

.ck-editor__editable:focus {
    border-color: #86b7fe !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}
/* Estilo para o editor de comentários */
#editorComentario .ck-editor__editable {
    min-height: 100px;
    border: 1px solid #dee2e6 !important;
    border-radius: 0.25rem !important;
}

/* Estilo para exibição de comentários */
.comment-body {
    word-break: break-word;
}
.comment-body p {
    margin-bottom: 0.5rem;
}
.comment-body ul, .comment-body ol {
    padding-left: 1.5rem;
}
/* Estilos para Comentários */
.comment-card {
    background-color: #fff;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.comment-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.comment-header {
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f3f5;
}

.comment-body {
    padding-top: 8px;
    line-height: 1.6;
}

.comment-body p:last-child {
    margin-bottom: 0;
}

.comment-actions .dropdown-toggle {
    padding: 2px 6px;
    border: none;
    background: transparent;
}

.comment-actions .dropdown-toggle::after {
    display: none;
}

.comment-actions .dropdown-menu {
    font-size: 14px;
}

.comment-actions .dropdown-item {
    padding: 6px 12px;
}

.comment-actions .dropdown-item .material-icons {
    font-size: 18px;
    vertical-align: middle;
    margin-right: 4px;
}

.avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: #023324;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    font-size: 12px;
}
.comment-card .badge[title] {
    cursor: help;
    font-size: 0.7rem;
    padding: 0.25em 0.4em;
    font-weight: normal;
}
/* Estilo para os checkboxes na lista */
.table .form-check {
    padding-left: 0;
    margin-bottom: 0;
}

.table .form-check-input {
    margin-left: 0;
    margin-top: 0;
}

/* Ajuste para o checkbox "selecionar todos" */
.table thead .form-check-input {
    margin-left: 0;
}
/* Container do select de pontos */
.kanban-card-points {
    width: 45%; /* Um pouco menos que metade para respirar */
    margin-left: auto; /* Alinha à direita */
}

/* Estilo do select */
.kanban-card-points select {
    width: 100%;
    font-size: 12px;
    padding: 4px 6px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background-color: #f9f9f9;
    cursor: pointer;
}
/* Loading Overlay Moderno */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    display: none;
    backdrop-filter: blur(5px);
    opacity: 0;
    transition: opacity 0.3s ease-out;
}

.loading-overlay.show {
    opacity: 1;
}

.loading-content {
    background: linear-gradient(145deg, #ffffff, #f8fafc);
    padding: 2rem;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 
                0 8px 10px -6px rgba(0, 0, 0, 0.1);
    max-width: 320px;
    width: 90%;
    text-align: center;
    transform: translateY(20px);
    transition: transform 0.3s ease-out;
}

.loading-overlay.show .loading-content {
    transform: translateY(0);
}

.loading-spinner {
    position: relative;
    width: 60px;
    height: 60px;
    margin-bottom: 1.5rem;
}

.loading-spinner::before,
.loading-spinner::after {
    content: '';
    position: absolute;
    border-radius: 50%;
}

.loading-spinner::before {
    width: 100%;
    height: 100%;
    background: conic-gradient(transparent, #3b82f6, transparent);
    animation: spin 1.2s linear infinite;
}

.loading-spinner::after {
    width: 80%;
    height: 80%;
    background: white;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-content p {
    margin: 0;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.5;
}

.loading-content .dots {
    display: inline-block;
    margin-left: 0.1em;
}

.loading-content .dots span {
    display: inline-block;
    animation: bounce 1.5s infinite ease-in-out;
    opacity: 0;
}

.loading-content .dots span:nth-child(1) {
    animation-delay: 0.1s;
}

.loading-content .dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.loading-content .dots span:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes bounce {
    0%, 100% { 
        transform: translateY(0); 
        opacity: 0;
    }
    50% { 
        transform: translateY(-5px); 
        opacity: 1;
    }
}

/* Adicione isso se quiser um efeito de pulso no card */
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

.loading-content {
    animation: pulse 2s infinite ease-in-out;
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

.checklist-item .form-control-sm {
    flex-grow: 1;
    min-width: 200px;
}

.checklist-item label {
    min-width: 200px; /* Largura mínima para o label */
    margin-bottom: 0;
}
/* Estilos para os filtros salvos */
.dropdown-menu .badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.3rem;
}

/* Ajuste para o dropdown de filtros */
.btn-group .dropdown-menu {
    max-height: 300px;
    overflow-y: auto;
}

/* Estilo para o modal de salvar filtro */
#salvarFiltroModal .modal-body {
    padding: 20px;
}

#salvarFiltroModal .form-control {
    margin-bottom: 10px;
}
/* Estilo para os itens do dropdown com ação de exclusão */
.dropdown-menu li {
    display: flex;
    align-items: center;
    padding: 0.25rem 1rem;
}

.dropdown-menu .dropdown-item {
    padding: 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.delete-filter {
    opacity: 0.5;
    transition: opacity 0.2s;
}

.delete-filter:hover {
    opacity: 1;
    color: #dc3545 !important;
}

/* Ajuste para o dropdown não ficar muito largo */
.dropdown-menu {
    min-width: 250px;
    max-width: 300px;
}
/* Ajuste para o dropdown de filtros salvos */
.dropdown-filtros .dropdown-menu {
    position: absolute;
    inset: 40px 0px auto auto !important;
    margin: 0 !important;
    transform: none !important;
}

/* Estilo para o dropdown de status */
.dropdown-status .dropdown-menu {
    min-width: 220px;
}

.dropdown-status .dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
}

.dropdown-status .badge {
    width: 20px;
    height: 20px;
    padding: 0;
    border-radius: 4px;
    margin-right: 10px;
}
/* Estilo para o spinner de status */
.status-loading {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
    vertical-align: middle;
}

/* Desabilita o hover durante o loading */
.dropdown-item.disabled {
    pointer-events: none;
    opacity: 0.7;
}
/* Estilos para o select de cliente personalizado */
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

    .custom-select .options div.selected {
        background-color: #0d6efd;
        color: white;
    }

    .no-results {
        padding: 8px 12px;
        color: #6c757d;
        font-style: italic;
    }
    .visualizacoes-indicator {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: #6c757d;
    transition: color 0.2s;
}

.visualizacoes-indicator:hover {
    color: #0d6efd;
}

.visualizacoes-indicator .material-icons {
    font-size: 18px;
}

.visualizacoes-count {
    font-size: 12px;
    font-weight: 500;
}

/* Kanban Stats */
.kanban-column-stats {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
}

.kanban-points {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 2px;
}

.kanban-points .material-icons {
    font-size: 14px;
}

/* Estilos para os containers recolhíveis */
.collapsible-container {
    margin-bottom: 1rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.collapsible-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1.25rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    cursor: pointer;
    user-select: none;
}

.collapsible-header h5 {
    margin-bottom: 0;
}

.collapsible-content {
    padding: 1.25rem;
}

.collapsible-content.collapsed {
    display: none;
}

.collapsible-icon {
    transition: transform 0.2s ease;
}

.collapsible-icon.collapsed {
    transform: rotate(-90deg);
}

/* ===== ESTILOS PERSONALIZADOS PARA FILTROS - CORES DA MARCA ===== */

/* Container dos filtros - mais compacto */
.sticky-top-container {
    background: linear-gradient(#f4f4f4);
    border: 1px solid #bbd36e;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(2, 51, 36, 0.1);
    margin-bottom: 20px;
}

.sticky-top-container .card {
    background: transparent;
    border: none;
    box-shadow: none;
}

.sticky-top-container .card-header {
    background: linear-gradient(135deg, #023324 0%, #8cc053 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 12px 20px;
    border: none;
}

.sticky-top-container .card-body {
    background: WHITE;
    border-radius: 0 0 12px 12px;
    padding: 15px 20px;
}

/* Botões principais com cores da marca */
.btn-primary {
     background: transparent;
    border: 1px solid #023324;
    color: #023324;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(2, 51, 36, 0.2);
}

.btn-primary:hover {
    background: #bbd36e;
    border-color: #bbd36e;
    color: #023324;
   transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(2, 51, 36, 0.3);
}

.btn-outline-primary {
    background: transparent;
    border: 2px solid #023324;
    color: #023324;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: #023324;
    border-color: #023324;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(2, 51, 36, 0.2);
}

.btn-outline-secondary {
    background: transparent;
    border: 1px solid #023324;
    color: #023324;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
    background: #bbd36e;
    border-color: #bbd36e;
    color: #023324;
    transform: translateY(-1px);
}

/* Botões compactos menores */
.compact-btn {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
    font-weight: 500;
}

.compact-btn .material-icons {
    font-size: 16px;
    vertical-align: middle;
    margin-top: -2px;
}

/* Form controls menores */
.card-body .form-select,
.card-body .form-control {
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid #bbd36e;
    background-color: white;
    transition: all 0.2s ease;
}

.card-body .form-select:focus,
.card-body .form-control:focus {
    border-color: #8cc053;
    box-shadow: 0 0 0 0.2rem rgba(140, 192, 83, 0.25);
}

.card-body .form-label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #023324;
    margin-bottom: 4px;
}

/* Badges de filtros ativos com cores da marca */
#activeFiltersContainer .badge {
    padding: 4px 8px;
    font-size: 0.7rem;
    font-weight: 500;
    border-radius: 12px;
    margin-right: 6px;
    margin-bottom: 4px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

#activeFiltersContainer .badge.bg-primary {
    background: linear-gradient(135deg, #023324 0%, #8cc053 100%) !important;
    border-color: #023324;
}

#activeFiltersContainer .badge.bg-info {
    background: linear-gradient(135deg, #ed691f 0%, #be5b31 100%) !important;
    color: white;
    border-color: #ed691f;
}

#activeFiltersContainer .badge.bg-warning {
    background: linear-gradient(135deg, #bbd36e 0%, #8cc053 100%) !important;
    color: #023324;
    border-color: #bbd36e;
}

#activeFiltersContainer .badge.bg-success {
    background: linear-gradient(135deg, #8cc053 0%, #bbd36e 100%) !important;
    color: #023324;
    border-color: #8cc053;
}

#activeFiltersContainer .badge.bg-secondary {
    background: linear-gradient(135deg, #023324 0%, #6c757d 100%) !important;
    color: white;
    border-color: #023324;
}

#activeFiltersContainer .badge.bg-dark {
    background: linear-gradient(135deg, #023324 0%, #343a40 100%) !important;
    color: white;
    border-color: #023324;
}

/* Hover nos badges */
#activeFiltersContainer .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Dropdown de filtros salvos */
.dropdown-filtros .btn {
    background: white;
    border: 1px solid #bbd36e;
    color: #023324;
}

.dropdown-filtros .btn:hover {
    background: #bbd36e;
    border-color: #8cc053;
    color: #023324;
}

/* Container de ações com visual mais limpo */
.d-flex.justify-content-between.align-items-center {
    
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}


.btn-outline-success {
background: transparent;
    border: 1px solid #023324;
    color: #023324;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;

}


.btn-outline-success:hover {
   background: #bbd36e;
    border-color: #bbd36e;
    color: #023324;
    transform: translateY(-1px);
}



/* Estilo para o total de chamados */
.badge.bg-primary {
    background: linear-gradient( #8cc053) !important;
    color: white;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 12px;
}

/* Responsividade para telas menores */
@media (max-width: 768px) {
    .compact-btn {
        padding: 4px 8px;
        font-size: 0.7rem;
    }
    
    .card-body .form-select,
    .card-body .form-control {
        font-size: 0.8rem;
        padding: 5px 10px;
    }
    
    #activeFiltersContainer .badge {
        font-size: 0.65rem;
        padding: 3px 6px;
    }
}

/* ===== ESTILOS PERSONALIZADOS PARA DASHBOARD STATS ===== */

/* Container principal dos stats - Versão compacta */
.row.mb-4 {
    margin-bottom: 1rem !important;
}

/* Cards de estatísticas com cores da marca - Versão compacta */
.row.mb-4 .card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
    overflow: hidden;
    position: relative;
    min-height: 70px;
}


.row.mb-4 .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Card Total - Efeito vidro com cor da fonte verde escuro */
.row.mb-4 .card.bg-primary {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #023324 !important;
}

.row.mb-4 .card.bg-primary::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 40px;
    background: rgba(2, 51, 36, 0.08);
    border-radius: 50%;
    transform: translate(15px, -15px);
}

/* Card Concluídos - Efeito vidro com cor da fonte verde claro */
.row.mb-4 .card.bg-success {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #8cc053 !important;
}

.row.mb-4 .card.bg-success::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 40px;
    background: rgba(140, 192, 83, 0.08);
    border-radius: 50%;
    transform: translate(15px, -15px);
}

/* Card Em Andamento - Efeito vidro com cor da fonte laranja */
.row.mb-4 .card.bg-warning {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ed691f !important;
}

.row.mb-4 .card.bg-warning::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 40px;
    background: rgba(237, 105, 31, 0.08);
    border-radius: 50%;
    transform: translate(15px, -15px);
}

/* Card Aplicar no Cliente - Efeito vidro com cor da fonte verde escuro */
.row.mb-4 .card.bg-danger {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #023324 !important;
}

.row.mb-4 .card.bg-danger::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40px;
    height: 40px;
    background: rgba(2, 51, 36, 0.08);
    border-radius: 50%;
    transform: translate(15px, -15px);
}

/* Corpo dos cards - Versão compacta */
.row.mb-4 .card-body {
    padding: 0.8rem;
    position: relative;
    z-index: 2;
}

/* Títulos dos cards - Versão compacta com melhor contraste */
.row.mb-4 .card-title {
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    opacity: 0.7;
}

/* Números dos cards - Versão compacta */
.row.mb-4 .card-text {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 0;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Ícones nos cards - Versão maior com cor mais forte */
.dashboard-icon {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 1.8rem;
    opacity: 0.8;
    z-index: 1;
    font-weight: bold;
}

/* Cores específicas para cada ícone */
.row.mb-4 .card.bg-primary .dashboard-icon {
    color: #023324 !important;
}

.row.mb-4 .card.bg-success .dashboard-icon {
    color: #8cc053 !important;
}

.row.mb-4 .card.bg-warning .dashboard-icon {
    color: #ed691f !important;
}

.row.mb-4 .card.bg-danger .dashboard-icon {
    color: #023324 !important;
}

/* Animação de pulso para números */
@keyframes pulse-number {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.row.mb-4 .card-text.updated {
    animation: pulse-number 0.6s ease-in-out;
}

/* Responsividade para dashboard stats - Versão compacta */
@media (max-width: 768px) {
    .row.mb-4 .card {
        margin-bottom: 0.5rem;
        min-height: 60px;
    }
    
    .row.mb-4 .card-body {
        padding: 0.6rem;
    }
    
    .row.mb-4 .card-text {
        font-size: 1.2rem;
    }
    
    .row.mb-4 .card-title {
        font-size: 0.65rem;
    }
    
    .dashboard-icon {
        font-size: 1rem;
        top: 6px;
        right: 6px;
    }
}

@media (max-width: 576px) {
    .row.mb-4 .card-text {
        font-size: 1rem;
    }
    
    .row.mb-4 .card-title {
        font-size: 0.6rem;
    }
    
    .dashboard-icon {
        font-size: 0.9rem;
    }
}

/* ===== OTIMIZAÇÕES ADICIONAIS ===== */

/* Global Actions Container - Menor e mais otimizado */
.global-actions-container {
    padding: 8px 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    background: white;
    border: 1px solid rgba(2, 51, 36, 0.1);
}

.global-actions-container .btn {
    padding: 4px 8px;
    font-size: 0.85rem;
    margin: 2px;
}

/* Card Header - Menor e mais otimizado */
.card-header.d-flex.justify-content-between.align-items-center {
    padding: 8px 12px;
    font-size: 0.9rem;
    font-weight: 500;
    background: white;
    border-bottom: 1px solid rgba(2, 51, 36, 0.1);
}

/* Form Check Input - Cores da marca */
.form-check-input:checked {
    background-color: #023324;
    border-color: #023324;
    box-shadow: 0 0 0 0.2rem rgba(2, 51, 36, 0.25);
}

.form-check-input:focus {
    border-color: #8cc053;
    box-shadow: 0 0 0 0.2rem rgba(140, 192, 83, 0.25);
}

/* Estados ativos dos botões - Cores da marca */
.btn-check:checked+.btn, 
.btn.active, 
.btn.show, 
.btn:first-child:active, 
:not(.btn-check)+.btn:active {
    color: white !important;
    background-color: #023324 !important;
    border-color: #023324 !important;
    box-shadow: 0 0 0 0.2rem rgba(2, 51, 36, 0.25);
}

.btn-check:checked+.btn:hover, 
.btn.active:hover, 
.btn.show:hover {
    background-color: #8cc053 !important;
    border-color: #8cc053 !important;
    color: #023324 !important;
}

/* ===== ESTILOS PERSONALIZADOS PARA KANBAN - CORES DA MARCA ===== */

/* Kanban Container - Otimizado */
.kanban-container {
    margin-bottom: 15px;
    margin-top: 0;
}

.kanban-scroll-container {
    padding-bottom: 10px;
}

.kanban-board {
    gap: 12px;
    padding: 8px 0;
}

/* Kanban Column Wrapper - Cores da marca */
.kanban-column-wrapper {
    width: 270px;
    min-width: 270px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(2, 51, 36, 0.08);
    border: 1px solid rgba(2, 51, 36, 0.1);
}

/* Kanban Column Header - Cores da marca */
.kanban-column-header {
    padding: 8px 12px;
    background: #023324;
    color: white;
    border-radius: 8px 8px 0 0;
    border-left: none;
    box-shadow: 0 2px 4px rgba(2, 51, 36, 0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 40px;
}

.kanban-column-title {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
    line-height: 1.2;
    flex: 1;
}

.kanban-column-title .form-check-label {
    color: white;
    font-weight: 600;
    margin: 0;
    line-height: 1.2;
}

.kanban-column-title .form-check-input {
    border-color: rgba(255, 255, 255, 0.5);
    background-color: transparent;
    margin-right: 6px;
}

.kanban-column-title .form-check-input:checked {
    background-color: #f5ece3;
    border-color: #f5ece3;
    color: #023324;
}

/* Kanban Count - Otimizado */
.kanban-count {
    background: rgba(245, 236, 227, 0.9);
    color: #023324;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    flex-shrink: 0;
}

/* Kanban Points - Cores da marca */
.kanban-points {
    background: rgba(245, 236, 227, 0.9);
    color: #023324;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 4px;
    flex-shrink: 0;
}

/* Kanban Column - Otimizado */
.kanban-column {
    padding: 8px;
    min-height: 80px;
    max-height: calc(100vh - 280px);
}

/* Kanban Cards - Cores da marca e otimizado */
.kanban-card {
    background: linear-gradient(135deg, #ffffff, rgba(245, 236, 227, 0.1));
    border-radius: 8px;
    padding: 10px 10px 10px 30px;
    margin-bottom: 8px;
    box-shadow: 0 2px 6px rgba(2, 51, 36, 0.08);
    border: 1px solid rgba(140, 192, 83, 0.2);
    transition: all 0.2s ease;
}

.kanban-card:hover {
    box-shadow: 0 4px 12px rgba(2, 51, 36, 0.15);
    transform: translateY(-1px);
    border-color: #8cc053;
}

.kanban-card.selected {
    background: linear-gradient(135deg, rgba(140, 192, 83, 0.1), rgba(245, 236, 227, 0.3));
    border-color: #8cc053;
    box-shadow: 0 0 0 2px rgba(140, 192, 83, 0.3);
}

/* Kanban Card Header - Otimizado */
.kanban-card-header {
    margin-bottom: 6px;
}

.kanban-card-number {
    font-size: 12px;
    color: #8cc053;
    font-weight: 600;
}

/* Kanban Card Body - Otimizado */
.kanban-card-body {
    gap: 6px;
    margin-bottom: 8px;
}

.kanban-card-icon {
    color: #023324;
    font-size: 16px;
}

.kanban-card-title {
    font-size: 13px;
    
    color: #023324;
}

.kanban-card-title:hover {
    color: #8cc053;
}

/* Kanban Card Client e Type - Otimizado */
.kanban-card-client,
.kanban-card-type {
    font-size: 11px;
    color: #be5b31;
    margin-bottom: 8px;
}

.kanban-card-type {
    margin-top: -4px;
}

/* Kanban Card Footer - Otimizado */
.kanban-card-footer {
    font-size: 11px;
}

.kanban-card-responsible {
    color: #023324;
}

/* Kanban Avatar - Cores da marca */
.kanban-avatar {
    width: 20px;
    height: 20px;
    font-size: 10px;
    background: linear-gradient(135deg, #023324, #8cc053);
    color: white;
    font-weight: 600;
}






/* Kanban Empty State */
.kanban-empty {
    padding: 15px 0;
    color: rgba(2, 51, 36, 0.4);
    font-size: 12px;
}

.kanban-empty .material-icons {
    font-size: 20px;
    color: rgba(140, 192, 83, 0.3);
}

/* Kanban Actions Buttons */
.kanban-add-btn,
.kanban-bulk-actions-btn {
    color: rgba(245, 236, 227, 0.8);
}

.kanban-add-btn:hover,
.kanban-bulk-actions-btn:hover {
    background: rgba(245, 236, 227, 0.2);
    color: #f5ece3;
}

/* Drag and Drop States */
.kanban-ghost {
    opacity: 0.6;
    background: linear-gradient(135deg, rgba(140, 192, 83, 0.1), rgba(245, 236, 227, 0.2));
    border: 2px dashed #8cc053;
}

.kanban-drag {
    transform: rotate(1deg);
    box-shadow: 0 6px 20px rgba(2, 51, 36, 0.2) !important;
}

.kanban-drop {
    background: linear-gradient(135deg, rgba(140, 192, 83, 0.05), rgba(245, 236, 227, 0.1));
}

/* Responsividade para Kanban */
@media (max-width: 768px) {
    .kanban-column-wrapper {
        width: 250px;
        min-width: 250px;
    }
    
    .kanban-card {
        padding: 8px 8px 8px 25px;
        margin-bottom: 6px;
    }
    
    .kanban-card-title {
        font-size: 12px;
    }
    
    .kanban-card-client,
    .kanban-card-type {
        font-size: 10px;
    }
}

/* ===== ESTILOS PERSONALIZADOS PARA VISUALIZAÇÃO EM LISTA - CORES DA MARCA ===== */

/* Container da tabela */
.table-responsive {
    background: linear-gradient(#f4f4f4);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(2, 51, 36, 0.08);
    border: 1px solid rgba(140, 192, 83, 0.2);
}

/* Tabela principal */
.table {
    background: transparent;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 0;
}

/* Cabeçalho da tabela */
.table thead th {
    background: #023324;
    color: white;
    border: none;
    padding: 8px 12px;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
    text-align: center;
    vertical-align: middle;
}

.table thead th:first-child {
    border-top-left-radius: 8px;
}

.table thead th:last-child {
    border-top-right-radius: 8px;
}

/* Alinhamento específico para colunas de texto */
.table thead th:nth-child(3), /* Título */
.table thead th:nth-child(8), /* Cliente */
.table thead th:nth-child(9), /* Responsável */
.table thead th:nth-child(10) { /* Criado por */
    text-align: left;
}

/* Linhas da tabela */
.table tbody tr {
    background: linear-gradient(145deg, #ffffff 0%, #f5ece3 100%);
    border: none;
    transition: all 0.3s ease;
}

.table tbody tr:nth-child(even) {
    background: linear-gradient(145deg, #f5ece3 0%, #ffffff 100%);
}

.table tbody tr:hover {
    background: linear-gradient(145deg, #8cc053 0%, #f5ece3 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(2, 51, 36, 0.1);
}

/* Células da tabela */
.table tbody td {
    border: none;
    padding: 12px 16px;
    vertical-align: middle;
    border-bottom: 1px solid rgba(140, 192, 83, 0.1);
    white-space: nowrap;
    text-align: center;
}

/* ID do chamado */
.table tbody td:nth-child(2) {
    color: #8cc053;
    font-weight: 600;
    font-size: 14px;
}

/* Título com ícone */
.table tbody td:nth-child(3) {
    color: #023324;
    
    text-align: left;
    white-space: normal;
    max-width: 300px;
    word-wrap: break-word;
}

.table tbody td:nth-child(3) .type-icon {
    color: #023324;
    font-size: 16px;
    margin-right: 8px;
    vertical-align: middle;
}

.table tbody td:nth-child(3) a {
    color: #023324;
    text-decoration: none;
    transition: color 0.2s ease;
}

.table tbody td:nth-child(3) a:hover {
    color: #8cc053;
    text-decoration: underline;
}

/* Tipo */
.table tbody td:nth-child(4) {
    color: #ed691f;
    font-weight: 500;
    font-size: 13px;
}

/* Badge de prioridade customizado */
.table .priority-badge {
    border: none;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Badge de status customizado */
.table .badge {
    border: none;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

/* Pontos de história */
.table .bg-info {
    background: linear-gradient(135deg, #8cc053 0%, #bbd36e 100%) !important;
    color: white;
    border: none;
}

/* Cliente */
.table tbody td:nth-child(8) {
    color: #ed691f;
    font-size: 13px;
    text-align: left;
    white-space: normal;
    max-width: 200px;
    word-wrap: break-word;
}

/* Responsável com avatar */
.table tbody td:nth-child(9) {
    color: #023324;
    font-size: 13px;
    text-align: left;
    white-space: normal;
    min-width: 150px;
}

.table tbody td:nth-child(9) .d-inline-block {
    background: linear-gradient(135deg, #023324 0%, #8cc053 100%) !important;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    margin-right: 8px;
    vertical-align: middle;
}

/* Criado por */
.table tbody td:nth-child(10) {
    color: #023324;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    min-width: 120px;
    text-align: left;
}

/* Data de criação */
.table tbody td:nth-child(11) {
    color: #ed691f;
    font-size: 14px;
    white-space: nowrap;
    min-width: 100px;
}

/* Botão de ação */
.table tbody td:nth-child(12) .btn-outline-primary {
    border-color: #8cc053;
    color: #8cc053;
    background: transparent;
    transition: all 0.2s ease;
}

.table tbody td:nth-child(12) .btn-outline-primary:hover {
    background: #8cc053;
    border-color: #8cc053;
    color: white;
    transform: translateY(-1px);
}

/* Checkbox personalizado */
.table .form-check-input {
    border: 2px solid rgba(140, 192, 83, 0.4);
    background-color: rgba(255, 255, 255, 0.8);
    transition: all 0.2s ease;
}

.table .form-check-input:checked {
    background-color: #8cc053;
    border-color: #8cc053;
}

.table .form-check-input:focus {
    border-color: #8cc053;
    box-shadow: 0 0 0 2px rgba(140, 192, 83, 0.2);
}

/* Mensagem de "nenhum chamado" */
.table tbody td.text-center.text-muted {
    color: rgba(2, 51, 36, 0.6) !important;
    font-style: italic;
    padding: 40px;
    background: linear-gradient(145deg, #f5ece3 0%, #ffffff 100%);
}

/* Responsividade para tabela */
@media (max-width: 768px) {
    .table-responsive {
        padding: 15px;
    }
    
    .table thead th {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .table tbody td {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .table tbody td:nth-child(3) .type-icon {
        font-size: 14px;
        margin-right: 4px;
    }
    
    .table tbody td:nth-child(9) .d-inline-block {
        width: 24px;
        height: 24px;
        font-size: 10px;
        margin-right: 6px;
    }
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
                <i class="material-icons">support_agent</i>
                <span>Sistema de Chamados</span>
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

<!-- Sub-navegação dos chamados -->
<div class="chamados-nav">
    <div class="container">
        <?php
        // Detecta a página atual
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>
        <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">
            <i class="material-icons">dashboard</i> Painel
        </a>
        <a class="nav-link <?= ($current_page == 'criar.php') ? 'active' : '' ?>" href="criar.php">
            <i class="material-icons">add</i> Novo Chamado
        </a>
        <a class="nav-link <?= ($current_page == 'sprints.php') ? 'active' : '' ?>" href="sprints.php">
            <i class="material-icons">date_range</i> Sprints
        </a>
        <a class="nav-link <?= ($current_page == 'releases.php') ? 'active' : '' ?>" href="releases.php">
            <i class="material-icons">rocket</i> Releases
        </a>
             <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'relatorios.php') ? 'active' : ''; ?>" href="relatorios.php">
            <i class="material-icons">assessment</i> Relatórios
        </a>
    </div>
</div>

    <div class="container mb-5">

<!-- Scripts para os dropdowns do header -->
<script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alternar visibilidade do dropdown de Suporte
    document.getElementById('supportButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');

        suporteDropdown.classList.toggle('visible');
        conhecimentoDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Conhecimento
    document.getElementById('knowledgeButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');

        conhecimentoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Treinamento
    document.getElementById('trainingButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const cadastroDropdown = document.getElementById('registerMenu');

        treinamentoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Cadastros
    document.getElementById('registerButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const cadastroDropdown = document.getElementById('registerMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');

        cadastroDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(event) {
        const suporteButton = document.getElementById('supportButton');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoButton = document.getElementById('knowledgeButton');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoButton = document.getElementById('trainingButton');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroButton = document.getElementById('registerButton');
        const cadastroDropdown = document.getElementById('registerMenu');

        if (suporteDropdown && suporteButton && !suporteDropdown.contains(event.target) && !suporteButton.contains(event.target)) {
            suporteDropdown.classList.remove('visible');
        }
        
        if (conhecimentoDropdown && conhecimentoButton && !conhecimentoDropdown.contains(event.target) && !conhecimentoButton.contains(event.target)) {
            conhecimentoDropdown.classList.remove('visible');
        }
        
        if (treinamentoDropdown && treinamentoButton && !treinamentoDropdown.contains(event.target) && !treinamentoButton.contains(event.target)) {
            treinamentoDropdown.classList.remove('visible');
        }
        
        if (cadastroDropdown && cadastroButton && !cadastroDropdown.contains(event.target) && !cadastroButton.contains(event.target)) {
            cadastroDropdown.classList.remove('visible');
        }
    });

    // Logout
    document.getElementById('logoutButton')?.addEventListener('click', function() {
        const confirmacao = confirm("Você realmente deseja sair?");
        if (confirmacao) {
            window.location.href = '/HelpDesk_Nutify/paginas/logout.php';
        }
    });
});
</script>