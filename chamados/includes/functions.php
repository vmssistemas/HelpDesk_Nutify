<?php
require_once __DIR__ . '/../../config/db.php';

function getChamadosStatus() {
    global $conn;
    $query = "SELECT * FROM chamados_status ORDER BY ordem";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getChamadosTipos() {
    global $conn;
    $query = "SELECT * FROM chamados_tipos";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getChamadosPrioridades() {
    global $conn;
    $query = "SELECT * FROM chamados_prioridades ORDER BY nivel";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getSprintsAtivas() {
    global $conn;
    $query = "SELECT * FROM chamados_sprints WHERE ativa IN (1, 2) ORDER BY data_inicio DESC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getUsuariosEquipe() {
    global $conn;
    $query = "SELECT id, nome, email FROM usuarios ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}
function getReleasesAtivas() {
    global $conn;
    $query = "SELECT id, nome, cor FROM chamados_releases WHERE status != 'cancelado' ORDER BY data_planejada DESC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getReleaseById($id) {
    global $conn;
    $query = "SELECT * FROM chamados_releases WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
// Pega todos os marcadores disponíveis
function getMarcadoresDisponiveis() {
    global $conn;
    $query = "SELECT * FROM chamados_marcadores ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Pega os marcadores vinculados a um chamado
function getMarcadoresChamado($chamado_id) {
    global $conn;
    $query = "SELECT m.*, u.nome as usuario_nome 
              FROM chamados_marcadores_vinculos v
              JOIN chamados_marcadores m ON v.marcador_id = m.id
              JOIN usuarios u ON v.usuario_id = u.id
              WHERE v.chamado_id = ?
              ORDER BY m.nome";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $chamado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Função para obter o nome do tipo de comentário
function getNomeTipoComentario($tipo) {
    $tipos = [
        'geral' => 'Geral',
        'analise_desenvolvimento' => 'Análise de Desenvolvimento',
        'analise_teste' => 'Análise de Teste',
        'retorno_teste' => 'Retorno de Teste'
    ];
    
    return $tipos[$tipo] ?? 'Geral';
}

// Função para obter a cor do tipo de comentário
function getCorTipoComentario($tipo) {
    $cores = [
        'geral' => '#6c757d',
        'analise_desenvolvimento' => '#0d6efd',
        'analise_teste' => '#fd7e14',
        'retorno_teste' => '#c70d0dff'
    ];
    
    return $cores[$tipo] ?? '#6c757d';
}

function getPontosHistoriaOptions() {
    return [
        ['value' => 1, 'label' => '1 ponto (0-2 horas)'],
        ['value' => 2, 'label' => '2 pontos (2-3 horas)'],
        ['value' => 3, 'label' => '3 pontos (1 período)'],
        ['value' => 5, 'label' => '5 pontos (1 dia)'],
        ['value' => 8, 'label' => '8 pontos (1,5 período)'],
        ['value' => 13, 'label' => '13 pontos (2,5 períodos)'],
        ['value' => 21, 'label' => '21 pontos (1 sprint)']
    ];
}

// Vincula um marcador a um chamado
function adicionarMarcadorChamado($chamado_id, $marcador_id, $usuario_id) {
    global $conn;
    $query = "INSERT INTO chamados_marcadores_vinculos (chamado_id, marcador_id, usuario_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $chamado_id, $marcador_id, $usuario_id);
    return $stmt->execute();
}

// Remove um marcador de um chamado
function removerMarcadorChamado($chamado_id, $marcador_id) {
    global $conn;
    $query = "DELETE FROM chamados_marcadores_vinculos WHERE chamado_id = ? AND marcador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $chamado_id, $marcador_id);
    return $stmt->execute();
}

// Cria um novo marcador
function criarMarcador($nome, $cor = '#6c757d') {
    global $conn;
    $query = "INSERT INTO chamados_marcadores (nome, cor) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $nome, $cor);
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}


function getChamadoConversaoChecklist($chamado_id) {
    global $conn;
    $query = "SELECT * FROM chamados_conversao_checklist WHERE chamado_id = ? ORDER BY id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $chamado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getChamadoById($id) {
    global $conn;
    
    $query = "SELECT c.*, s.nome as status_nome, s.cor as status_cor, 
                     t.nome as tipo_nome, t.icone as tipo_icone,
                     p.nome as prioridade_nome, p.cor as prioridade_cor,
                     u.nome as usuario_nome, r.nome as responsavel_nome,
                     cli.nome as cliente_nome, cli.contrato as cliente_contrato,
                     sp.nome as sprint_nome,
                     rel.nome as release_nome, rel.cor as release_cor,
                     m.nome as menu_nome, sm.nome as submenu_nome
              FROM chamados c
              LEFT JOIN chamados_status s ON c.status_id = s.id
              LEFT JOIN chamados_tipos t ON c.tipo_id = t.id
              LEFT JOIN chamados_prioridades p ON c.prioridade_id = p.id
              LEFT JOIN usuarios u ON c.usuario_id = u.id
              LEFT JOIN usuarios r ON c.responsavel_id = r.id
              LEFT JOIN clientes cli ON c.cliente_id = cli.id
              LEFT JOIN chamados_sprints sp ON c.sprint_id = sp.id
              LEFT JOIN chamados_releases rel ON c.release_id = rel.id
              LEFT JOIN menu_atendimento m ON c.menu_id = m.id
              LEFT JOIN submenu_atendimento sm ON c.submenu_id = sm.id
              WHERE c.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

function getComentariosChamado($chamado_id) {
    global $conn;
    
    $query = "SELECT cc.*, u.nome as usuario_nome, u.id as usuario_id 
              FROM chamados_comentarios cc
              JOIN usuarios u ON cc.usuario_id = u.id
              WHERE cc.chamado_id = ?
              ORDER BY cc.data_criacao ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $chamado_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getAnexosChamado($chamado_id) {
    global $conn;
    $query = "SELECT a.*, u.nome as usuario_nome
              FROM chamados_anexos a
              JOIN usuarios u ON a.usuario_id = u.id
              WHERE a.chamado_id = ?
              ORDER BY a.data_criacao DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $chamado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Função para salvar imagens do comentário
function salvarImagensComentario($comentario_id, $imagens_info) {
    global $conn;
    
    if (empty($imagens_info)) {
        return true;
    }
    
    $query = "INSERT INTO chamados_comentarios_imagens 
              (comentario_id, nome_arquivo, caminho_azure, blob_id, blob_url, blob_path, tamanho, mime_type, ordem) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    
    foreach ($imagens_info as $index => $imagem) {
        $stmt->bind_param(
            "isssssisi",
            $comentario_id,
            $imagem['nome_arquivo'],
            $imagem['caminho_azure'],
            $imagem['blob_id'],
            $imagem['blob_url'],
            $imagem['blob_path'],
            $imagem['tamanho'],
            $imagem['mime_type'],
            $index
        );
        
        if (!$stmt->execute()) {
            return false;
        }
    }
    
    return true;
}

// Função para extrair URLs de imagens do conteúdo HTML do comentário
function extrairImagensDoComentario($html_content) {
    $imagens = [];
    
    // Usar DOMDocument para extrair imagens
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $images = $dom->getElementsByTagName('img');
    
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (strpos($src, 'blob.core.windows.net') !== false) {
            // É uma imagem do Azure, extrair informações
            $imagens[] = $src;
        }
    }
    
    return $imagens;
}

// Função para buscar imagens de um comentário
function getImagensComentario($comentario_id) {
    global $conn;
    
    $query = "SELECT * FROM chamados_comentarios_imagens 
              WHERE comentario_id = ? 
              ORDER BY ordem ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $comentario_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Função para remover todas as imagens de um comentário
function removerImagensComentario($comentario_id) {
    global $conn;
    
    // Busca as imagens antes de remover para poder excluir do Azure se necessário
    $imagens = getImagensComentario($comentario_id);
    
    // Remove as imagens do banco de dados
    $query = "DELETE FROM chamados_comentarios_imagens WHERE comentario_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $comentario_id);
    
    if ($stmt->execute()) {
        // Aqui você pode adicionar lógica para remover as imagens do Azure Blob Storage
        // se necessário, usando as informações em $imagens
        return true;
    }
    
    return false;
}

// Função para atualizar imagens de um comentário (remove antigas e adiciona novas)
function atualizarImagensComentario($comentario_id, $novas_imagens_info) {
    global $conn;
    
    // Inicia transação
    $conn->begin_transaction();
    
    try {
        // Remove imagens antigas
        if (!removerImagensComentario($comentario_id)) {
            throw new Exception('Erro ao remover imagens antigas');
        }
        
        // Adiciona novas imagens se houver
        if (!empty($novas_imagens_info) && is_array($novas_imagens_info)) {
            if (!salvarImagensComentario($comentario_id, $novas_imagens_info)) {
                throw new Exception('Erro ao salvar novas imagens');
            }
        }
        
        // Confirma a transação
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Desfaz a transação em caso de erro
        $conn->rollback();
        registrarErro('Erro ao atualizar imagens do comentário ' . $comentario_id . ': ' . $e->getMessage());
        return false;
    }
}

// Função para verificar se um comentário tem imagens
function comentarioTemImagens($comentario_id) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM chamados_comentarios_imagens WHERE comentario_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $comentario_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] > 0;
}

// Função adicional para verificar se o usuário tem permissão
function verificaPermissao($usuario_id, $permissao_necessaria) {
    // Implemente sua lógica de permissões aqui
    return true; // Temporário - ajuste conforme sua estrutura de permissões
}

// Função para registrar erros
function registrarErro($mensagem) {
    $log_file = __DIR__ . '/../../logs/erros.log';
    $mensagem = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
    file_put_contents($log_file, $mensagem, FILE_APPEND);
}
function getClientes() {
    global $conn;
    $query = "SELECT id, nome, contrato FROM clientes ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}
// Adicionar estas funções no arquivo functions.php

function getMenusAtendimento() {
    global $conn;
    $query = "SELECT * FROM menu_atendimento ORDER BY ordem";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getSubmenusAtendimento() {
    global $conn;
    $query = "SELECT * FROM submenu_atendimento ORDER BY menu_id, ordem";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}
function getAnexoById($id) {
    global $conn;
    
    $query = "SELECT * FROM chamados_anexos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}


function abrirLinksNovaGuia($html) {
    // Primeiro sanitiza o conteúdo para segurança, incluindo imagens e formatação
    $html = strip_tags($html, '<p><a><strong><em><u><s><del><strike><ul><ol><li><br><h1><h2><h3><h4><img>');
    
    // Adiciona target="_blank" e rel="noopener noreferrer" a todos os links
    $html = preg_replace_callback('/<a[^>]+/', function($matches) {
        $link = $matches[0];
        
        // Se já tiver target, substitui
        if (preg_match('/target\s*=/', $link)) {
            $link = preg_replace('/target\s*=\s*["\'][^"\']["\']/', 'target="_blank" rel="noopener noreferrer"', $link);
        } 
        // Se não tiver target, adiciona
        else {
            $link .= ' target="_blank" rel="noopener noreferrer"';
        }
        
        return $link;
    }, $html);
    
    // Adiciona classes e atributos para zoom nas imagens do Azure
    $html = preg_replace_callback('/<img[^>]+>/', function($matches) {
        $img = $matches[0];
        
        // Verifica se é uma imagem do Azure Blob Storage
        if (strpos($img, 'blob.core.windows.net') !== false) {
            // Extrai o src da imagem
            preg_match('/src=["\']([^"\'][^"\']*)/', $img, $src_matches);
            if (isset($src_matches[1])) {
                $src = $src_matches[1];
                
                // Adiciona classes e atributos para o zoom
                $img = preg_replace('/<img/', '<img class="comment-image-inline" data-image-src="' . htmlspecialchars($src) . '"', $img);
            }
        }
        
        return $img;
    }, $html);
    
    return $html;
}
// Adicione estas funções no arquivo functions.php

function getFiltrosUsuario($usuario_id) {
    global $conn;
    $query = "SELECT * FROM usuario_filtros 
              WHERE usuario_id = ? OR compartilhado = 1 
              ORDER BY padrao DESC, nome";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}



function salvarFiltroUsuario($usuario_id, $nome, $filtros, $compartilhado = 0) {
    global $conn;
    $query = "INSERT INTO usuario_filtros (usuario_id, nome, filtros, compartilhado) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $filtros_serializados = json_encode($filtros);
    $stmt->bind_param("issi", $usuario_id, $nome, $filtros_serializados, $compartilhado);
    return $stmt->execute();
}

function deletarFiltroUsuario($filtro_id, $usuario_id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Primeiro verifica se é o filtro padrão
        $query = "SELECT padrao FROM usuario_filtros WHERE id = ? AND usuario_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $filtro_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $filtro = $result->fetch_assoc();
        $era_padrao = $filtro['padrao'];
        
        // Exclui o filtro
        $query = "DELETE FROM usuario_filtros WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $filtro_id);
        $stmt->execute();
        
        // Se era o padrão, define um novo padrão (o mais recente)
        if ($era_padrao) {
            $query = "UPDATE usuario_filtros SET padrao = 1 
                      WHERE usuario_id = ? 
                      ORDER BY id DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
function definirFiltroPadrao($filtro_id, $usuario_id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Primeiro remove o padrão de todos os filtros do usuário
        $query = "UPDATE usuario_filtros SET padrao = 0 WHERE usuario_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        
        // Depois define o novo filtro como padrão
        $query = "UPDATE usuario_filtros SET padrao = 1 WHERE id = ? AND usuario_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $filtro_id, $usuario_id);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
function registrarVisualizacaoChamado($chamado_id, $usuario_id) {
    global $conn;
    
    // Verifica se já existe um registro para este usuário e chamado
    $query = "SELECT id FROM chamados_visualizacoes 
              WHERE chamado_id = ? AND usuario_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $chamado_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Se não existir, cria um novo registro
    if ($result->num_rows === 0) {
        $query = "INSERT INTO chamados_visualizacoes (chamado_id, usuario_id) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $chamado_id, $usuario_id);
        return $stmt->execute();
    }
    
    return true;
}

function getUsuariosQueVisualizaram($chamado_id) {
    global $conn;
    
    $query = "SELECT u.id, u.nome, v.data_visualizacao 
              FROM chamados_visualizacoes v
              JOIN usuarios u ON v.usuario_id = u.id
              WHERE v.chamado_id = ?
              ORDER BY v.data_visualizacao DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $chamado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
function formatarVisualizacoesTooltip($visualizacoes) {
    if (empty($visualizacoes)) {
        return "Nenhuma visualização registrada";
    }
    
    $html = '<div class="text-start"><strong>Visualizado por:</strong><ul class="mb-0">';
    
    foreach ($visualizacoes as $visualizacao) {
        $data = date('d/m/Y H:i', strtotime($visualizacao['data_visualizacao']));
        $html .= sprintf(
            '<li>%s <small class="text-muted">(%s)</small></li>',
            htmlspecialchars($visualizacao['nome']),
            $data
        );
    }
    
    $html .= '</ul></div>';
    return $html;
}