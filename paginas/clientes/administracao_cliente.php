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

// Busca todos os módulos
$query_modulos = "SELECT * FROM modulos ORDER BY id";
$result_modulos = $conn->query($query_modulos);
$modulos = $result_modulos->fetch_all(MYSQLI_ASSOC);

// Busca todos os planos
$query_planos = "SELECT * FROM planos ORDER BY id";
$result_planos = $conn->query($query_planos);
$planos = $result_planos->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Módulos e Planos</title>
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
        <h1>Administração de Módulos e Planos</h1>
        <nav>
            <a href="cadastrar_modulo.php">Cadastrar Módulo</a>
            <a href="cadastrar_plano.php">Cadastrar Plano</a>
            <a href="../principal.php">Voltar ao Site</a>
        </nav>
        <!-- Botões de Expandir/Recolher -->
        <div class="botoes-expansao">
            <button onclick="expandirTudo()">Expandir tudo</button>
            <button onclick="recolherTudo()">Recolher tudo</button>
        </div>
    </header>

    <main>
        <!-- Seção de Módulos -->
        <section>
            <h2>Módulos ▼</h2>
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
                        <?php foreach ($modulos as $modulo): ?>
                            <tr>
                                <td><?php echo $modulo['id']; ?></td>
                                <td><?php echo htmlspecialchars($modulo['nome']); ?></td>
                                <td>
                                    <a href="editar_modulo.php?id=<?php echo $modulo['id']; ?>">Editar</a>
                                    <a href="excluir_modulo.php?id=<?php echo $modulo['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Seção de Planos -->
        <section>
            <h2>Planos ▼</h2>
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
                        <?php foreach ($planos as $plano): ?>
                            <tr>
                                <td><?php echo $plano['id']; ?></td>
                                <td><?php echo htmlspecialchars($plano['nome']); ?></td>
                                <td>
                                    <a href="editar_plano.php?id=<?php echo $plano['id']; ?>">Editar</a>
                                    <a href="excluir_plano.php?id=<?php echo $plano['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
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