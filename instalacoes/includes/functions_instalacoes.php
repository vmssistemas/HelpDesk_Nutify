<?php
require_once __DIR__ . '/../../config/db.php';

function getInstalacoesStatus() {
    global $conn;
    $query = "SELECT * FROM instalacoes_status ORDER BY ordem";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getInstalacaoById($id) {
    global $conn;
    
    $query = "SELECT i.*, s.nome as status_nome, s.cor as status_cor,
                     c.nome as cliente_nome, c.contrato as cliente_contrato,
                     p.nome as plano_nome, u.nome as usuario_nome,
                     r.nome as responsavel_nome,
                     it.nome as tipo_nome
              FROM instalacoes i
              LEFT JOIN instalacoes_status s ON i.status_id = s.id
              LEFT JOIN clientes c ON i.cliente_id = c.id
              LEFT JOIN planos p ON i.plano_id = p.id
              LEFT JOIN usuarios u ON i.usuario_id = u.id
              LEFT JOIN usuarios r ON i.responsavel_id = r.id
              LEFT JOIN instalacoes_tipos it ON i.tipo_id = it.id
              WHERE i.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

function getInstalacoesChecklist($instalacao_id) {
    global $conn;
    
    $query = "SELECT ic.id, ic.tipo, ic.concluido, ic.horas, ic.observacao, ic.data_conclusao,
                     COALESCE(ip.item, iu.item, im.item) as item,
                     COALESCE(ip.descricao, iu.descricao, im.descricao) as item_descricao,
                     COALESCE(ip.ordem, iu.ordem, im.ordem) as item_ordem,
                     ic.item_plano_id, ic.item_upgrade_id, ic.item_modulo_id
              FROM instalacoes_checklist ic
              LEFT JOIN instalacoes_itens_plano ip ON ic.item_plano_id = ip.id AND ic.tipo = 'plano'
              LEFT JOIN instalacoes_itens_upgrade iu ON ic.item_upgrade_id = iu.id AND ic.tipo = 'upgrade'
              LEFT JOIN instalacoes_itens_modulo im ON ic.item_modulo_id = im.id AND ic.tipo = 'modulo'
              WHERE ic.instalacao_id = ?
              ORDER BY item_ordem";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $instalacao_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
function criarChecklistParaInstalacao($instalacao_id, $plano_id, $tipo_id, $dados_especificos = []) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        if ($tipo_id == 1) { // Implantação de plano
            if (!$plano_id) {
                throw new Exception("Plano ID é obrigatório para instalações do tipo implantação");
            }
            
            $query = "SELECT id, horas_padrao FROM instalacoes_itens_plano 
                     WHERE plano_id = ? AND ativo = 1 ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $plano_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $itens = $result->fetch_all(MYSQLI_ASSOC);
            
            foreach ($itens as $item) {
                $query = "INSERT INTO instalacoes_checklist 
                         (instalacao_id, tipo, item_plano_id, horas) 
                         VALUES (?, 'plano', ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iid", $instalacao_id, $item['id'], $item['horas_padrao']);
                $stmt->execute();
            }
        } 
        elseif ($tipo_id == 2) { // Upgrade de plano
            $origem = $dados_especificos['plano_origem'] ?? null;
            $destino = $dados_especificos['plano_destino'] ?? null;
            
            if (!$origem || !$destino) {
                throw new Exception("Planos de origem e destino são obrigatórios para upgrades");
            }
            
            $query = "SELECT id, horas_padrao FROM instalacoes_itens_upgrade 
                     WHERE origem_plano_id = ? AND destino_plano_id = ? AND ativo = 1 
                     ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $origem, $destino);
            $stmt->execute();
            $result = $stmt->get_result();
            $itens = $result->fetch_all(MYSQLI_ASSOC);
            
            foreach ($itens as $item) {
                $query = "INSERT INTO instalacoes_checklist 
                         (instalacao_id, tipo, item_upgrade_id, horas) 
                         VALUES (?, 'upgrade', ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iid", $instalacao_id, $item['id'], $item['horas_padrao']);
                $stmt->execute();
            }
        }
        elseif ($tipo_id == 4) { // Módulo
            $modulo = $dados_especificos['modulo'] ?? null;
            
            if (!$modulo) {
                throw new Exception("Módulo é obrigatório para instalações do tipo módulo");
            }
            
            $query = "SELECT id, horas_padrao FROM instalacoes_itens_modulo 
                     WHERE modulo = ? AND ativo = 1 ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $modulo);
            $stmt->execute();
            $result = $stmt->get_result();
            $itens = $result->fetch_all(MYSQLI_ASSOC);
            
            foreach ($itens as $item) {
                $query = "INSERT INTO instalacoes_checklist 
                         (instalacao_id, tipo, item_modulo_id, horas) 
                         VALUES (?, 'modulo', ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iid", $instalacao_id, $item['id'], $item['horas_padrao']);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro ao criar checklist: " . $e->getMessage());
        return false;
    }
}
function getClienteNameById($id) {
    global $conn;
    $query = "SELECT nome, contrato FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return '';
    }
    
    $cliente = $result->fetch_assoc();
    return ($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome'];
}
function getItensPlanoInstalacao($plano_id) {
    global $conn;
    $query = "SELECT * FROM instalacoes_itens_plano 
              WHERE plano_id = ? AND ativo = 1 ORDER BY ordem";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $plano_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getItensUpgradeInstalacao($origem_id, $destino_id) {
    global $conn;
    $query = "SELECT * FROM instalacoes_itens_upgrade 
              WHERE origem_plano_id = ? AND destino_plano_id = ? AND ativo = 1 
              ORDER BY ordem";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $origem_id, $destino_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getItensModuloInstalacao($modulo) {
    global $conn;
    $query = "SELECT * FROM instalacoes_itens_modulo 
              WHERE modulo = ? AND ativo = 1 ORDER BY ordem";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $modulo);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getPlanosChecklist($plano_id) {
    global $conn;
    $query = "SELECT * FROM planos_checklist WHERE plano_id = ? ORDER BY ordem";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $plano_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getClientes() {
    global $conn;
    $query = "SELECT id, nome, contrato, plano FROM clientes ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getPlanos() {
    global $conn;
    $query = "SELECT id, nome FROM planos ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getEquipe() {
    global $conn;
    $query = "SELECT id, nome FROM usuarios ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getInstalacaoComentarios($instalacao_id) {
    global $conn;
    $query = "SELECT c.*, u.nome as usuario_nome 
              FROM instalacoes_comentarios c
              JOIN usuarios u ON c.usuario_id = u.id
              WHERE c.instalacao_id = ?
              ORDER BY c.data_criacao DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $instalacao_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function adicionarComentario($instalacao_id, $usuario_id, $comentario) {
    global $conn;
    $query = "INSERT INTO instalacoes_comentarios (instalacao_id, usuario_id, comentario) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $instalacao_id, $usuario_id, $comentario);
    return $stmt->execute();
}

function calcularTotalHorasChecklist($checklist) {
    $total = 0;
    foreach ($checklist as $item) {
        $total += $item['horas'] ?? 0;
    }
    return number_format($total, 2);
}

function atualizarItemChecklist($instalacao_id, $item_id, $dados) {
    global $conn;
    
    $query = "UPDATE instalacoes_checklist SET 
                concluido = ?,
                horas = ?,
                observacao = ?
              WHERE id = ? AND instalacao_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "idsii",
        $dados['concluido'],
        $dados['horas'],
        $dados['observacao'],
        $item_id,
        $instalacao_id
    );
    
    return $stmt->execute();
}

function getInstalacaoAgendamentos($instalacao_id) {
    global $conn;
    
    $query = "SELECT a.*, u.nome as usuario_nome 
              FROM instalacoes_agendamentos a
              LEFT JOIN usuarios u ON a.usuario_id = u.id
              WHERE a.instalacao_id = ?
              ORDER BY a.data_agendada DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $instalacao_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function adicionarAgendamento($instalacao_id, $data_agendada, $horas, $observacao, $usuario_id = null) {
    global $conn;
    
    $query = "INSERT INTO instalacoes_agendamentos 
              (instalacao_id, usuario_id, data_agendada, horas, observacao) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisds", $instalacao_id, $usuario_id, $data_agendada, $horas, $observacao);
    return $stmt->execute();
}

function atualizarStatusAgendamento($agendamento_id, $status, $motivo_cancelamento = null) {
    global $conn;
    
    $query = "UPDATE instalacoes_agendamentos SET 
              status = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,
              motivo_cancelamento = ?,
              data_conclusao = CASE WHEN CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'realizado' THEN NOW() ELSE NULL END
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $status, $motivo_cancelamento, $status, $agendamento_id);
    return $stmt->execute();
}

function excluirAgendamento($agendamento_id) {
    global $conn;
    
    $query = "DELETE FROM instalacoes_agendamentos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $agendamento_id);
    return $stmt->execute();
}

function atualizarAgendamento($agendamento_id, $data_agendada, $horas, $observacao, $status, $usuario_id = null, $motivo_cancelamento = null) {
    global $conn;
    
    $query = "UPDATE instalacoes_agendamentos SET 
              data_agendada = ?,
              horas = ?,
              observacao = ?,
              status = ?,
              usuario_id = ?,
              motivo_cancelamento = ?,
              data_conclusao = CASE WHEN ? = 'realizado' AND status != 'realizado' THEN NOW() 
                                   WHEN ? != 'realizado' THEN NULL 
                                   ELSE data_conclusao END
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdsssssii", 
        $data_agendada, 
        $horas, 
        $observacao, 
        $status, 
        $usuario_id, 
        $motivo_cancelamento, 
        $status, 
        $status, 
        $agendamento_id
    );
    return $stmt->execute();
}

function getAgendamentoById($agendamento_id) {
    global $conn;
    
    $query = "SELECT * FROM instalacoes_agendamentos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $agendamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}
?>