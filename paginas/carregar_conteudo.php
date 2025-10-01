<?php
session_start();
require_once '../config/db.php'; // Inclui a conexão com o banco de dados

// Verifica se o submenu_id foi passado via GET
if (!isset($_GET['submenu_id'])) {
    echo "Submenu ID não fornecido.";
    exit();
}

$submenu_id = intval($_GET['submenu_id']); // Converte para inteiro para evitar SQL injection

// Função para carregar conteúdo com base no submenu_id
function carregarConteudo($conn, $submenu_id) {
    $conteudos = [];
    $sql = "SELECT * FROM conteudos WHERE submenu_id = ?"; // Tabela corrigida para "conteudos"
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $submenu_id); // "i" indica que o parâmetro é um inteiro
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $conteudos[] = $row;
        }

        $stmt->close();
    } else {
        echo "Erro ao preparar a consulta: " . $conn->error;
    }

    return $conteudos;
}

// Função para obter o nome do submenu
function obterNomeSubmenu($conn, $submenu_id) {
    $sql = "SELECT nome FROM submenus WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $submenu_id);
    $stmt->execute();
    $stmt->bind_result($nome_submenu);
    $stmt->fetch();
    $stmt->close();
    return $nome_submenu;
}

// Função para converter URLs em links clicáveis
function converterUrlsParaLinks($texto) {
    // Expressão regular para detectar URLs e substituí-las por links clicáveis
    $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/i';
    $texto = preg_replace_callback($pattern, function($matches) {
        $url = $matches[2]; // URL do link
        $textoLink = $matches[3]; // Texto do link
        return '<a href="' . $url . '" target="_blank">' . $textoLink . '</a>';
    }, $texto);
    return $texto;
}

// Busca os conteúdos do submenu
$conteudos = carregarConteudo($conn, $submenu_id);

// Busca o nome do submenu
$nome_submenu = obterNomeSubmenu($conn, $submenu_id);

// Separa vídeos e documentações
$videos = array_filter($conteudos, function($conteudo) {
    return $conteudo['tipo'] === 'video';
});

$documentacoes = array_filter($conteudos, function($conteudo) {
    return $conteudo['tipo'] === 'documentacao';
});
?>

<!-- Exibe o título do submenu -->
<h2 id="page-title"> <?php echo htmlspecialchars($nome_submenu); ?></h2>

<!-- Exibe os vídeos -->
<?php if (!empty($videos)): ?>
    <div class="video-scroll-container" id="videoContainer">
        <?php foreach ($videos as $index => $video): ?>
            <iframe
                width="1000"
                height="562.5"
                src="https://www.youtube.com/embed/<?php echo htmlspecialchars($video['conteudo']); ?>?v=1741093579323"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                class="active"
                data-index="<?php echo $index; ?>"
            ></iframe>
        <?php endforeach; ?>
    </div>

    <!-- Controles de navegação para vídeos -->
    <div id="video-controls" style="display: <?php echo count($videos) > 1 ? 'block' : 'none'; ?>;">
        <ion-icon name="chevron-back-outline" id="prevVideo" role="img" class="md hydrated"></ion-icon>
        <span id="videoCounter">1 / <?php echo count($videos); ?></span>
        <ion-icon name="chevron-forward-outline" id="nextVideo" role="img" class="md hydrated"></ion-icon>
    </div>
    <?php else: ?>
    <p class="no-videos-message">Nenhum vídeo disponível para este submenu.</p>
<?php endif; ?>

<!-- Exibe as documentações -->
<?php if (!empty($documentacoes)): ?>
    <div class="documentation">
        <ul>
            <?php foreach ($documentacoes as $doc): ?>
                <li>
                    <!-- Aqui, exibimos o conteúdo com URLs convertidos em links -->
                    <?php echo converterUrlsParaLinks($doc['conteudo']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <p class="no-docs-message">Nenhuma documentação disponível para este submenu.</p>
<?php endif; ?>