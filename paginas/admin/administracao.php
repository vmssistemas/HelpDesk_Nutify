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

// Busca todos os menus ordenados pela ordem
$query_menus = "SELECT * FROM menus ORDER BY ordem";
$result_menus = $conn->query($query_menus);
$menus = $result_menus->fetch_all(MYSQLI_ASSOC);

// Busca todos os submenus ordenados por menu_id e ordem
$query_submenus = "SELECT submenus.id, submenus.nome, submenus.menu_id, menus.nome AS menu_nome 
                   FROM submenus 
                   JOIN menus ON submenus.menu_id = menus.id 
                   ORDER BY submenus.menu_id, submenus.ordem";
$result_submenus = $conn->query($query_submenus);
$submenus = $result_submenus->fetch_all(MYSQLI_ASSOC);

// Agrupa submenus por menu_id
$submenus_por_menu = [];
foreach ($submenus as $submenu) {
    $menu_id = $submenu['menu_id'];
    if (!isset($submenus_por_menu[$menu_id])) {
        $submenus_por_menu[$menu_id] = [];
    }
    $submenus_por_menu[$menu_id][] = $submenu;
}

// Busca todos os conteúdos (documentações)
$query_conteudos = "SELECT conteudos.id, conteudos.tipo, conteudos.conteudo, conteudos.submenu_id, submenus.nome AS submenu_nome 
                    FROM conteudos 
                    JOIN submenus ON conteudos.submenu_id = submenus.id";
$result_conteudos = $conn->query($query_conteudos);
$conteudos = $result_conteudos->fetch_all(MYSQLI_ASSOC);

// Agrupa conteúdos por submenu_id
$conteudos_por_submenu = [];
foreach ($conteudos as $conteudo) {
    $submenu_id = $conteudo['submenu_id'];
    if (!isset($conteudos_por_submenu[$submenu_id])) {
        $conteudos_por_submenu[$submenu_id] = [];
    }
    $conteudos_por_submenu[$submenu_id][] = $conteudo;
}

// Busca todos os links úteis (sem vínculo com menus)
$query_links_uteis = "SELECT id, nome, url FROM links_uteis";
$result_links_uteis = $conn->query($query_links_uteis);
$links_uteis = $result_links_uteis->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração</title>
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
        <h1>Administração documentações</h1>
        <nav>
            <a href="adicionar_menu.php">Adicionar Menu</a>
            <a href="adicionar_submenu.php">Adicionar Submenu</a>
            <a href="adicionar_conteudo.php">Adicionar Conteúdo</a>
            <a href="adicionar_link_util.php">Adicionar Link Útil</a>
            <a href="editar_ordenacao.php">Editar Ordenação</a>
            <a href="../principal.php">Voltar ao Site</a>
        </nav>
        <!-- Botões de Expandir/Recolher -->
        <div class="botoes-expansao">
            <button onclick="expandirTudo()">Expandir tudo</button>
            <button onclick="recolherTudo()">Recolher tudo</button>
        </div>
    </header>

    <main>
        <!-- Seção de Menus -->
        <section>
            <h2>Menus ▼</h2>
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
                        <?php foreach ($menus as $menu): ?>
                            <tr>
                                <td><?php echo $menu['id']; ?></td>
                                <td><?php echo htmlspecialchars($menu['nome']); ?></td>
                                <td>
                                    <a href="editar_menu.php?id=<?php echo $menu['id']; ?>">Editar</a>
                                    <a href="excluir_menu.php?id=<?php echo $menu['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Seção de Submenus Agrupados por Menu -->
        <section>
            <h2>Submenus ▼</h2>
            <div class="conteudo">
                <?php foreach ($menus as $menu): ?>
                    <?php if (isset($submenus_por_menu[$menu['id']])): ?>
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
                                    <?php foreach ($submenus_por_menu[$menu['id']] as $submenu): ?>
                                        <tr>
                                            <td><?php echo $submenu['id']; ?></td>
                                            <td><?php echo htmlspecialchars($submenu['nome']); ?></td>
                                            <td>
                                                <a href="editar_submenu.php?id=<?php echo $submenu['id']; ?>">Editar</a>
                                                <a href="excluir_submenu.php?id=<?php echo $submenu['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
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

        <!-- Seção de Conteúdos (Documentações) Agrupados por Submenu -->
        <section>
            <h2>Documentações ▼</h2>
            <div class="conteudo">
                <?php foreach ($submenus as $submenu): ?>
                    <?php if (isset($conteudos_por_submenu[$submenu['id']])): ?>
                        <h3>Submenu: <?php echo htmlspecialchars($submenu['nome']); ?> ▼</h3>
                        <div class="conteudo">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo</th>
                                        <th>Conteúdo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conteudos_por_submenu[$submenu['id']] as $conteudo): ?>
                                        <tr>
                                            <td><?php echo $conteudo['id']; ?></td>
                                            <td><?php echo htmlspecialchars($conteudo['tipo']); ?></td>
                                            <td><?php echo htmlspecialchars($conteudo['conteudo']); ?></td>
                                            <td>
                                                <a href="editar_conteudo.php?id=<?php echo $conteudo['id']; ?>">Editar</a>
                                                <a href="excluir_conteudo.php?id=<?php echo $conteudo['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
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

        <!-- Seção de Links Úteis -->
        <section>
            <h2>Links Úteis ▼</h2>
            <div class="conteudo">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>URL</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links_uteis as $link): ?>
                            <tr>
                                <td><?php echo $link['id']; ?></td>
                                <td><?php echo htmlspecialchars($link['nome']); ?></td>
                                <td><?php echo htmlspecialchars($link['url']); ?></td>
                                <td>
                                    <a href="editar_link_util.php?id=<?php echo $link['id']; ?>">Editar</a>
                                    <a href="excluir_link_util.php?id=<?php echo $link['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
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