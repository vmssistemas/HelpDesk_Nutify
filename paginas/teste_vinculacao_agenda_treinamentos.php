<?php
require_once '../config/db.php';
require_once '../config/session.php';

// Teste das modificações na vinculação agenda-treinamentos
echo "<h2>Teste das Modificações - Vinculação Agenda-Treinamentos</h2>";

// 1. Verificar se as colunas foram criadas corretamente
echo "<h3>1. Verificação da Estrutura do Banco de Dados</h3>";

// Verificar colunas na tabela treinamentos_agendamentos
$query = "SHOW COLUMNS FROM treinamentos_agendamentos LIKE 'agenda_evento_id'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ Coluna 'agenda_evento_id' existe na tabela treinamentos_agendamentos<br>";
} else {
    echo "❌ Coluna 'agenda_evento_id' NÃO existe na tabela treinamentos_agendamentos<br>";
}

$query = "SHOW COLUMNS FROM treinamentos_agendamentos LIKE 'sincronizado_automaticamente'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ Coluna 'sincronizado_automaticamente' existe na tabela treinamentos_agendamentos<br>";
} else {
    echo "❌ Coluna 'sincronizado_automaticamente' NÃO existe na tabela treinamentos_agendamentos<br>";
}

// Verificar coluna na tabela agenda_eventos
$query = "SHOW COLUMNS FROM agenda_eventos LIKE 'treinamento_agendamento_id'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ Coluna 'treinamento_agendamento_id' existe na tabela agenda_eventos<br>";
} else {
    echo "❌ Coluna 'treinamento_agendamento_id' NÃO existe na tabela agenda_eventos<br>";
}

// 2. Verificar se a view foi criada
echo "<h3>2. Verificação da View</h3>";
$query = "SHOW TABLES LIKE 'vw_agenda_treinamentos_vinculados'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ View 'vw_agenda_treinamentos_vinculados' foi criada com sucesso<br>";
    
    // Testar a view
    $query = "SELECT COUNT(*) as total FROM vw_agenda_treinamentos_vinculados";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ View funciona corretamente - Total de registros vinculados: " . $row['total'] . "<br>";
    }
} else {
    echo "❌ View 'vw_agenda_treinamentos_vinculados' NÃO foi criada<br>";
}

// 3. Verificar se os índices foram criados
echo "<h3>3. Verificação dos Índices</h3>";
$query = "SHOW INDEX FROM treinamentos_agendamentos WHERE Key_name = 'idx_agenda_evento_id'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ Índice 'idx_agenda_evento_id' foi criado<br>";
} else {
    echo "❌ Índice 'idx_agenda_evento_id' NÃO foi criado<br>";
}

$query = "SHOW INDEX FROM agenda_eventos WHERE Key_name = 'idx_treinamento_agendamento_id'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✅ Índice 'idx_treinamento_agendamento_id' foi criado<br>";
} else {
    echo "❌ Índice 'idx_treinamento_agendamento_id' NÃO foi criado<br>";
}

// 4. Testar migração de dados existentes
echo "<h3>4. Verificação da Migração de Dados</h3>";
$query = "SELECT COUNT(*) as total_migrados FROM treinamentos_agendamentos WHERE agenda_evento_id IS NOT NULL";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Total de agendamentos migrados com vinculação: " . $row['total_migrados'] . "<br>";
}

$query = "SELECT COUNT(*) as total_eventos FROM agenda_eventos WHERE tipo = 'tarefa' AND treinamento_agendamento_id IS NOT NULL";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Total de eventos de treinamento vinculados: " . $row['total_eventos'] . "<br>";
}

// 5. Verificar se as funções modificadas existem no agenda_ajax.php
echo "<h3>5. Verificação das Funções Modificadas</h3>";
$agenda_ajax_content = file_get_contents('agenda_ajax.php');

if (strpos($agenda_ajax_content, 'function criarAgendamentoTreinamento') !== false) {
    echo "✅ Função 'criarAgendamentoTreinamento' encontrada<br>";
    
    if (strpos($agenda_ajax_content, 'agenda_evento_id') !== false) {
        echo "✅ Função 'criarAgendamentoTreinamento' foi atualizada para usar agenda_evento_id<br>";
    } else {
        echo "❌ Função 'criarAgendamentoTreinamento' NÃO foi atualizada<br>";
    }
} else {
    echo "❌ Função 'criarAgendamentoTreinamento' NÃO encontrada<br>";
}

if (strpos($agenda_ajax_content, 'function atualizarAgendamentoTreinamento') !== false) {
    echo "✅ Função 'atualizarAgendamentoTreinamento' encontrada<br>";
} else {
    echo "❌ Função 'atualizarAgendamentoTreinamento' NÃO encontrada<br>";
}

if (strpos($agenda_ajax_content, 'function excluirVinculacaoTreinamento') !== false) {
    echo "✅ Função 'excluirVinculacaoTreinamento' foi adicionada<br>";
} else {
    echo "❌ Função 'excluirVinculacaoTreinamento' NÃO foi adicionada<br>";
}

// 6. Teste de integridade referencial
echo "<h3>6. Teste de Integridade Referencial</h3>";
$query = "SELECT COUNT(*) as inconsistencias FROM treinamentos_agendamentos ta 
          LEFT JOIN agenda_eventos ae ON ta.agenda_evento_id = ae.id 
          WHERE ta.agenda_evento_id IS NOT NULL AND ae.id IS NULL";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['inconsistencias'] == 0) {
        echo "✅ Nenhuma inconsistência encontrada na vinculação treinamentos -> eventos<br>";
    } else {
        echo "⚠️ Encontradas " . $row['inconsistencias'] . " inconsistências na vinculação<br>";
    }
}

$query = "SELECT COUNT(*) as inconsistencias FROM agenda_eventos ae 
          LEFT JOIN treinamentos_agendamentos ta ON ae.treinamento_agendamento_id = ta.id 
          WHERE ae.treinamento_agendamento_id IS NOT NULL AND ta.id IS NULL";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['inconsistencias'] == 0) {
        echo "✅ Nenhuma inconsistência encontrada na vinculação eventos -> treinamentos<br>";
    } else {
        echo "⚠️ Encontradas " . $row['inconsistencias'] . " inconsistências na vinculação reversa<br>";
    }
}

echo "<h3>7. Resumo do Teste</h3>";
echo "<p><strong>Status:</strong> Teste concluído. Verifique os resultados acima para identificar possíveis problemas.</p>";
echo "<p><strong>Próximos passos:</strong> Se todos os itens estão marcados com ✅, a implementação está funcionando corretamente.</p>";

?>