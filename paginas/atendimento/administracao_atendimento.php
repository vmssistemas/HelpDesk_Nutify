<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

// Verifica se o usuário é admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Busca todos os menus de atendimento (ordenados por ordem)
$query_menu_atendimento = "SELECT * FROM menu_atendimento ORDER BY id";
$result_menu_atendimento = $conn->query($query_menu_atendimento);
$menu_atendimento = $result_menu_atendimento->fetch_all(MYSQLI_ASSOC);

// Busca todos os submenus de atendimento (ordenados por menu_id e ordem)
$query_submenu_atendimento = "SELECT submenu_atendimento.id, submenu_atendimento.nome, submenu_atendimento.menu_id, menu_atendimento.nome AS menu_nome 
                              FROM submenu_atendimento 
                              JOIN menu_atendimento ON submenu_atendimento.menu_id = menu_atendimento.id 
                              ORDER BY submenu_atendimento.menu_id";
$result_submenu_atendimento = $conn->query($query_submenu_atendimento);
$submenu_atendimento = $result_submenu_atendimento->fetch_all(MYSQLI_ASSOC);

// Agrupa submenus de atendimento por menu_id
$submenu_atendimento_por_menu = [];
foreach ($submenu_atendimento as $submenu) {
    $menu_id = $submenu['menu_id'];
    if (!isset($submenu_atendimento_por_menu[$menu_id])) {
        $submenu_atendimento_por_menu[$menu_id] = [];
    }
    $submenu_atendimento_por_menu[$menu_id][] = $submenu;
}

// Busca todos os tipos de erro (ordenados por descricao)
$query_tipo_erro = "SELECT * FROM tipo_erro ORDER BY id"; // Ordena por descricao
$result_tipo_erro = $conn->query($query_tipo_erro);
$tipo_erro = $result_tipo_erro->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Atendimento</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .conteudo {
            margin-top: 10px;
            display: none; /* Inicia oculto */
        }

        h2, h3 {
            font-size: 20px;
            color: #023324;
            cursor: pointer; /* Indica que é clicável */
        }

        button {
            margin: 5px;
            padding: 5px 10px; /* Botões menores */
            background-color: #8CC053;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px; /* Tamanho da fonte reduzido */
        }

        button:hover {
            background-color: #8CC053; /* Cor mais escura no hover */
        }

        .botoes-expansao {
    text-align: center; /* Centraliza os botões */
    margin: 20px 0 2px 0; /* Reduz o espaçamento acima e abaixo */
}
    </style>
</head>
<body>
    <header>
        <h1>Administração de Atendimento</h1>
        <nav>
            <a href="cadastrar_menu_atendimento.php">Cadastrar Menu de Atendimento</a>
            <a href="cadastrar_submenu_atendimento.php">Cadastrar Submenu de Atendimento</a>
            <a href="cadastrar_tipo_erro.php">Cadastrar Tipo</a>
            <a href="../principal.php">Voltar ao Site</a>
        </nav>
        <!-- Botões de Expandir/Recolher -->
        <div class="botoes-expansao">
            <button onclick="expandirTudo()">Expandir tudo</button>
            <button onclick="recolherTudo()">Recolher tudo</button>
        </div>
    </header>

    <main>
        <!-- Seção de Menus de Atendimento -->
        <section>
            <h2>Menus de Atendimento ▼</h2>
            <div class="conteudo">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_atendimento as $menu): ?>
                            <tr>
                                <td><?php echo $menu['id']; ?></td>
                                <td><?php echo htmlspecialchars($menu['nome']); ?></td>
                                <td>
                                    <a href="editar_menu_atendimento.php?id=<?php echo $menu['id']; ?>">Editar</a>
                                    <a href="excluir_menu_atendimento.php?id=<?php echo $menu['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Seção de Submenus de Atendimento Agrupados por Menu -->
        <section>
            <h2>Submenus de Atendimento ▼</h2>
            <div class="conteudo">
                <?php foreach ($menu_atendimento as $menu): ?>
                    <?php if (isset($submenu_atendimento_por_menu[$menu['id']])): ?>
                        <h3>Menu: <?php echo htmlspecialchars($menu['nome']); ?> ▼</h3>
                        <div class="conteudo">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submenu_atendimento_por_menu[$menu['id']] as $submenu): ?>
                                        <tr>
                                            <td><?php echo $submenu['id']; ?></td>
                                            <td><?php echo htmlspecialchars($submenu['nome']); ?></td>
                                            <td>
                                                <a href="editar_submenu_atendimento.php?id=<?php echo $submenu['id']; ?>">Editar</a>
                                                <a href="excluir_submenu_atendimento.php?id=<?php echo $submenu['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Seção de Tipos de Erro -->
        <section>
            <h2>Tipos de Erro ▼</h2>
            <div class="conteudo">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipo_erro as $erro): ?>
                            <tr>
                                <td><?php echo $erro['id']; ?></td>
                                <td><?php echo htmlspecialchars($erro['descricao']); ?></td>
                                <td>
                                    <a href="editar_tipo_erro.php?id=<?php echo $erro['id']; ?>">Editar</a>
                                    <a href="excluir_tipo_erro.php?id=<?php echo $erro['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Função para alternar a visibilidade de uma seção
        function toggleSection(element) {
            const conteudo = element.nextElementSibling;
            if (conteudo.style.display === "none" || conteudo.style.display === "") {
                conteudo.style.display = "block";
                element.innerHTML = element.innerHTML.replace("▼", "▲");
            } else {
                conteudo.style.display = "none";
                element.innerHTML = element.innerHTML.replace("▲", "▼");
            }
        }

        // Função para expandir todas as seções
        function expandirTudo() {
            document.querySelectorAll('.conteudo').forEach(conteudo => {
                conteudo.style.display = "block";
            });
            document.querySelectorAll('h2, h3').forEach(titulo => {
                titulo.innerHTML = titulo.innerHTML.replace("▼", "▲");
            });
        }

        // Função para recolher todas as seções
        function recolherTudo() {
            document.querySelectorAll('.conteudo').forEach(conteudo => {
                conteudo.style.display = "none";
            });
            document.querySelectorAll('h2, h3').forEach(titulo => {
                titulo.innerHTML = titulo.innerHTML.replace("▲", "▼");
            });
        }

        // Adiciona evento de clique aos títulos das seções
        document.querySelectorAll('h2, h3').forEach(titulo => {
            titulo.style.cursor = "pointer";
            titulo.addEventListener('click', () => toggleSection(titulo));
        });
    </script>
</body>
</html>