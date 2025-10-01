<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once 'functions_treinamentos.php';

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
$_SESSION['id'] = $usuario['id'];

// Buscar lista de clientes para JavaScript
$clientes_list = getClientes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Treinamentos</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
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

   /* Atualize os estilos dos dropdowns no header_treinamentos.php */

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

    /* Sub-navegação dos treinamentos - Atualizada para ficar igual aos chamados */
    .treinamentos-nav {
        background-color: rgba(2, 51, 36, 0.1);
        padding: 5px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .treinamentos-nav .container {
        display: flex;
        align-items: center;
    }

    .treinamentos-nav .nav-link {
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

    .treinamentos-nav .nav-link:hover {
        background-color: rgba(2, 51, 36, 0.1);
    }


    .status-container-wrapper {
    
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.status-indicators-container {
    gap: 0;
}

.status-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    padding: 8px 12px;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 80px;
    border-radius: 6px;
}

.status-indicator:hover {
    background-color: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-indicator small {
    white-space: nowrap;
    font-size: 0.9rem;
    margin-top: 2px;
}

/* Melhorar a aparência dos tooltips */
.tooltip {
    font-size: 0.875rem;
}

.tooltip-inner {
    max-width: 300px;
    text-align: left;
    padding: 8px 12px;
    background-color: #424242;
}

.tooltip.bs-tooltip-top .tooltip-arrow::before {
    border-top-color: #424242;
}

.tooltip.bs-tooltip-bottom .tooltip-arrow::before {
    border-bottom-color: #424242;
}

.tooltip.bs-tooltip-start .tooltip-arrow::before {
    border-left-color: #424242;
}

.tooltip.bs-tooltip-end .tooltip-arrow::before {
    border-right-color: #424242;
}

/* Responsividade para telas menores */
@media (max-width: 768px) {
    .status-indicators-container {
        flex-wrap: wrap;
    }
    
    .status-indicator {
        min-width: 70px;
        padding: 6px 8px;
    }
    
    .status-indicator small {
        font-size: 0.8rem;
    }
}

@media (max-width: 576px) {
    .status-indicator {
        min-width: 60px;
        padding: 4px 6px;
    }
    
    .badge-dot {
        width: 10px;
        height: 10px;
    }
    
    .status-indicator small {
        font-size: 0.75rem;
    }
}

    .treinamentos-nav .nav-link.active {
        background-color: #28a745;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .treinamentos-nav .nav-link.active:hover {
        background-color: #218838;
    }

    .treinamentos-nav .nav-link i {
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

.comissao-real-values,
.valor-hora-real-values {
    display: none;
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
}
.dropdown-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 4px 4px;
    background: white;
    z-index: 1000;
}

.dropdown-item {
    padding: 8px 12px;
    cursor: pointer;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.no-results {
    color: #6c757d;
    font-style: italic;
}

.highlight {
    background-color: #fff3cd;
    font-weight: bold;
}

.d-none {
        display: none !important;
    }

    /* ===== ESTILOS PERSONALIZADOS PARA DASHBOARD STATS ===== */

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

    /* Card Info - Efeito vidro com cor da fonte verde escuro */
    .row.mb-4 .card.bg-info {
        background: rgba(255, 255, 255, 0.1) !important;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #023324 !important;
    }

    .row.mb-4 .card.bg-info::before {
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

    /* Estilos para ícones do dashboard - igual aos chamados */
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

    .row.mb-4 .card.bg-info .dashboard-icon {
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

    /* ===== ESTILOS DOS FILTROS (IDÊNTICOS AOS CHAMADOS) ===== */

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

    /* Card Header - Menor e mais otimizado */
    .card-header.d-flex.justify-content-between.align-items-center {
        padding: 8px 12px;
        font-size: 0.9rem;
        font-weight: 500;
        
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

    /* Container de ações com visual mais limpo */
    .d-flex.justify-content-between.align-items-center {
        
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
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

    /* Adicione isso na seção de estilos do header_treinamentos.php */

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
        background: transparent;
    border: 1px solid #023324;
    color: #023324;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
    }
    


     .card-header .btn:hover {
           background: #bbd36e;
    border-color: #bbd36e;
    color: #023324;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(2, 51, 36, 0.2);
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

/* Estilos para botões de comentários */
.comment-edit-btn:hover {
    background-color: #007bff !important;
    color: white !important;
    transition: all 0.3s ease;
}

.comment-delete-btn:hover {
    background-color: #dc3545 !important;
    color: white !important;
    transition: all 0.3s ease;
}

.comment-edit-btn:hover .material-icons,
.comment-delete-btn:hover .material-icons {
    color: white !important;
}

.comment-edit-btn,
.comment-delete-btn {
    transition: all 0.3s ease;
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
                <i class="material-icons">school</i>
                <span>Controle de Treinamentos</span>
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

<!-- Navbar de treinamentos simplificada -->
<!-- Sub-navegação dos treinamentos com navegação ativa -->
<div class="treinamentos-nav">
    <div class="container">
        <?php
        // Detecta a página atual
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>
        <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">
            <i class="material-icons">dashboard</i> Painel
        </a>
        <a class="nav-link <?= ($current_page == 'criar.php') ? 'active' : '' ?>" href="criar.php">
            <i class="material-icons">add</i> Novo Treinamento
        </a>
           <a class="nav-link <?= ($current_page == 'relatorios.php') ? 'active' : '' ?>" href="relatorios.php">
                <i class="material-icons">assessment</i> Relatórios
            </a>
        <?php if ($is_admin == 1): ?>
            <a class="nav-link <?= ($current_page == 'comissoes.php') ? 'active' : '' ?>" href="comissoes.php">
                <i class="material-icons">analytics</i> Comissões
            </a>
        <?php endif; ?>
    </div>
</div>

    <div class="container mb-5">