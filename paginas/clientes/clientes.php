<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Filtros
$filtro_contrato = $_GET['contrato'] ?? '';
$filtro_nome = $_GET['nome'] ?? '';
$filtro_cnpj = $_GET['cnpj'] ?? '';
$filtro_plano = $_GET['plano'] ?? '';
$filtro_modulo = $_GET['modulo'] ?? '';
$filtro_grupo = $_GET['grupo'] ?? '';
$filtro_contato = $_GET['contato'] ?? ''; // Novo filtro por contato

// Parâmetros de ordenação
$ordenar_por = $_GET['ordenar_por'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

// Paginação
$limite = 30;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Consulta base com JOIN para contatos
$query = "SELECT DISTINCT c.* FROM clientes c 
          LEFT JOIN cliente_contato cc ON c.id = cc.cliente_id 
          LEFT JOIN contatos co ON cc.contato_id = co.id 
          WHERE 1=1";

// Aplica filtros
if (!empty($filtro_contrato)) {
    $query .= " AND c.contrato LIKE '%$filtro_contrato%'";
}
if (!empty($filtro_nome)) {
    $query .= " AND c.nome LIKE '%$filtro_nome%'";
}
if (!empty($filtro_cnpj)) {
    $query .= " AND c.cnpj LIKE '%$filtro_cnpj%'";
}
if (!empty($filtro_plano)) {
    $query .= " AND c.plano = '$filtro_plano'";
}
if (!empty($filtro_modulo)) {
    $query .= " AND c.modulos LIKE '%$filtro_modulo%'";
}
if (!empty($filtro_grupo)) {
    $query .= " AND c.id_grupo = '$filtro_grupo'";
}
if (!empty($filtro_contato)) {
    $query .= " AND (co.nome LIKE '%$filtro_contato%' OR co.telefone LIKE '%$filtro_contato%')";
}

// Ordenação e paginação
$query .= " ORDER BY c.$ordenar_por $direcao LIMIT $limite OFFSET $offset";

$result_clientes = $conn->query($query);
$clientes = $result_clientes->fetch_all(MYSQLI_ASSOC);

// Consulta para total de clientes (com mesmos filtros)
$query_total = "SELECT COUNT(DISTINCT c.id) as total FROM clientes c 
                LEFT JOIN cliente_contato cc ON c.id = cc.cliente_id 
                LEFT JOIN contatos co ON cc.contato_id = co.id 
                WHERE 1=1";

if (!empty($filtro_contrato)) {
    $query_total .= " AND c.contrato LIKE '%$filtro_contrato%'";
}
if (!empty($filtro_nome)) {
    $query_total .= " AND c.nome LIKE '%$filtro_nome%'";
}
if (!empty($filtro_cnpj)) {
    $query_total .= " AND c.cnpj LIKE '%$filtro_cnpj%'";
}
if (!empty($filtro_plano)) {
    $query_total .= " AND c.plano = '$filtro_plano'";
}
if (!empty($filtro_modulo)) {
    $query_total .= " AND c.modulos LIKE '%$filtro_modulo%'";
}
if (!empty($filtro_grupo)) {
    $query_total .= " AND c.id_grupo = '$filtro_grupo'";
}
if (!empty($filtro_contato)) {
    $query_total .= " AND (co.nome LIKE '%$filtro_contato%' OR co.telefone LIKE '%$filtro_contato%')";
}

$result_total = $conn->query($query_total);
$total_clientes = $result_total->fetch_assoc()['total'];

// Calcular total de páginas
$total_paginas = ceil($total_clientes / $limite);

// Busca dados para filtros
$query_planos = "SELECT * FROM planos ORDER BY nome";
$result_planos = $conn->query($query_planos);
$planos_filtro = $result_planos->fetch_all(MYSQLI_ASSOC);

$query_modulos = "SELECT * FROM modulos ORDER BY nome";
$result_modulos = $conn->query($query_modulos);
$modulos_filtro = $result_modulos->fetch_all(MYSQLI_ASSOC);

$query_grupos = "SELECT * FROM grupos ORDER BY nome";
$result_grupos = $conn->query($query_grupos);
$grupos_filtro = $result_grupos->fetch_all(MYSQLI_ASSOC);

// Mapeamentos
$planos_map = array_column($planos_filtro, 'nome', 'id');
$modulos_map = array_column($modulos_filtro, 'nome', 'id');
$grupos_map = array_column($grupos_filtro, 'nome', 'id');



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
    <title>Listagem de Clientes</title>
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
    
    /* ESTILOS MELHORADOS PARA FILTROS E TABELA */
    .filtros {
        background-color: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid #e0e0e0;
    }

    .filtros h2 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: #023324;
        font-weight: 600;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }

    .filtros-form {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        align-items: flex-end;
    }

    .filtro-group {
        margin-bottom: 0;
    }

    .filtro-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
        font-size: 13px;
    }

    .filtro-group input,
    .filtro-group select {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
        background-color: #f9f9f9;
    }

    .filtro-group input:focus,
    .filtro-group select:focus {
        outline: none;
        border-color: #8CC053;
        background-color: white;
    }

    .filtro-group button {
        background-color: #023324;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        transition: background-color 0.2s ease;
        width: 100%;
    }

    .filtro-group button:hover {
        background-color: #01261b;
    }

    .limpar-filtros {
        color: #8CC053;
        text-decoration: none;
        font-size: 13px;
        display: inline-block;
        margin-top: 8px;
        transition: color 0.2s ease;
    }

    .limpar-filtros:hover {
        color: #023324;
        text-decoration: underline;
    }

    /* Estilo para o título da tabela */
    .section-title {
        font-size: 16px;
        color: #023324;
        font-weight: 600;
        margin: 15px 0 10px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }

    /* Ajustes na tabela */
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 5px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        background-color: white;
        border: 1px solid #e0e0e0;
    }

    table th,
    table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    table th {
        background-color: #023324;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    table tr {
        transition: background-color 0.3s ease;
    }

    table tr:nth-child(even) {
        background-color: #f8f8f8;
    }

    table tr:hover {
        background-color: #f1f1f1;
    }

    table td {
        color: #333;
        font-size: 13px;
    }

    table td a {
        color: #8CC053;
        text-decoration: none;
        margin-right: 10px;
        transition: color 0.3s ease;
        font-size: 13px;
    }

    table td a:hover {
        color: #023324;
        text-decoration: underline;
    }

    table td:first-child,
    table th:first-child {
        padding-left: 15px;
    }

    table td:last-child,
    table th:last-child {
        padding-right: 15px;
    }

    table tr:last-child td {
        border-bottom: none;
    }

.contador-atendimentos {
    font-size: 13px;
    color: #555;
    margin: 5px 0 10px 0;
    padding: 0;
    text-align: right; /* Alinha o texto à direita */
}

    .hr {
        margin: 10px 0;
        border: none;
        border-top: 1px solid #eee;
    }

    /* Remover cores dos links dos cabeçalhos */
    table#tabela-clientes th a {
        color: inherit;
        text-decoration: none;
    }

    /* Estilo para ordenação */
    th a {
        color: inherit;
        text-decoration: none;
        display: block;
    }

    th a.asc:after {
        content: " ▲";
        color: #f1f1f1;
        font-size: 0.8em;
    }

    th a.desc:after {
        content: " ▼";
        color: #f1f1f1;
        font-size: 0.8em;
    }
</style>
    <script>
        // Funções para formatação e validação
        function formatarContrato(input) {
            let valor = input.value.replace(/\D/g,'');
            if (valor.length > 6) valor = valor.substring(0, 6) + '.' + valor.substring(6);
            if (valor.length > 3) valor = valor.substring(0, 3) + '.' + valor.substring(3);
            input.value = valor;
        }
        
        function validarContrato(contrato) {
            const regex = /^\d{3}\.\d{3}\.\d{2}$/;
            return regex.test(contrato);
        }
        
        function formatarCNPJ(input) {
            let valor = input.value.replace(/\D/g, '');
            if (valor.length > 14) valor = valor.substring(0, 14);
            valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
            valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            valor = valor.replace(/\.(\d{3})(\d)/, '.$1/$2');
            valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
            input.value = valor;
        }
        
        function validarCNPJ(cnpj) {
            cnpj = cnpj.replace(/[^\d]+/g,'');
            if (cnpj.length !== 14) return false;
            if (/^(\d)\1{13}$/.test(cnpj)) return false;
            
            let tamanho = cnpj.length - 2;
            let numeros = cnpj.substring(0, tamanho);
            let digitos = cnpj.substring(tamanho);
            let soma = 0;
            let pos = tamanho - 7;
            
            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
            if (resultado != digitos.charAt(0)) return false;
            
            tamanho = tamanho + 1;
            numeros = cnpj.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;
            
            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
            return resultado == digitos.charAt(1);
        }
    </script>
</head>
<body>
    <!-- Header fixo com os mesmos menus do principal.php -->
    <header id="main-header">
        <!-- Container principal que agrupa título e botões -->
        <div class="header-content">
            <!-- Título do sistema -->
            <div class="header-title-container">
                <div class="header-title">
                    <i class="fas fa-users"></i>
                    <span>Gestão de Clientes</span>
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
            <a class="nav-link active" href="clientes.php"><i class="fas fa-list"></i> Lista de Clientes</a>
            <a class="nav-link" href="cadastrar_cliente.php"><i class="fas fa-plus"></i> Novo Cliente</a>
            <a class="nav-link" href="contatos.php"><i class="fas fa-address-book"></i> Contatos</a>
            <a class="nav-link" href="grupos.php"><i class="fas fa-users"></i> Grupos</a>
            <a class="nav-link" href="importar_clientes.php"><i class="fas fa-file-import"></i> Importar</a>
        </div>
    </div>

    <div class="container mb-5">
        <main>
           <!-- Filtros -->
<section class="filtros">
    <h2>Filtros</h2>
    <form method="GET" action="clientes.php" class="filtros-form">
        <!-- Filtro de Contrato -->
        <div class="filtro-group">
            <label for="contrato">Contrato:</label>
            <input type="text" id="contrato" name="contrato" placeholder="000.000.00" 
                   value="<?= htmlspecialchars($filtro_contrato) ?>"
                   oninput="formatarContrato(this)"
                   onblur="if(!validarContrato(this.value) && this.value.length > 0) alert('Formato de contrato inválido. Use 000.000.00');">
        </div>

        <!-- Filtro de Nome -->
        <div class="filtro-group">
            <label for="nome">Cliente:</label>
            <input type="text" id="nome" name="nome" placeholder="Digite o nome" value="<?= htmlspecialchars($filtro_nome) ?>">
        </div>

        <!-- Filtro de CNPJ -->
        <div class="filtro-group">
            <label for="cnpj">CNPJ:</label>
            <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" 
                   value="<?= htmlspecialchars($filtro_cnpj) ?>"
                   oninput="formatarCNPJ(this)"
                   onblur="if(!validarCNPJ(this.value) && this.value.length > 0) alert('CNPJ inválido');">
        </div>

        <!-- Filtro por Contato -->
        <div class="filtro-group">
            <label for="contato">Contato (nome/tel):</label>
            <input type="text" id="contato" name="contato" placeholder="Nome ou telefone" value="<?= htmlspecialchars($filtro_contato) ?>">
        </div>

        <!-- Filtro de Plano -->
        <div class="filtro-group">
            <label for="plano">Plano:</label>
            <select id="plano" name="plano">
                <option value="">Todos</option>
                <?php foreach ($planos_filtro as $plano): ?>
                    <option value="<?= $plano['id'] ?>" <?= ($filtro_plano == $plano['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($plano['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro de Módulo -->
        <div class="filtro-group">
            <label for="modulo">Módulo:</label>
            <select id="modulo" name="modulo">
                <option value="">Todos</option>
                <?php foreach ($modulos_filtro as $modulo): ?>
                    <option value="<?= $modulo['id'] ?>" <?= ($filtro_modulo == $modulo['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($modulo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro de Grupo -->
        <div class="filtro-group">
            <label for="grupo">Grupo:</label>
            <select id="grupo" name="grupo">
                <option value="">Todos</option>
                <?php foreach ($grupos_filtro as $grupo): ?>
                    <option value="<?= $grupo['id'] ?>" <?= ($filtro_grupo == $grupo['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($grupo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

   <!-- Botões de Filtro -->
<div class="filtro-group" style="display: flex; gap: 10px; align-items: center;">
    <button type="submit" style="padding: 6px 12px; font-size: 12px;">Filtrar</button>
    <a href="clientes.php" style="padding: 6px 12px; font-size: 12px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; color: #023324; text-decoration: none;">Limpar</a>
</div>
    </form>
</section>

<!-- Contador de Clientes -->
<div class="contador-atendimentos">
    <strong>Total de Clientes:</strong> <?= $total_clientes ?>
</div>

<hr class="hr">

<!-- Tabela de Clientes -->
<section>
    <h2 class="section-title">Registros de Clientes</h2>
    <table id="tabela-clientes">
                    <thead>
                        <tr>
                            <th>
                                <a href="?ordenar_por=id&direcao=<?= ($ordenar_por == 'id') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'id') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    ID
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=nome&direcao=<?= ($ordenar_por == 'nome') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'nome') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Cliente
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=cnpj&direcao=<?= ($ordenar_por == 'cnpj') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'cnpj') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    CNPJ
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=email&direcao=<?= ($ordenar_por == 'email') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'email') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Email
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=telefone&direcao=<?= ($ordenar_por == 'telefone') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'telefone') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Telefone
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=plano&direcao=<?= ($ordenar_por == 'plano') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'plano') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Plano
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=modulos&direcao=<?= ($ordenar_por == 'modulos') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'modulos') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Módulos
                                </a>
                            </th>
                            <th>
                                <a href="?ordenar_por=id_grupo&direcao=<?= ($ordenar_por == 'id_grupo') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC' ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>"
                                   class="<?= ($ordenar_por == 'id_grupo') ? ($direcao == 'ASC' ? 'asc' : 'desc') : '' ?>">
                                    Grupo
                                </a>
                            </th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                            $plano_nome = $planos_map[$cliente['plano']] ?? 'Desconhecido';
                            
                            $modulos_ids = explode(",", $cliente['modulos']);
                            $modulos_nomes = array_filter(array_map(function($id) use ($modulos_map) {
                                return $modulos_map[$id] ?? null;
                            }, $modulos_ids));
                            $modulos_str = implode(", ", $modulos_nomes);
                            
                            $grupo_nome = $grupos_map[$cliente['id_grupo']] ?? 'Nenhum';
                            ?>
                            <tr>
                                <td><?= $cliente['id'] ?></td>
<td>
    <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" 
       style="color: #023324; text-decoration: none; display: flex; align-items: center; gap: 5px;">
        <i class="fas fa-external-link-alt" style="font-size: 12px; color: #8CC053;"></i>
        <?= htmlspecialchars($cliente['contrato'] . " - " . $cliente['nome']) ?>
    </a>
</td>
                                <td><?= htmlspecialchars($cliente['cnpj']) ?></td>
                                <td><?= htmlspecialchars($cliente['email']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                                <td><?= htmlspecialchars($plano_nome) ?></td>
                                <td><?= htmlspecialchars($modulos_str) ?></td>
                                <td><?= htmlspecialchars($grupo_nome) ?></td>
                                <td>
                                    <a href="editar_cliente.php?id=<?= $cliente['id'] ?>">Editar</a>
                                    <a href="excluir_cliente.php?id=<?= $cliente['id'] ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Controles de Paginação -->
                <?php if ($total_paginas > 1): ?>
                <div class="paginacao-container">
                    <div class="paginacao-info">
                        Página <?= $pagina ?> de <?= $total_paginas ?> (<?= $total_clientes ?> registros)
                    </div>
                    <div class="paginacao-controles">
                        <?php if ($pagina > 1): ?>
                            <a href="?pagina=1&ordenar_por=<?= urlencode($ordenar_por) ?>&direcao=<?= urlencode($direcao) ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>" class="btn-paginacao">« Primeira</a>
                            <a href="?pagina=<?= $pagina - 1 ?>&ordenar_por=<?= urlencode($ordenar_por) ?>&direcao=<?= urlencode($direcao) ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>" class="btn-paginacao">‹ Anterior</a>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar páginas próximas
                        $inicio = max(1, $pagina - 2);
                        $fim = min($total_paginas, $pagina + 2);
                        
                        for ($i = $inicio; $i <= $fim; $i++):
                        ?>
                            <a href="?pagina=<?= $i ?>&ordenar_por=<?= urlencode($ordenar_por) ?>&direcao=<?= urlencode($direcao) ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>" 
                               class="btn-paginacao <?= ($i == $pagina) ? 'ativo' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?pagina=<?= $pagina + 1 ?>&ordenar_por=<?= urlencode($ordenar_por) ?>&direcao=<?= urlencode($direcao) ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>" class="btn-paginacao">Próxima ›</a>
                            <a href="?pagina=<?= $total_paginas ?>&ordenar_por=<?= urlencode($ordenar_por) ?>&direcao=<?= urlencode($direcao) ?>&contrato=<?= urlencode($filtro_contrato) ?>&nome=<?= urlencode($filtro_nome) ?>&cnpj=<?= urlencode($filtro_cnpj) ?>&contato=<?= urlencode($filtro_contato) ?>&plano=<?= urlencode($filtro_plano) ?>&modulo=<?= urlencode($filtro_modulo) ?>&grupo=<?= urlencode($filtro_grupo) ?>" class="btn-paginacao">Última »</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Estilos para paginação */
        .paginacao-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .paginacao-info {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .paginacao-controles {
            display: flex;
            gap: 5px;
        }
        
        .btn-paginacao {
            padding: 8px 12px;
            background-color: white;
            border: 1px solid #ddd;
            color: #023324;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-paginacao:hover {
            background-color: #8CC053;
            color: white;
            border-color: #8CC053;
        }
        
        .btn-paginacao.ativo {
            background-color: #023324;
            color: white;
            border-color: #023324;
            font-weight: bold;
        }
        
        .btn-paginacao.ativo:hover {
             background-color: #034d2a;
             border-color: #034d2a;
         }
         
         /* Responsividade para dispositivos móveis */
         @media (max-width: 768px) {
             .paginacao-container {
                 flex-direction: column;
                 gap: 10px;
                 text-align: center;
             }
             
             .paginacao-controles {
                 flex-wrap: wrap;
                 justify-content: center;
             }
             
             .btn-paginacao {
                 padding: 6px 10px;
                 font-size: 12px;
             }
         }
     </style>
    <script>

        // Funções para os dropdowns do header
        document.addEventListener('DOMContentLoaded', function() {
            // Alternar visibilidade do dropdown de Suporte
            document.getElementById('supportButton')?.addEventListener('click', function(event) {
                event.stopPropagation();
                const suporteDropdown = document.getElementById('supportMenu');
                const conhecimentoDropdown = document.getElementById('knowledgeMenu');
                const treinamentoDropdown = document.getElementById('trainingMenu');
                const cadastroDropdown = document.getElementById('registerMenu');
                const configContainer = document.getElementById('configContainer');

                suporteDropdown.classList.toggle('visible');
                conhecimentoDropdown.classList.remove('visible');
                treinamentoDropdown.classList.remove('visible');
                cadastroDropdown.classList.remove('visible');
                configContainer.classList.remove('visible');
            });

            // Alternar visibilidade do dropdown de Conhecimento
            document.getElementById('knowledgeButton')?.addEventListener('click', function(event) {
                event.stopPropagation();
                const conhecimentoDropdown = document.getElementById('knowledgeMenu');
                const suporteDropdown = document.getElementById('supportMenu');
                const treinamentoDropdown = document.getElementById('trainingMenu');
                const cadastroDropdown = document.getElementById('registerMenu');
                const configContainer = document.getElementById('configContainer');

                conhecimentoDropdown.classList.toggle('visible');
                suporteDropdown.classList.remove('visible');
                treinamentoDropdown.classList.remove('visible');
                cadastroDropdown.classList.remove('visible');
                configContainer.classList.remove('visible');
            });

            // Alternar visibilidade do dropdown de Treinamento
            document.getElementById('trainingButton')?.addEventListener('click', function(event) {
                event.stopPropagation();
                const treinamentoDropdown = document.getElementById('trainingMenu');
                const suporteDropdown = document.getElementById('supportMenu');
                const conhecimentoDropdown = document.getElementById('knowledgeMenu');
                const cadastroDropdown = document.getElementById('registerMenu');
                const configContainer = document.getElementById('configContainer');

                treinamentoDropdown.classList.toggle('visible');
                suporteDropdown.classList.remove('visible');
                conhecimentoDropdown.classList.remove('visible');
                cadastroDropdown.classList.remove('visible');
                configContainer.classList.remove('visible');
            });

            // Alternar visibilidade do dropdown de Cadastros
            document.getElementById('registerButton')?.addEventListener('click', function(event) {
                event.stopPropagation();
                const cadastroDropdown = document.getElementById('registerMenu');
                const suporteDropdown = document.getElementById('supportMenu');
                const conhecimentoDropdown = document.getElementById('knowledgeMenu');
                const treinamentoDropdown = document.getElementById('trainingMenu');
                const configContainer = document.getElementById('configContainer');

                cadastroDropdown.classList.toggle('visible');
                suporteDropdown.classList.remove('visible');
                conhecimentoDropdown.classList.remove('visible');
                treinamentoDropdown.classList.remove('visible');
                configContainer.classList.remove('visible');
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
                const configButton = document.getElementById('configButton');
                const configContainer = document.getElementById('configContainer');

                if (!suporteDropdown.contains(event.target) && !suporteButton.contains(event.target)) {
                    suporteDropdown.classList.remove('visible');
                }
                
                if (!conhecimentoDropdown.contains(event.target) && !conhecimentoButton.contains(event.target)) {
                    conhecimentoDropdown.classList.remove('visible');
                }
                
                if (!treinamentoDropdown.contains(event.target) && !treinamentoButton.contains(event.target)) {
                    treinamentoDropdown.classList.remove('visible');
                }
                
                if (!cadastroDropdown.contains(event.target) && !cadastroButton.contains(event.target)) {
                    cadastroDropdown.classList.remove('visible');
                }
                
                if (!configContainer.contains(event.target) && !configButton.contains(event.target)) {
                    configContainer.classList.remove('visible');
                }
            });

            document.getElementById('logoutButton')?.addEventListener('click', function() {
                const confirmacao = confirm("Você realmente deseja sair?");
                if (confirmacao) {
                    window.location.href = '/HelpDesk_Nutify/login/logout.php';
                }
            });
        });
    </script>
</body>
</html>