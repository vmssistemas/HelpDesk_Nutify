<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Exibir mensagens flash
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// Filtros
$filtro_nome = isset($_GET['nome']) ? $_GET['nome'] : '';
$filtro_telefone = isset($_GET['telefone']) ? preg_replace('/[^0-9]/', '', $_GET['telefone']) : '';

// Consulta com filtros
$query = "SELECT * FROM contatos WHERE 1=1";
$params = [];

if (!empty($filtro_nome)) {
    $query .= " AND nome LIKE ?";
    $params[] = "%$filtro_nome%";
}

if (!empty($filtro_telefone)) {
    $query .= " AND telefone LIKE ?";
    $params[] = "%$filtro_telefone%";
}

$query .= " ORDER BY nome ASC";

$stmt = $conn->prepare($query);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$contatos = $result->fetch_all(MYSQLI_ASSOC);

$total_contatos = count($contatos);

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
    <title>Gerenciar Contatos</title>
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

    /* Estilos para modais */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 500px;
        border-radius: 8px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #8CC053;
    }

    .button-container {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .btn-cancelar {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-salvar {
        background-color: #023324;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-novo-contato {
        background-color: #023324;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .btn-novo-contato:hover {
        background-color: #01261b;
    }

    .mensagem-flash {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .mensagem-sucesso {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .mensagem-erro {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
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
                    <i class="fas fa-address-book"></i>
                    <span>Gestão de Contatos</span>
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
            <a class="nav-link active" href="contatos.php"><i class="fas fa-address-book"></i> Contatos</a>
            <a class="nav-link" href="grupos.php"><i class="fas fa-users"></i> Grupos</a>
            <a class="nav-link" href="importar_clientes.php"><i class="fas fa-file-import"></i> Importar</a>
        </div>
    </div>

    <div class="container mb-5">
        <main>
            <?php if (isset($mensagem)): ?>
                <div class="mensagem-flash mensagem-<?php echo $mensagem['tipo']; ?>">
                    <?php echo $mensagem['texto']; ?>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <section class="filtros">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                    <h2 style="margin: 0; font-size: 16px; color: #023324; font-weight: 600;">Filtros</h2>
                    <button type="button" class="btn-novo-contato" onclick="abrirModal('modal-cadastrar')">
                        <i class="fas fa-plus"></i> Novo Contato
                    </button>
                </div>
                <form method="GET" action="contatos.php" class="filtros-form">
                    <div class="filtro-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" placeholder="Digite o nome" value="<?php echo htmlspecialchars($filtro_nome); ?>">
                    </div>
                    <div class="filtro-group">
                        <label for="telefone-filtro">Telefone:</label>
                        <input type="text" id="telefone-filtro" name="telefone" placeholder="(99) 99999-9999" maxlength="15" value="<?php echo htmlspecialchars(formatarTelefoneExibicao($filtro_telefone)); ?>">
                    </div>
                    <div class="filtro-group" style="display: flex; gap: 10px; align-items: center;">
                        <button type="submit" style="padding: 6px 12px; font-size: 12px;">Filtrar</button>
                        <a href="contatos.php" style="padding: 6px 12px; font-size: 12px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; color: #023324; text-decoration: none;">Limpar</a>
                    </div>
                </form>
            </section>

            <!-- Contador de Contatos -->
            <div class="contador-atendimentos">
                <strong>Total de Contatos:</strong> <?php echo $total_contatos; ?>
            </div>

            <hr class="hr">

            <!-- Tabela de Contatos -->
            <section>
                <h2 class="section-title">Lista de Contatos</h2>
                <table class="table-contatos">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contatos as $contato): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($contato['nome']); ?></td>
                                <td><?php echo formatarTelefoneExibicao($contato['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($contato['email']); ?></td>
                                <td class="acoes-contato">
                                    <a href="#" class="btn-acao btn-editar" onclick="abrirModalEditar(<?php echo $contato['id']; ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="excluir_contato.php?id=<?php echo $contato['id']; ?>" class="btn-acao btn-excluir" onclick="return confirm('Tem certeza que deseja excluir este contato?')">
                                        <i class="fas fa-trash"></i> Excluir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <!-- Modal de Cadastro -->
    <div id="modal-cadastrar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cadastrar Novo Contato</h2>
                <span class="close" onclick="fecharModal('modal-cadastrar')">&times;</span>
            </div>
            <form id="form-cadastrar-contato" method="POST" action="cadastrar_contato.php">
                <div class="form-group">
                    <label for="nome-contato">Nome:</label>
                    <input type="text" id="nome-contato" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="telefone-contato">Telefone: <small>(11 dígitos com DDD)</small></label>
                    <input type="text" id="telefone-contato" name="telefone" placeholder="(99) 99999-9999" maxlength="15" required>
                    <small class="erro-telefone" style="color: red; display: none;">Telefone deve ter 11 dígitos</small>
                </div>
                <div class="form-group">
                    <label for="email-contato">Email:</label>
                    <input type="email" id="email-contato" name="email">
                </div>
                <div class="form-group">
                    <label for="observacoes-contato">Observações:</label>
                    <textarea id="observacoes-contato" name="observacoes" rows="4"></textarea>
                </div>
                <div class="button-container">
                    <button type="button" class="btn-cancelar" onclick="fecharModal('modal-cadastrar')">Cancelar</button>
                    <button type="submit" class="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="modal-editar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Contato</h2>
                <span class="close" onclick="fecharModal('modal-editar')">&times;</span>
            </div>
            <form id="form-editar-contato" method="POST" action="editar_contato.php">
                <input type="hidden" id="id-contato-editar" name="id">
                <div class="form-group">
                    <label for="nome-contato-editar">Nome:</label>
                    <input type="text" id="nome-contato-editar" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="telefone-contato-editar">Telefone: <small>(11 dígitos com DDD)</small></label>
                    <input type="text" id="telefone-contato-editar" name="telefone" placeholder="(99) 99999-9999" maxlength="15" required>
                    <small class="erro-telefone-editar" style="color: red; display: none;">Telefone deve ter 11 dígitos</small>
                </div>
                <div class="form-group">
                    <label for="email-contato-editar">Email:</label>
                    <input type="email" id="email-contato-editar" name="email">
                </div>
                <div class="form-group">
                    <label for="observacoes-contato-editar">Observações:</label>
                    <textarea id="observacoes-contato-editar" name="observacoes" rows="4"></textarea>
                </div>
                <div class="button-container">
                    <button type="button" class="btn-cancelar" onclick="fecharModal('modal-editar')">Cancelar</button>
                    <button type="submit" class="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funções para abrir e fechar modais
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Função para abrir modal de edição com dados do contato
        function abrirModalEditar(id) {
            fetch(`obter_contato.php?id=${id}`)
                .then(response => response.json())
                .then(contato => {
                    document.getElementById('id-contato-editar').value = contato.id;
                    document.getElementById('nome-contato-editar').value = contato.nome;
                    document.getElementById('telefone-contato-editar').value = formatarTelefone(contato.telefone);
                    document.getElementById('email-contato-editar').value = contato.email;
                    document.getElementById('observacoes-contato-editar').value = contato.observacoes;
                    
                    abrirModal('modal-editar');
                })
                .catch(error => {
                    console.error('Erro ao obter dados do contato:', error);
                    alert('Erro ao carregar dados do contato');
                });
        }

        // Função para formatar telefone enquanto digita
        function formatarTelefone(input) {
            // Remove tudo que não é dígito
            let value = input.replace(/\D/g, '');
            
            // Aplica a máscara (99) 99999-9999
            if (value.length > 2) {
                value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
            }
            if (value.length > 10) {
                value = `${value.substring(0, 10)}-${value.substring(10)}`;
            }
            
            // Limita a 15 caracteres (formato completo)
            return value.substring(0, 15);
        }

        // Função para validar o telefone (11 dígitos)
        function validarTelefone(telefone) {
            const numeros = telefone.replace(/\D/g, '');
            return numeros.length === 11;
        }

        // Manipuladores de eventos para os campos de telefone
        function configurarValidacaoTelefone(campoId, erroId) {
            const campo = document.getElementById(campoId);
            const erro = document.querySelector(erroId);
            
            campo.addEventListener('input', function(e) {
                // Formata enquanto digita
                e.target.value = formatarTelefone(e.target.value);
                
                // Valida em tempo real
                if (validarTelefone(e.target.value)) {
                    erro.style.display = 'none';
                } else {
                    erro.style.display = 'block';
                }
            });
            
            // Impede a entrada de caracteres não numéricos
            campo.addEventListener('keydown', function(e) {
                // Permite: backspace, delete, tab, escape, enter, setas
                if ([46, 8, 9, 27, 13, 37, 38, 39, 40].includes(e.keyCode) ||
                    // Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                    (e.keyCode === 65 && e.ctrlKey === true) ||
                    (e.keyCode === 67 && e.ctrlKey === true) ||
                    (e.keyCode === 86 && e.ctrlKey === true) ||
                    (e.keyCode === 88 && e.ctrlKey === true) ||
                    // Números do teclado principal ou numérico
                    (e.keyCode >= 48 && e.keyCode <= 57) ||
                    (e.keyCode >= 96 && e.keyCode <= 105)) {
                    return;
                }
                e.preventDefault();
            });
        }

        // Configura a validação para todos os campos de telefone
        document.addEventListener('DOMContentLoaded', function() {
            configurarValidacaoTelefone('telefone-contato', '.erro-telefone');
            configurarValidacaoTelefone('telefone-contato-editar', '.erro-telefone-editar');
            configurarValidacaoTelefone('telefone-filtro', '.erro-telefone-filtro');
            
            // Validação ao enviar formulários
            document.getElementById('form-cadastrar-contato').addEventListener('submit', function(e) {
                const telefone = document.getElementById('telefone-contato').value;
                if (!validarTelefone(telefone)) {
                    e.preventDefault();
                    alert('Por favor, insira um telefone válido com 11 dígitos');
                }
            });
            
            document.getElementById('form-editar-contato').addEventListener('submit', function(e) {
                const telefone = document.getElementById('telefone-contato-editar').value;
                if (!validarTelefone(telefone)) {
                    e.preventDefault();
                    alert('Por favor, insira um telefone válido com 11 dígitos');
                }
            });
        });

        // Envio do formulário de cadastro via AJAX
        document.getElementById('form-cadastrar-contato').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const telefone = document.getElementById('telefone-contato').value;
            if (!validarTelefone(telefone)) {
                alert('Por favor, insira um telefone válido com 11 dígitos');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('cadastrar_contato.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Contato cadastrado com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao cadastrar contato');
            });
        });

        // Envio do formulário de edição via AJAX
        document.getElementById('form-editar-contato').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const telefone = document.getElementById('telefone-contato-editar').value;
            if (!validarTelefone(telefone)) {
                alert('Por favor, insira um telefone válido com 11 dígitos');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('editar_contato.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Contato atualizado com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar contato');
            });
        });

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        });
    // JavaScript para dropdowns do header
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
        if (confirm('Tem certeza que deseja sair?')) {
            window.location.href = '/HelpDesk_Nutify/paginas/logout.php';
        }
    });
});
</script>
</body>
</html>

<?php
// Função para formatar telefone para exibição
function formatarTelefoneExibicao($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    if (strlen($telefone) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    
    return $telefone; // Retorna sem formatação se não for 10 ou 11 dígitos
}
?>