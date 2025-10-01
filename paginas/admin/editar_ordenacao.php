<?php
session_start();

// Verifica se o usuário está autenticado e é admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Busca todos os menus ordenados pela ordem
$query_menus = "SELECT * FROM menus ORDER BY ordem";
$result_menus = $conn->query($query_menus);
$menus = $result_menus->fetch_all(MYSQLI_ASSOC);

// Busca todos os submenus ordenados pela ordem
$query_submenus = "SELECT * FROM submenus ORDER BY menu_id, ordem";
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

// Atualiza a ordem dos itens
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['menus'])) {
        foreach ($data['menus'] as $order => $menu_id) {
            $stmt = $conn->prepare("UPDATE menus SET ordem = ? WHERE id = ?");
            $stmt->bind_param("ii", $order, $menu_id);
            $stmt->execute();
        }
    }
    if (isset($data['submenus'])) {
        foreach ($data['submenus'] as $menu_id => $submenu_orders) {
            foreach ($submenu_orders as $order => $submenu_id) {
                $stmt = $conn->prepare("UPDATE submenus SET ordem = ? WHERE id = ? AND menu_id = ?");
                $stmt->bind_param("iii", $order, $submenu_id, $menu_id);
                $stmt->execute();
            }
        }
    }
    echo json_encode(['status' => 'success']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ordenação</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
</head>
<body>
    <header>
        <h1>Editar Ordenação</h1>
        <nav>
            <a href="administracao.php">Voltar</a>
        </nav>
    </header>

    <main>
        <h2>Menus</h2>
        <ul id="menu-list" class="sortable">
            <?php foreach ($menus as $menu): ?>
                <li data-id="<?php echo $menu['id']; ?>"><?php echo htmlspecialchars($menu['nome']); ?></li>
            <?php endforeach; ?>
        </ul>

        <h2>Submenus</h2>
        <?php foreach ($menus as $menu): ?>
            <?php if (isset($submenus_por_menu[$menu['id']])): ?>
                <h3>Submenu de: <?php echo htmlspecialchars($menu['nome']); ?></h3>
                <ul class="submenu-list sortable" data-menu-id="<?php echo $menu['id']; ?>">
                    <?php foreach ($submenus_por_menu[$menu['id']] as $submenu): ?>
                        <li data-id="<?php echo $submenu['id']; ?>"><?php echo htmlspecialchars($submenu['nome']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>

        <button id="save-order">Salvar Ordenação</button>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuList = document.getElementById('menu-list');
            const submenuLists = document.querySelectorAll('.submenu-list');

            // Inicializa Sortable para menus
            new Sortable(menuList, {
                animation: 150,
                onEnd: function(evt) {
                    console.log('Menu reordenado');
                }
            });

            // Inicializa Sortable para cada lista de submenus
            submenuLists.forEach(list => {
                new Sortable(list, {
                    animation: 150,
                    onEnd: function(evt) {
                        console.log('Submenu reordenado');
                    }
                });
            });

            // Salvar ordenação
            document.getElementById('save-order').addEventListener('click', function() {
                const menus = Array.from(menuList.children).map((li, index) => ({
                    id: li.dataset.id,
                    order: index
                }));

                const submenus = {};
                submenuLists.forEach(list => {
                    const menuId = list.dataset.menuId;
                    submenus[menuId] = Array.from(list.children).map((li, index) => ({
                        id: li.dataset.id,
                        order: index
                    }));
                });

                fetch('editar_ordenacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        menus: menus.reduce((acc, item) => ({ ...acc, [item.order]: item.id }), {}),
                        submenus: Object.keys(submenus).reduce((acc, menuId) => {
                            acc[menuId] = submenus[menuId].reduce((subAcc, item) => ({ ...subAcc, [item.order]: item.id }), {});
                            return acc;
                        }, {})
                    })
                }).then(response => response.json())
                  .then(data => {
                      if (data.status === 'success') {
                          alert('Ordenação salva com sucesso!');
                      }
                  });
            });
        });
    </script>
</body>
<script>
    // Adiciona um listener para o evento de tecla pressionada
    document.addEventListener('keydown', function(event) {
        // Verifica se a tecla pressionada é ESC (código 27)
        if (event.keyCode === 27) {
            // Volta para a página anterior
            window.history.back();
        }
    });
</script>
</html>