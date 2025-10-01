<?php
session_start();

// Gera um token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../login/login.php");
    exit();
}

// Conexão com o banco de dados
require_once '../config/db.php';

// Buscar dados do usuário logado
$email = $_SESSION['email']; // A variável de sessão que armazena o e-mail do usuário
$query = "SELECT id, admin FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email); // "s" para string
$stmt->execute();
$stmt->bind_result($usuario_id, $is_admin);
$stmt->fetch();
$stmt->close();

// Armazenar os dados do usuário na sessão
$_SESSION['is_admin'] = $is_admin;
$_SESSION['usuario_id'] = $usuario_id;

// Função para carregar menus e submenus
function carregarMenus($conn) {
    $menus = [];
    $query = "SELECT * FROM menus ORDER BY ordem";
    $result = $conn->query($query);

    while ($menu = $result->fetch_assoc()) {
        $menu_id = $menu['id'];
        $query_submenus = "SELECT * FROM submenus WHERE menu_id = $menu_id ORDER BY ordem";
        $result_submenus = $conn->query($query_submenus);
        $submenus = [];

        while ($submenu = $result_submenus->fetch_assoc()) {
            $submenus[] = $submenu;
        }

        $menu['submenus'] = $submenus;
        $menus[] = $menu;
    }

    return $menus;
}

// Função para carregar links úteis
function carregarLinksUteis($conn) {
    $links_uteis = [];
    $query = "SELECT * FROM links_uteis"; // Não há mais vínculo com menus
    $result = $conn->query($query);

    while ($link = $result->fetch_assoc()) {
        $links_uteis[] = $link;
    }

    return $links_uteis;
}

$menus = carregarMenus($conn);
$links_uteis = carregarLinksUteis($conn);

// Função para carregar usuários e suas cores
function carregarUsuariosCores($conn) {
    $usuarios_cores = [];
    $query = "SELECT nome, cor FROM usuarios WHERE cor IS NOT NULL AND cor != '' ORDER BY nome";
    $result = $conn->query($query);

    while ($usuario = $result->fetch_assoc()) {
        $usuarios_cores[] = $usuario;
    }

    return $usuarios_cores;
}

$usuarios_cores = carregarUsuariosCores($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Documentação</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../assets/css/principal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- IonIcons via jsDelivr -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://cdn.jsdelivr.net/npm/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>


    <!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.js'></script>
</head>
<body>
<header id="main-header">
    <!-- Botão para esconder/mostrar o menu e o cabeçalho fixo -->
    <button id="toggle-menu-button" onclick="toggleMenuVisibility()">
        <ion-icon name="menu-outline"></ion-icon>
    </button>
    <!-- Container para Suporte -->
    <div id="support-container">
        <button id="supportButton">
            Suporte <ion-icon name="chevron-down-outline"></ion-icon>
        </button>
        <div id="supportMenu" class="support-menu">
            <a href="atendimento/atendimento.php">
                <i class="fa-solid fa-headset"></i> Atendimentos
            </a>
            <a href="../chamados/index.php" class="btn btn-primary">
            <i class="fas fa-clipboard-list"></i> Chamados
</a>
            <a href="../instalacoes/index.php" class="btn btn-primary">
            <i class="fas fa-tools"></i> Instalações
</a>
        </div>
    </div>
    <!-- Container para Treinamento -->
<div id="training-container">
    <button id="trainingButton">
        Treinamento <ion-icon name="chevron-down-outline"></ion-icon>
    </button>
    <div id="trainingMenu" class="training-menu">
        <a href="../treinamentos/index.php">
            <i class="fas fa-graduation-cap"></i> Treinamentos
        </a>
    </div>
</div>

<!-- Container para Cadastros -->
<div id="register-container">
    <button id="registerButton">
        Cadastros <ion-icon name="chevron-down-outline"></ion-icon>
    </button>
    <div id="registerMenu" class="register-menu">
        <a href="clientes/clientes.php">
            <i class="fa-solid fa-user-plus"></i> Clientes
        </a>
    </div>
</div>
    <div id="knowledge-container">
        <button id="knowledgeButton">
            Principal <ion-icon name="chevron-down-outline"></ion-icon>
        </button>
        <div id="knowledgeMenu" class="knowledge-menu">
            <a href="principal.php">
                <i class="fas fa-book"></i> Documentações
            </a>
        </div>
    </div>

    
<button id="agendaButton" class="agenda-btn">
    <i class="fas fa-calendar-alt"></i> 
    <span class="btn-text">Agenda</span>
</button>

    <!-- Botão de alternância para tema claro/escuro -->
    <div id="perfil-container">
        <span><strong>Usuário:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'Usuário', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <button id="configButton">
        <i class="fas fa-cog"></i> Configurações
    </button>
    <div id="configContainer" class="config-container">
        <!-- Botão de alternância de tema movido para dentro do container de configurações -->
        <div id="theme-toggle">
            <label class="switch">
                <input type="checkbox" id="theme-switch">
                <span class="slider round"></span>
            </label>
            <span id="theme-label">Tema Escuro</span>
        </div>
        <button id="alterarSenhaButton" onclick="toggleSenhaForm()">
            <i class="fas fa-user"></i> Conta
        </button>
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
            <button id="adminButton" onclick="window.location.href='./admin/administracao.php'">
            <i class="fas fa-book"></i> Documentações
            </button>
            <!-- Novo botão para Administração de Atendimento -->
            <button id="adminAtendimentoButton" onclick="window.location.href='./atendimento/administracao_atendimento.php'">
                <i class="fas fa-headset"></i> Atendimento
            </button>  
            <button id="adminClienteButton" onclick="window.location.href='./clientes/administracao_cliente.php'">
            <i class="fa-solid fa-user-plus"></i> Clientes
            </button> 
        <?php endif; ?>
    </div>
    <button id="logoutButton">
    <i class="fas fa-sign-out-alt"></i> Sair
</button>
</header>

<!-- Adicione este div antes do formulário de alteração de senha -->
<div id="overlay" class="overlay"></div>
<!-- Formulário de alteração de senha (inicialmente oculto) -->
<div id="alterarSenhaForm" class="senha-form-container">
    <ion-icon name="close" id="closeSenhaForm" onclick="toggleSenhaForm()"></ion-icon>
    <h2>Alterar Senha</h2>
    <?php if (isset($_SESSION['senha_erro'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['senha_erro'], ENT_QUOTES, 'UTF-8'); ?>
            <?php unset($_SESSION['senha_erro']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['senha_sucesso'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['senha_sucesso'], ENT_QUOTES, 'UTF-8'); ?>
            <?php unset($_SESSION['senha_sucesso']); ?>
        </div>
    <?php endif; ?>
    <form id="formAlterarSenha" action="../usuarios/alterar_senha.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" autocomplete="username" />
        <div class="input-group">
            <label for="senha_atual">Senha Atual</label>
            <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="current-password" required />
        </div>
        <div class="input-group">
            <label for="nova_senha">Nova Senha</label>
            <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite sua nova senha" autocomplete="new-password" required />
        </div>
        <div class="input-group">
            <label for="confirmar_senha">Confirmar Nova Senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme sua nova senha" autocomplete="new-password" required />
        </div>
        <button type="submit">Salvar Nova Senha</button>
    </form>
</div>

<!-- Container principal para menu e conteúdo -->
<div id="main-container">
    <!-- Cabeçalho fixo com logo e pesquisa -->
    <div id="fixed-header">
        <img src="../assets/img/logo.png" alt="Nutify Sistemas" onclick="window.location.href = 'principal.php';" style="cursor: pointer;">
        <div id="search-container">
            <input type="text" id="searchInput" onkeyup="filterMenu()" placeholder="Pesquisar...">
            <ion-icon name="close" id="clearSearch" onclick="clearSearch()"></ion-icon>
        </div>
    </div>

    <!-- Menu lateral -->
    <div id="menu">
        <?php foreach ($menus as $menu): ?>
            <?php if ($menu['mostrar_linha']): ?>
                <hr class="hr">
            <?php endif; ?>
            <div class="menu-item" onclick="toggleMenu('submenu-<?php echo $menu['id']; ?>')">
                <?php if (!empty($menu['icone'])): ?>
                    <?php echo $menu['icone']; ?>
                <?php else: ?>
                    <ion-icon name="folder-outline"></ion-icon>
                <?php endif; ?>
                <?php echo htmlspecialchars($menu['nome']); ?>
            </div>
            <div class="submenu" id="submenu-<?php echo $menu['id']; ?>">
                <?php foreach ($menu['submenus'] as $submenu): ?>
                    <a href="#" onclick="loadContent(<?php echo $submenu['id']; ?>)">
                        • <?php echo htmlspecialchars($submenu['nome']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Links Úteis -->
        <div id="links-uteis-container">
            <div class="menu-item" onclick="toggleMenu('submenu-links')">
                <ion-icon name="link-outline"></ion-icon> Links Úteis
            </div>
            <div class="submenu" id="submenu-links">
                <?php foreach ($links_uteis as $link): ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank">
                        <?php echo htmlspecialchars($link['nome']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<!-- Conteúdo principal -->
<div id="content">
 

    <!-- Container para o conteúdo carregado dinamicamente -->
    <div id="content-container"></div>
</div>
 <!-- Modal -->
 <div id="atendimentoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Registrar Atendimento Rápido</h2>
                <span class="close">&times;</span>
            </div>
            <iframe id="modalIframe" src="" style="width:100%; height:500px; border:none;"></iframe>
        </div>
    </div>
<div id="agendaModal" class="modal">
    <div class="modal-content" style="width: 98%; max-width: 1600px;">
        <div class="modal-body">
            <div class="agenda-tabs">
                <button class="tab-button active" onclick="openTab(event, 'calendarTab')">Calendário</button>
                 <button class="tab-button" onclick="openTab(event, 'changesHistoryTab')">Histórico de Alterações (Individual)</button>
                <button class="tab-button" onclick="openTab(event, 'deletedEventsTab')">Histórico de Excluídos (Geral)</button>
                <div class="agenda-search-container">
                    <input type="text" id="agendaClienteFilter" placeholder="Buscar por cliente..." class="agenda-search-input">
                    <button type="button" id="clearAgendaFilter" class="clear-filter-btn" onclick="clearAgendaClienteFilter()" title="Limpar filtro">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="close">&times;</span>
            </div>
            
            <div id="calendarTab" class="tab-content active">
                <div class="agenda-container">
                    <div class="agenda-sidebar">
                        <h3>Usuários</h3>
                        <div class="user-controls">
                            <button type="button" class="btn-select-all" onclick="selectAllUsers()">Marcar Todos</button>
                            <button type="button" class="btn-deselect-all" onclick="deselectAllUsers()">Desmarcar Todos</button>
                        </div>
                        <div class="agenda-users">
                            <?php if (!empty($usuarios_cores)): ?>
                                <?php foreach ($usuarios_cores as $usuario): ?>
                                    <div class="user-filter-item">
                                        <input type="checkbox" id="user-<?php echo htmlspecialchars($usuario['nome']); ?>" 
                                               class="user-filter-checkbox" 
                                               data-user="<?php echo htmlspecialchars($usuario['nome']); ?>" 
                                               data-color="<?php echo htmlspecialchars($usuario['cor']); ?>" 
                                               checked>
                                        <label for="user-<?php echo htmlspecialchars($usuario['nome']); ?>">
                                            <span class="user-name"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="agenda-main">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
            
            <div id="deletedEventsTab" class="tab-content">
                <div id="deletedEventsList">
                    <p>Carregando eventos excluídos...</p>
                </div>
            </div>
            
            <div id="changesHistoryTab" class="tab-content">
                <div id="changesHistoryList">
                    <p>Carregando histórico de alterações...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Substitua o modal de evento por este: -->
<div id="eventoModal" class="modal">
    <div class="modal-content" style="width: 90%; max-width: 1000px;">
        <div class="modal-header">
            <h2 id="modalEventTitle">Adicionar Evento</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="eventoForm">
                <input type="hidden" id="eventoId">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" data-user-id="<?php echo $usuario_id; ?>">
                
                <div class="form-group">
                    <label for="eventoTitulo">Título *</label>
                    <input type="text" id="eventoTitulo" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="eventoDescricao">Descrição</label>
                    <textarea id="eventoDescricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventoInicio">Início *</label>
                        <input type="datetime-local" id="eventoInicio" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="eventoFim">Fim *</label>
                        <input type="datetime-local" id="eventoFim" class="form-control" required>
                    </div>
                </div>
                

                       <div class="form-group">
                        <label for="eventoTipo">Tipo *</label>
                        <select id="eventoTipo" class="form-control" required>
                            <option value="">Selecione...</option>
                            <option value="reuniao">Instalação</option>
                            <option value="tarefa">Treinamento</option>
                            <option value="lembrete">Conversão</option>
                            <option value="lembrete">Configurações</option>
                            <option value="lembrete">Cancelamento</option>
                            <option value="feriado">Outros</option>
                        </select>
                    </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventoCor">Cor</label>
                        <input type="color" id="eventoCor" class="form-control" value="#3788d8">
                    </div>
                    
             
                </div>
                
                <div class="form-group">
                    <label for="eventoCliente" id="clienteLabel" style="cursor: pointer; color: #007bff; text-decoration: underline;" onclick="handleClienteClick()">Cliente *</label>
                    <div class="custom-select">
                        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." class="form-control">
                        <div class="options" id="cliente_options">
                            <div data-value="">Nenhum</div>
                            <?php
                            $query = "SELECT id, CONCAT(COALESCE(contrato, ''), CASE WHEN contrato IS NOT NULL AND contrato != '' THEN ' - ' ELSE '' END, nome) AS nome_completo FROM clientes ORDER BY nome";
                            $result = $conn->query($query);
                            while ($cliente = $result->fetch_assoc()) {
                                echo '<div data-value="'.$cliente['id'].'">'.$cliente['nome_completo'].'</div>';
                            }
                            ?>
                        </div>
                        <select id="eventoCliente" name="eventoCliente" class="form-select d-none">
                            <option value="" selected>Nenhum</option>
                            <?php
                            $query = "SELECT id, CONCAT(COALESCE(contrato, ''), CASE WHEN contrato IS NOT NULL AND contrato != '' THEN ' - ' ELSE '' END, nome) AS nome_completo FROM clientes ORDER BY nome";
                            $result = $conn->query($query);
                            while ($cliente = $result->fetch_assoc()) {
                                echo '<option value="'.$cliente['id'].'">'.$cliente['nome_completo'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="eventoUsuario">Atribuir a</label>
                    <select id="eventoUsuario" class="form-control" required>
                        <option value="">Selecione o usuário...</option>
                        <?php
                        $query = "SELECT id, nome FROM usuarios ORDER BY nome";
                        $result = $conn->query($query);
                        while ($user = $result->fetch_assoc()) {
                            echo '<option value="'.$user['id'].'">'.$user['nome'].'</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Campo de visibilidade removido -->
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primaryy">Salvar</button>
                    <button type="button" id="deleteEventBtn" class="btn btn-danger" style="display: none;">Excluir</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancelar</button>
                </div>
            </form>
            
            <div id="eventoHistorico">
                <h4>Histórico de Alterações</h4>
                <div id="historicoContent"></div>
            </div>
        </div>
    </div>
</div>
<!-- Scripts -->
<script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>

<!-- Script para inicializar o sistema de atualizações em tempo real -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sistema de notificações removido para melhorar performance
        // As atualizações do calendário ainda funcionam sem as notificações
    });
</script>
</body>
</html>