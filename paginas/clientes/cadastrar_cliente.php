<?php
ob_start();
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Configuração para evitar erros no JSON
error_reporting(0);
ini_set('display_errors', 0);

// Busca dados do banco
$query_planos = "SELECT * FROM planos ORDER BY nome";
$planos = $conn->query($query_planos)->fetch_all(MYSQLI_ASSOC);

$query_modulos = "SELECT * FROM modulos ORDER BY nome";
$modulos = $conn->query($query_modulos)->fetch_all(MYSQLI_ASSOC);

$query_grupos = "SELECT * FROM grupos ORDER BY nome";
$grupos = $conn->query($query_grupos)->fetch_all(MYSQLI_ASSOC);

$query_contatos = "SELECT * FROM contatos ORDER BY nome";
$contatos = $conn->query($query_contatos)->fetch_all(MYSQLI_ASSOC);

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

$ufs = [
    'AC'=>'Acre', 'AL'=>'Alagoas', 'AP'=>'Amapá', 'AM'=>'Amazonas',
    'BA'=>'Bahia', 'CE'=>'Ceará', 'DF'=>'Distrito Federal',
    'ES'=>'Espírito Santo', 'GO'=>'Goiás', 'MA'=>'Maranhão',
    'MT'=>'Mato Grosso', 'MS'=>'Mato Grosso do Sul', 'MG'=>'Minas Gerais',
    'PA'=>'Pará', 'PB'=>'Paraíba', 'PR'=>'Paraná', 'PE'=>'Pernambuco',
    'PI'=>'Piauí', 'RJ'=>'Rio de Janeiro', 'RN'=>'Rio Grande do Norte',
    'RS'=>'Rio Grande do Sul', 'RO'=>'Rondônia', 'RR'=>'Roraima',
    'SC'=>'Santa Catarina', 'SP'=>'São Paulo', 'SE'=>'Sergipe',
    'TO'=>'Tocantins'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida campos obrigatórios
    $required = ['nome', 'contrato', 'telefone', 'uf', 'plano'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            ob_end_clean();
            echo json_encode(['status'=>'error', 'message'=>"O campo $field é obrigatório"]);
            exit();
        }
    }

$dados = [
    'nome' => $_POST['nome'],
    'email' => $_POST['email'] ?? '',
    'telefone' => $_POST['telefone'],
    'cnpj' => $_POST['cnpj'],
    'contrato' => $_POST['contrato'],
    'uf' => $_POST['uf'],
    'plano' => $_POST['plano'],
    'modulos' => implode(",", $_POST['modulos'] ?? []),
    'id_grupo' => !empty($_POST['grupo']) ? $_POST['grupo'] : null,
    'observacoes' => $_POST['observacoes'] ?? '',
    'observacoes_config' => $_POST['observacoes_config'] ?? '' // Nova linha
];

    // Verifica CNPJ duplicado (apenas se o CNPJ não estiver vazio)
    if (!empty($dados['cnpj'])) {
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE cnpj = ?");
        $stmt->bind_param("s", $dados['cnpj']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            ob_end_clean();
            echo json_encode(['status'=>'error', 'message'=>'CNPJ já cadastrado']);
            exit();
        }
    }

    // Verifica Contrato duplicado
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE contrato = ?");
    $stmt->bind_param("s", $dados['contrato']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'Número de contrato já cadastrado']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Insere cliente
$stmt = $conn->prepare("INSERT INTO clientes (nome,email,telefone,cnpj,contrato,uf,plano,modulos,id_grupo,observacoes,observacoes_config) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$stmt->bind_param("sssssssisss", $dados['nome'],$dados['email'],$dados['telefone'],$dados['cnpj'],$dados['contrato'],$dados['uf'],$dados['plano'],$dados['modulos'],$dados['id_grupo'],$dados['observacoes'],$dados['observacoes_config']);
        $stmt->execute();
        $cliente_id = $conn->insert_id;

        // Vincula contatos
        foreach ($_POST['contatos'] ?? [] as $contato_id => $tipo_relacao) {
            $stmt = $conn->prepare("INSERT INTO cliente_contato (cliente_id,contato_id,tipo_relacao) VALUES (?,?,?)");
            $stmt->bind_param("iis", $cliente_id, $contato_id, $tipo_relacao);
            $stmt->execute();
        }

        $conn->commit();
        ob_end_clean();
        echo json_encode(['status'=>'success', 'message'=>'Cliente cadastrado com sucesso!']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'Erro: '.$e->getMessage()]);
    }
    exit();
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Cliente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="../../assets/img/icone_verde.ico" type="image/png">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- IonIcons via jsDelivr -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
   <style>
    :root {
        --primary-color: #023324;
        --secondary-color: #8CC053;
        --light-gray: #f5f5f5;
        --medium-gray: #e0e0e0;
        --dark-gray: #555;
        --white: #fff;
        --shadow: 0 2px 10px rgba(0,0,0,0.1);
        --success-color: #28a745;
        --info-color: #17a2b8;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
    }
    
    /* Estilos para o formulário de cadastro */
    .cadastro-cliente {
        max-width: 1250px;
        margin: 20px auto;
        padding: 20px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .section-title {
        color: #023324;
        font-size: 18px;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #8CC053;
        grid-column: 1 / -1;
    }
    
    /* Layout do formulário */
    .form-container {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 20px;
        align-items: start;
    }
    
    .form-main-fields {
        display: grid;
        gap: 12px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .form-group {
        margin-bottom: 10px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #023324;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        box-sizing: border-box;
        height: 40px;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #023324;
        box-shadow: 0 0 0 3px rgba(2,51,36,0.1);
    }
    
    /* Módulos */
    .modulos-container {
        margin-top: 10px;
        background-color: #f8f8f8;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 12px;
    }
    
    .modulos-container fieldset {
        border: none;
        margin: 0;
        padding: 0;
    }
    
    .modulos-container legend {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
        color: #023324;
    }
    
    .checkbox-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    /* Abas */
    .form-secondary-fields {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .tabs-container {
        display: flex;
        flex-direction: column;
        height: 100%;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .tabs-header {
        display: flex;
        background-color: #f8f8f8;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .tab-button {
        flex: 1;
        padding: 12px;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 600;
        color: #555;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
    }
    
    .tab-button:hover {
        background-color: #eee;
        color: #023324;
    }
    
    .tab-button.active {
        color: #023324;
        border-bottom-color: #8CC053;
        background-color: white;
    }
    
    .tab-content {
        display: none;
        padding: 15px;
        flex-grow: 1;
        overflow-y: auto;
        background-color: white;
        min-height: 300px;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Contatos */
    .contatos-container {
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .contatos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .contatos-header h3 {
        margin: 0;
        font-size: 16px;
    }
    
    .contatos-header button {
        padding: 8px 15px;
        background-color: #8CC053;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: background-color 0.2s;
    }
    
    .contatos-header button:hover {
        background-color: #7FB449;
    }
    
    .contatos-list {
        flex-grow: 1;
        max-height: none;
    }
    
    .contato-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .contato-item:last-child {
        border-bottom: none;
    }
    
    .contato-info {
        font-size: 14px;
    }
    
    .contato-tipo {
        font-size: 13px;
    }
    
    /* Botão de cadastro */
    .button-container {
        grid-column: 1 / -1;
        text-align: center;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    
    .button-container button {
        background-color: #8CC053;
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 5px;
        font-size: 15px;
        cursor: pointer;
        transition: background-color 0.2s;
        height: 40px;
    }
    
    .button-container button:hover {
        background-color: #7FB449;
    }
    
    /* CKEditor */
    .ck-editor__editable {
        min-height: 150px !important;
        max-height: 150px !important;
        font-size: 14px;
        padding: 12px !important;
        border: 1px solid #ddd !important;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 20px;
        width: 90%;
        max-width: 700px;
        border-radius: 6px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        cursor: pointer;
    }
    
    .close:hover {
        color: #777;
    }
    
    /* Tabela no Modal */
    #lista-contatos {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    #lista-contatos th {
        text-align: left;
        padding: 12px;
        background-color: #f2f2f2;
        font-size: 14px;
    }
    
    #lista-contatos td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }
    
    #lista-contatos select {
        padding: 8px;
        font-size: 14px;
        height: 38px;
    }
    
    #lista-contatos button {
        padding: 8px 15px;
        font-size: 14px;
        height: auto;
        background-color: #8CC053;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    #lista-contatos button:hover {
        background-color: #7FB449;
    }
    
    /* Mensagens */
    #mensagem {
        display: none;
        padding: 15px;
        margin: 15px auto;
        border-radius: 5px;
        max-width: 700px;
        text-align: center;
        font-size: 15px;
    }
    
    /* Responsividade */
    @media (max-width: 768px) {
        .form-container {
            grid-template-columns: 1fr;
        }
        
        .checkbox-group {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }

    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f5f7fa;
        color: #333;
        margin-top: 0; /* Removido o espaço fixo do body */
        padding-top: 0; /* Removido o padding do body */
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
        margin-top: 40px; /* Ajuste para dar espaço após o header fixo */
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
                    <i class="fas fa-user-plus"></i>
                    <span>Cadastrar Cliente</span>
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
            <a class="nav-link active" href="cadastrar_cliente.php"><i class="fas fa-plus"></i> Novo Cliente</a>
            <a class="nav-link" href="contatos.php"><i class="fas fa-address-book"></i> Contatos</a>
            <a class="nav-link" href="grupos.php"><i class="fas fa-users"></i> Grupos</a>
            <a class="nav-link" href="importar_clientes.php"><i class="fas fa-file-import"></i> Importar</a>
        </div>
    </div>

    <div id="mensagem"></div>

    <div class="container mb-5" style="margin-top: 20px;">
        <main class="cadastro-cliente">
    <form id="form-cadastro" method="POST">
        <div class="form-container">
            <!-- Coluna Esquerda - Campos Cadastrais -->
            <div class="form-main-fields">
                <h2 class="section-title">Dados Cadastrais</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contrato">Nº Contrato</label>
                        <input type="text" id="contrato" name="contrato" maxlength="10" required
                               placeholder="000.000.00" title="Formato: 000.000.00">
                    </div>
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cnpj">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" maxlength="18">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" maxlength="15" required>
                    </div>
                    <div class="form-group">
                        <label for="uf">UF</label>
                        <select id="uf" name="uf" required>
                            <option value="">Selecione</option>
                            <?php foreach($ufs as $sigla=>$nome): ?>
                            <option value="<?=$sigla?>"><?="$sigla - $nome"?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="grupo">Grupo</label>
                        <select id="grupo" name="grupo">
                            <option value="">Selecione</option>
                            <?php foreach($grupos as $grupo): ?>
                            <option value="<?=$grupo['id']?>"><?=htmlspecialchars($grupo['nome'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="plano">Plano</label>
                        <select id="plano" name="plano" required>
                            <option value="">Selecione</option>
                            <?php foreach($planos as $plano): ?>
                            <option value="<?=$plano['id']?>"><?=htmlspecialchars($plano['nome'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modulos-container">
                    <fieldset>
                        <legend>Módulos</legend>
                        <div class="checkbox-group">
                            <?php foreach($modulos as $modulo): ?>
                            <label>
                                <input type="checkbox" name="modulos[]" value="<?=$modulo['id']?>">
                                <?=htmlspecialchars($modulo['nome'])?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
            </div>

            <!-- Coluna Direita - Abas de Observações -->
            <div class="form-secondary-fields">
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button type="button" class="tab-button active" data-tab="contatos">Contatos</button>
                        <button type="button" class="tab-button" data-tab="observacoes">Observações</button>
                        <button type="button" class="tab-button" data-tab="configuracoes">Configurações</button>
                    </div>
                    
                    <div class="tab-content active" id="contatos-tab">
                        <div class="contatos-container">
                            <div class="contatos-header">
                                <h3>Contatos Vinculados</h3>
                                <button type="button" onclick="abrirModalContatos()">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                            <div class="contatos-list" id="contatos-vinculados">
                                <p>Nenhum contato vinculado</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="observacoes-tab">
                        <div class="form-group">
                            <textarea id="observacoes" name="observacoes" style="display:none;"></textarea>
                            <div id="editor-observacoes"></div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="configuracoes-tab">
                        <div class="form-group">
                            <textarea id="observacoes_config" name="observacoes_config" style="display:none;"></textarea>
                            <div id="editor-observacoes-config"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="button-container">
            <button type="submit">Cadastrar Cliente</button>
        </div>
    </form>
</main>

    <!-- Modal Contatos -->
    <div id="modal-contatos" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalContatos()">&times;</span>
            <h2>Selecionar Contatos</h2>
            <div style="margin-bottom:10px">
                <input type="text" id="filtro-contato" placeholder="Filtrar contatos..." style="width:100%;padding:6px">
            </div>
            <div style="max-height:50vh;overflow-y:auto">
                <table id="lista-contatos" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Tipo</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($contatos as $contato): 
                            $tel = preg_replace('/[^0-9]/','',$contato['telefone']);
                            $tel_formatado = strlen($tel)===11 ? 
                                preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$tel) :
                                (strlen($tel)===10 ? preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$tel) : $contato['telefone']);
                        ?>
                        <tr data-id="<?=$contato['id']?>">
                            <td><?=htmlspecialchars($contato['nome'])?></td>
                            <td><?=$tel_formatado?></td>
                            <td>
                                <select name="tipo_relacao[<?=$contato['id']?>]" style="padding:4px">
                                    <option value="dono">Dono</option>
                                    <option value="gerente">Gerente</option>
                                    <option value="funcionario">Funcionário</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </td>
                            <td>
                                <button type="button" onclick="adicionarContato(<?=$contato['id']?>,'<?=htmlspecialchars(addslashes($contato['nome']))?>','<?=htmlspecialchars(addslashes($contato['telefone']))?>')">
                                    Adicionar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Variáveis globais
    const contatosSelecionados = {};
    
    // Formatação de campos
    function formatarCNPJ(cnpj) {
        return cnpj.replace(/\D/g,'')
            .replace(/^(\d{2})(\d)/,'$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3')
            .replace(/\.(\d{3})(\d)/,'.$1/$2')
            .replace(/(\d{4})(\d)/,'$1-$2');
    }

    function formatarContrato(contrato) {
        // Remove tudo que não é dígito
        contrato = contrato.replace(/\D/g,'');
        
        // Aplica a máscara 000.000.00
        if (contrato.length > 6) {
            contrato = contrato.substring(0, 6) + '.' + contrato.substring(6);
        }
        if (contrato.length > 3) {
            contrato = contrato.substring(0, 3) + '.' + contrato.substring(3);
        }
        
        return contrato;
    }

    function formatarTelefone(tel) {
        tel = tel.replace(/\D/g,'');
        return tel.length===11 ? tel.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3') :
               tel.length===10 ? tel.replace(/(\d{2})(\d{4})(\d{4})/,'($1) $2-$3') : tel;
    }

    // Validação UF
    document.getElementById('uf').addEventListener('change', function(e) {
        if(e.target.value.length!==2 && e.target.value!=='') {
            alert('UF deve ter 2 caracteres');
            e.target.value = '';
        }
    });

    // Validação Contrato
    function validarContrato(contrato) {
        // Verifica o formato 000.000.00
        const regex = /^\d{3}\.\d{3}\.\d{2}$/;
        return regex.test(contrato);
    }

    // Aplicar máscaras
    document.getElementById('cnpj').addEventListener('input', function(e) {
        e.target.value = formatarCNPJ(e.target.value);
    });

    document.getElementById('contrato').addEventListener('input', function(e) {
        e.target.value = formatarContrato(e.target.value);
        
        // Validação em tempo real
        if (!validarContrato(e.target.value) && e.target.value.length >= 10) {
            alert('O número do contrato deve estar no formato 000.000.00');
            e.target.value = '';
        }
    });

    document.getElementById('telefone').addEventListener('input', function(e) {
        e.target.value = formatarTelefone(e.target.value);
    });

    // Modal de contatos
    function abrirModalContatos() {
        document.getElementById('modal-contatos').style.display = 'block';
    }

    function fecharModalContatos() {
        document.getElementById('modal-contatos').style.display = 'none';
    }

    // Filtro de contatos
    document.getElementById('filtro-contato').addEventListener('input', function(e) {
        const filtro = e.target.value.toLowerCase();
        document.querySelectorAll('#lista-contatos tbody tr').forEach(tr => {
            const nome = tr.cells[0].textContent.toLowerCase();
            const tel = tr.cells[1].textContent.toLowerCase();
            tr.style.display = (nome.includes(filtro) || tel.includes(filtro)) ? '' : 'none';
        });
    });

    // Gerenciamento de contatos
    function adicionarContato(id, nome, telefone) {
        const tipo = document.querySelector(`select[name="tipo_relacao[${id}]"]`).value;
        contatosSelecionados[id] = {
            tipo: tipo,
            nome: nome,
            telefone: formatarTelefone(telefone.replace(/\D/g,''))
        };
        atualizarContatosVinculados();
        fecharModalContatos();
    }

    function removerContato(id) {
        delete contatosSelecionados[id];
        atualizarContatosVinculados();
    }

    function atualizarContatosVinculados() {
        const container = document.getElementById('contatos-vinculados');
        if(Object.keys(contatosSelecionados).length === 0) {
            container.innerHTML = '<p>Nenhum contato vinculado</p>';
            return;
        }
        
        let html = '';
        for(const [id, contato] of Object.entries(contatosSelecionados)) {
            html += `
            <div class="contato-item">
                <div class="contato-info">
                    <strong>${contato.nome}</strong> - ${contato.telefone}
                    <span class="contato-tipo">(${contato.tipo})</span>
                </div>
                <div>
                    <button type="button" onclick="removerContato(${id})" style="color:red">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="contatos[${id}]" value="${contato.tipo}">
                </div>
            </div>`;
        }
        container.innerHTML = html;
    }

    // CKEditor
    ClassicEditor.create(document.querySelector('#editor-observacoes'), {
        toolbar: ['bold','italic','link','bulletedList','numberedList','|','undo','redo'],
        wordWrap: true
    }).then(editor => {
        editor.model.document.on('change:data', () => {
            document.querySelector('#observacoes').value = editor.getData();
        });
    }).catch(console.error);

    // CKEditor para observações de configurações
ClassicEditor.create(document.querySelector('#editor-observacoes-config'), {
    toolbar: ['bold','italic','link','bulletedList','numberedList','|','undo','redo'],
    wordWrap: true
}).then(editor => {
    editor.model.document.on('change:data', () => {
        document.querySelector('#observacoes_config').value = editor.getData();
    });
}).catch(console.error);

    // Envio do formulário
    document.getElementById('form-cadastro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validação do contrato antes de enviar
        const contrato = document.getElementById('contrato').value;
        if (!validarContrato(contrato)) {
            alert('O número do contrato deve estar no formato 000.000.00');
            return;
        }

        const formData = new FormData(this);
        
        fetch('cadastrar_cliente.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const msg = document.getElementById('mensagem');
            msg.style.display = 'block';
            msg.textContent = data.message;
            msg.style.backgroundColor = data.status==='success' ? '#d4edda' : '#f8d7da';
            msg.style.color = data.status==='success' ? '#155724' : '#721c24';
            
            if(data.status==='success') {
                setTimeout(() => window.location.href='clientes.php', 1000);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao enviar o formulário');
        });
    });

    // Tecla ESC para voltar
    document.addEventListener('keydown', e => {
        if(e.keyCode===27) window.history.back();
    });
    // Controle das abas
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        // Remove classe active de todos os botões e conteúdos
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Adiciona classe active ao botão clicado
        this.classList.add('active');
        
        // Mostra o conteúdo correspondente
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
    });
});

// Ajusta a altura dos editores quando a aba é aberta
function ajustarAlturaEditores() {
    const activeTab = document.querySelector('.tab-content.active');
    if (activeTab) {
        const editors = activeTab.querySelectorAll('.ck-editor');
        editors.forEach(editor => {
            editor.style.height = '100%';
        });
    }
}

// Observa mudanças nas abas para ajustar os editores
const observer = new MutationObserver(ajustarAlturaEditores);
document.querySelectorAll('.tab-content').forEach(tab => {
    observer.observe(tab, { attributes: true, attributeFilter: ['class'] });
});
    </script>

    <!-- Scripts para funcionamento dos menus dropdown e logout -->
    <script>
        // Função para alternar dropdown
        function toggleDropdown(containerId, menuId) {
            const container = document.getElementById(containerId);
            const menu = document.getElementById(menuId);
            
            // Fechar outros dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                if (otherMenu.id !== menuId) {
                    otherMenu.style.display = 'none';
                    otherMenu.parentElement.classList.remove('active');
                }
            });
            
            // Alternar o dropdown atual
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
                container.classList.remove('active');
            } else {
                menu.style.display = 'block';
                container.classList.add('active');
            }
        }

        // Event listeners para os botões de dropdown
        document.getElementById('supportButton').addEventListener('click', function(e) {
            e.preventDefault();
            toggleDropdown('support-container', 'supportMenu');
        });

        document.getElementById('trainingButton').addEventListener('click', function(e) {
            e.preventDefault();
            toggleDropdown('training-container', 'trainingMenu');
        });

        document.getElementById('registerButton').addEventListener('click', function(e) {
            e.preventDefault();
            toggleDropdown('register-container', 'registerMenu');
        });

        document.getElementById('knowledgeButton').addEventListener('click', function(e) {
            e.preventDefault();
            toggleDropdown('knowledge-container', 'knowledgeMenu');
        });

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-container')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                    menu.parentElement.classList.remove('active');
                });
            }
        });

        // Logout
        document.getElementById('logoutButton').addEventListener('click', function() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = '/HelpDesk_Nutify/logout.php';
            }
        });
    </script>
</body>
</html>