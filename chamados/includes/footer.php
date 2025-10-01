</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Configurações iniciais
    let isLoading = false;
    let currentPage = 1;
    const urlParams = new URLSearchParams(window.location.search);
    
    // Verifica se estamos na página de releases para não executar código desnecessário
  if (!window.location.pathname.includes('releases.php') && !window.location.pathname.includes('sprints.php')) {
        // Funções específicas para outras páginas
        
        function initSortableColumn(column) {
            if (!column) return;
            
            new Sortable(column, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'kanban-ghost',
                dragClass: 'kanban-drag',
                onStart: function(evt) {
                    if (!evt.item) return;
                    evt.item.classList.add('kanban-dragging');
                    
                    const kanbanContainer = document.querySelector('.kanban-scroll-container');
                    if (kanbanContainer) {
                        kanbanContainer.dataset.scrollTop = kanbanContainer.scrollTop;
                        kanbanContainer.dataset.scrollLeft = kanbanContainer.scrollLeft;
                    }
                },
                onEnd: function(evt) {
                    if (!evt.item) return;
                    evt.item.classList.remove('kanban-dragging');
                    
                    const kanbanContainer = document.querySelector('.kanban-scroll-container');
                    if (kanbanContainer && kanbanContainer.dataset.scrollTop) {
                        kanbanContainer.scrollTop = parseInt(kanbanContainer.dataset.scrollTop);
                        kanbanContainer.scrollLeft = parseInt(kanbanContainer.dataset.scrollLeft);
                    }
                    
                    if (evt.to && evt.from) {
                        handleCardMove(evt);
                    }
                },
                onAdd: function(evt) {
                    if (!evt.to) return;
                    evt.to.classList.add('kanban-drop');
                    setTimeout(() => {
                        evt.to.classList.remove('kanban-drop');
                    }, 300);
                }
            });
        }

        function initSortable() {
            const kanbanColumns = document.querySelectorAll('.kanban-column');
            if (!kanbanColumns || kanbanColumns.length === 0) return;
            
            kanbanColumns.forEach(column => {
                initSortableColumn(column);
            });
        }

        // Função para lidar com o movimento de um card
        async function handleCardMove(evt) {
            const chamadoId = evt.item.dataset.id;
            const novoStatusId = evt.to.dataset.status;
            const statusAnteriorId = evt.from.dataset.status;
            
            // Adiciona estado de loading no card
            evt.item.classList.add('kanban-loading');
            
            // Atualiza imediatamente os pontos das colunas afetadas
            updateColumnStats(statusAnteriorId);
            updateColumnStats(novoStatusId);
            
            try {
                // Atualiza no servidor
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('id', chamadoId);
                formData.append('status_id', novoStatusId);
                
                const response = await fetch('api/chamados.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) throw new Error('Falha na atualização');
                
                // Atualiza as colunas afetadas
                await Promise.all([
                    updateKanbanColumn(statusAnteriorId),
                    updateKanbanColumn(novoStatusId)
                ]);
                
                // Atualiza as estatísticas do dashboard
                updateDashboardStats();
                
                // Feedback visual sutil
                evt.item.classList.add('kanban-updated');
                setTimeout(() => {
                    evt.item.classList.remove('kanban-updated', 'kanban-loading');
                }, 1000);
                
            } catch (error) {
                console.error('Erro:', error);
                // Reverte visualmente se houver erro
                evt.from.appendChild(evt.item);
                evt.item.classList.remove('kanban-loading');
                // Reverte os pontos das colunas
                updateColumnStats(statusAnteriorId);
                updateColumnStats(novoStatusId);
            }
        }

       // Função para atualizar a lista de forma reativa
window.updateListView = async function() {
    try {
        // Salva a posição atual do scroll
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Pega os parâmetros atuais da URL
        const params = new URLSearchParams(window.location.search);
        params.set('ajax', 'true');
        
        // Faz a requisição para o servidor
        const response = await fetch(`index.php?${params.toString()}`);
        const html = await response.text();
        
        // Analisa o HTML retornado
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Encontra a tabela da lista no HTML retornado
        const newListContainer = doc.querySelector('.list-view');
        if (!newListContainer) return;
        
        // Atualiza o container da lista atual
        const currentListContainer = document.querySelector('.list-view');
        if (currentListContainer) {
            // Adiciona transição suave
            currentListContainer.style.opacity = '0.5';
            
            setTimeout(() => {
                currentListContainer.innerHTML = newListContainer.innerHTML;
                currentListContainer.style.opacity = '1';
                
                // Restaura a posição do scroll
                window.scrollTo(0, scrollTop);
                
                // Atualiza as estatísticas do dashboard
                updateDashboardStats();
            }, 150);
        }
        
    } catch (error) {
        console.error('Erro ao atualizar lista:', error);
        // Em caso de erro, recarrega a página como fallback
        window.location.reload();
    }
};

window.updateKanbanColumn = async function(statusId) {
    try {
        // Salva a posição atual do scroll antes da atualização
        const kanbanContainer = document.querySelector('.kanban-scroll-container');
        const scrollTop = kanbanContainer.scrollTop;
        const scrollLeft = kanbanContainer.scrollLeft;
        
        // Pega os parâmetros atuais da URL
        const params = new URLSearchParams(window.location.search);
        
        // Remove o parâmetro 'status' para obter todos os status
        params.delete('status');
        params.set('ajax', 'true');
        
        // Faz a requisição para o servidor
        const response = await fetch(`index.php?${params.toString()}`);
        const html = await response.text();
        
        // Analisa o HTML retornado
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Encontra a coluna correspondente no HTML retornado
        const newColumn = doc.querySelector(`.kanban-column[data-status="${statusId}"]`);
        if (!newColumn) return;
        
        // Atualiza a coluna atual com o novo conteúdo
        const currentColumn = document.querySelector(`.kanban-column[data-status="${statusId}"]`);
        const currentColumnWrapper = currentColumn.closest('.kanban-column-wrapper');
        
        // Adiciona transição suave
        currentColumn.style.opacity = '0.5';
        setTimeout(() => {
            currentColumn.innerHTML = newColumn.innerHTML;
            currentColumn.style.opacity = '1';
            
            // Re-inicializa os cards arrastáveis na coluna atualizada
            initSortableColumn(currentColumn);
            
            // Força uma nova renderização para garantir que o DOM seja atualizado
            currentColumnWrapper.style.display = 'none';
            currentColumnWrapper.offsetHeight; // Trigger reflow
            currentColumnWrapper.style.display = 'block';
            
            // Atualiza o contador e os pontos após a renderização
            setTimeout(() => {
                updateColumnStats(statusId);
                // Reinicializa os event listeners dos selects de pontos na coluna atualizada
                initPontosHistoriaListeners(currentColumn);
            }, 50);
            
            // Restaura a posição do scroll após a atualização
            kanbanContainer.scrollTop = scrollTop;
            kanbanContainer.scrollLeft = scrollLeft;
        }, 150);
        
    } catch (error) {
        console.error('Erro ao atualizar coluna:', error);
        document.querySelector(`.kanban-column[data-status="${statusId}"]`).style.opacity = '1';
    }
};

function updateColumnStats(statusId) {
    const column = document.querySelector(`.kanban-column[data-status="${statusId}"]`);
    const header = column.closest('.kanban-column-wrapper').querySelector('.kanban-column-header');
    const countElement = header.querySelector('.kanban-count');
    const pointsElement = header.querySelector('.kanban-points');
    
    // Conta os cards visíveis
    const cards = column.querySelectorAll('.kanban-card:not(.kanban-ghost)');
    const cardCount = cards.length;
    
    // Calcula o total de pontos
    let totalPoints = 0;
    cards.forEach(card => {
        const pointsSelect = card.querySelector('.pontos-historia-select');
        if (pointsSelect && pointsSelect.value) {
            totalPoints += parseInt(pointsSelect.value);
        }
    });
    
    // Animação suave do contador
    animateValue(countElement, cardCount, 200);
    
    // Atualiza os pontos
    if (pointsElement) {
        if (totalPoints > 0) {
            pointsElement.textContent = `${totalPoints} pts`;
            pointsElement.style.display = 'flex';
        } else {
            pointsElement.style.display = 'none';
        }
    }
    
    // Atualiza o empty state
    const emptyState = column.querySelector('.kanban-empty');
    if (emptyState) {
        emptyState.querySelector('span').textContent = 
            cardCount === 0 ? 'Nenhum chamado' : 'Filtrado';
    }
}
        // Função para atualizar apenas o contador de uma coluna
        function updateColumnCounter(statusId) {
            const column = document.querySelector(`.kanban-column[data-status="${statusId}"]`);
            const header = column.closest('.kanban-column-wrapper').querySelector('.kanban-column-header');
            const countElement = header.querySelector('.kanban-count');
            
            // Conta os cards visíveis
            const cardCount = column.querySelectorAll('.kanban-card:not(.kanban-ghost)').length;
            
            // Animação suave do contador
            animateValue(countElement, cardCount, 200);
            
            // Atualiza o empty state
            const emptyState = column.querySelector('.kanban-empty');
            if (emptyState) {
                emptyState.querySelector('span').textContent = 
                    cardCount === 0 ? 'Nenhum chamado' : 'Filtrado';
            }
        }

window.updateDashboardStats = async function() {
    try {
        // Adiciona classe de loading nos cards de estatísticas
        const statCards = document.querySelectorAll('.card.text-white');
        statCards.forEach(card => card.classList.add('stat-loading'));
        
        const response = await fetch('api/dashboard_stats.php');
        const data = await response.json();
        
        if (data.success) {
            // Animação suave de contagem
            animateValue(document.querySelector('.card.bg-primary .card-text'), data.total, 300);
            animateValue(document.querySelector('.card.bg-success .card-text'), data.concluidos, 300);
            animateValue(document.querySelector('.card.bg-warning .card-text'), data.em_andamento, 300);
            animateValue(document.querySelector('.card.bg-danger .card-text'), data.aplicar_cliente, 300);
        }
    } catch (error) {
        console.error('Erro ao atualizar estatísticas:', error);
    } finally {
        // Remove classe de loading
        const statCards = document.querySelectorAll('.card.text-white');
        statCards.forEach(card => card.classList.remove('stat-loading'));
    }
};

        // Função para animar a mudança de valores numéricos
        function animateValue(element, target, duration) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            if (!element) return;
            
            const start = parseInt(element.textContent) || 0;
            const targetNumber = parseInt(target);
            const increment = targetNumber > start ? 1 : -1;
            const range = targetNumber - start;
            const startTime = performance.now();
            
            function updateValue(timestamp) {
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const value = start + (range * progress);
                
                element.textContent = Math.floor(value);
                
                if (progress < 1) {
                    requestAnimationFrame(updateValue);
                } else {
                    element.textContent = targetNumber;
                }
            }
            
            requestAnimationFrame(updateValue);
        }

        // Função de carregamento infinito removida - agora usando paginação

        // Funções de navegação do Kanban
        function initKanbanNavigation() {
            const scrollContainer = document.getElementById('kanbanScrollContainer');
            const leftBtn = document.getElementById('kanbanNavLeft');
            const rightBtn = document.getElementById('kanbanNavRight');
            
            if (!scrollContainer || !leftBtn || !rightBtn) return;
            
            const scrollAmount = 300; // Pixels para rolar
            
            // Função para atualizar estado dos botões
            function updateButtonStates() {
                const isAtStart = scrollContainer.scrollLeft <= 0;
                const isAtEnd = scrollContainer.scrollLeft >= (scrollContainer.scrollWidth - scrollContainer.clientWidth);
                
                leftBtn.disabled = isAtStart;
                rightBtn.disabled = isAtEnd;
            }
            
            // Event listeners para os botões
            leftBtn.addEventListener('click', () => {
                scrollContainer.scrollBy({
                    left: -scrollAmount,
                    behavior: 'smooth'
                });
            });
            
            rightBtn.addEventListener('click', () => {
                scrollContainer.scrollBy({
                    left: scrollAmount,
                    behavior: 'smooth'
                });
            });
            
            // Atualizar estado dos botões quando o scroll mudar
            scrollContainer.addEventListener('scroll', updateButtonStates);
            
            // Estado inicial
            updateButtonStates();
        }

        // Funções auxiliares
        function toggleAllStatus(source) {
            const checkboxes = document.querySelectorAll('.status-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

     function selectDefaultStatus() {
    // IDs dos status que devem ser selecionados por padrão
    // Incluindo Backlog development (10) e Aplicar no cliente (11)
    const defaultStatusIds = [1, 2, 3, 4, 7, 8, 9, 10, 11, 12];
    const checkboxes = document.querySelectorAll('.status-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = defaultStatusIds.includes(parseInt(checkbox.value));
    });
    
    document.getElementById('select-all-status').checked = false;
}
        
        // Inicialização quando a página carrega - paginação implementada
        
        if (document.querySelector('.kanban-board')) {
            initSortable();
        }
    }

    // Inicializa tooltips apenas se existirem
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (tooltipTriggerList.length > 0) {
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Inicializa datepickers apenas se existirem
    if (document.querySelector(".datepicker")) {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            allowInput: true
        });
    }

    // Verifica status selecionados
    const checkboxes = document.querySelectorAll('.status-checkbox');
    if (checkboxes.length > 0) {
        const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
        const selectAllStatus = document.getElementById('select-all-status');
        if (selectAllStatus) {
            selectAllStatus.checked = allChecked;
        }
    }

    // Select all cards in a column when header checkbox is clicked
    document.querySelectorAll('.select-all-cards').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const statusId = this.dataset.status;
            const column = document.querySelector(`.kanban-column[data-status="${statusId}"]`);
            if (column) {
                const checkboxes = column.querySelectorAll('.kanban-card-select');
                
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
            }
        });
    });

    // Select all cards in list view
document.getElementById('select-all-list')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.list-card-select');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
    });
});

// Atualize a função globalBulkActionsBtn para funcionar em ambas as visualizações
const globalBulkActionsBtn = document.getElementById('globalBulkActionsBtn');
if (globalBulkActionsBtn) {
    globalBulkActionsBtn.addEventListener('click', function() {
        // Verifica qual visualização está ativa
        const isKanbanView = document.querySelector('.kanban-board') !== null;
        const isListView = document.querySelector('.table') !== null;
        
        let selectedCards = [];
        let currentStatus = null;
        
        if (isKanbanView) {
            selectedCards = Array.from(document.querySelectorAll('.kanban-card-select:checked'))
                .map(checkbox => checkbox.value);
            
            // Get the current status from the first selected card
            const firstSelectedCard = document.querySelector('.kanban-card-select:checked')?.closest('.kanban-card');
            if (firstSelectedCard) {
                currentStatus = firstSelectedCard.closest('.kanban-column').dataset.status;
            }
        } else if (isListView) {
            selectedCards = Array.from(document.querySelectorAll('.list-card-select:checked'))
                .map(checkbox => checkbox.value);
            
            // Get the current status from the first selected card
            const firstSelectedCard = document.querySelector('.list-card-select:checked');
            if (firstSelectedCard) {
                currentStatus = firstSelectedCard.dataset.status;
            }
        }
        
        if (selectedCards.length === 0) {
            showToast('Selecione pelo menos um chamado', 'warning');
            return;
        }
        
        document.getElementById('bulkSelectedCards').value = selectedCards.join(',');
        document.getElementById('bulkCurrentStatus').value = currentStatus || '';
        
        const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
        modal.show();
    });
}
// Mostra/oculta campos baseado no tipo de ação
const bulkActionType = document.getElementById('bulkActionType');
if (bulkActionType) {
    bulkActionType.addEventListener('change', function() {
        const actionType = this.value;
        
        const bulkStatusContainer = document.getElementById('bulkStatusContainer');
        const bulkSprintContainer = document.getElementById('bulkSprintContainer');
        const bulkReleaseContainer = document.getElementById('bulkReleaseContainer');
        const bulkResponsibleContainer = document.getElementById('bulkResponsibleContainer');
        
        if (bulkStatusContainer) bulkStatusContainer.style.display = 
            actionType === 'change_status' ? 'block' : 'none';
        if (bulkSprintContainer) bulkSprintContainer.style.display = 
            actionType === 'assign_sprint' ? 'block' : 'none';
        if (bulkReleaseContainer) bulkReleaseContainer.style.display = 
            actionType === 'assign_release' ? 'block' : 'none';
        if (bulkResponsibleContainer) bulkResponsibleContainer.style.display = 
            actionType === 'assign_responsible' ? 'block' : 'none';
    });
}


 // Modifica a função confirmBulkAction para resetar o form após sucesso
const confirmBulkAction = document.getElementById('confirmBulkAction');
if (confirmBulkAction) {
    confirmBulkAction.addEventListener('click', async function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('bulkActionsModal'));
        const form = document.getElementById('bulkActionsForm');
        
        // Verifica se pelo menos um campo foi alterado
        const hasChanges = Array.from(form.elements).some(element => {
            return element.type !== 'hidden' && element.value !== '';
        });
        
        if (!hasChanges) {
            showToast('Nenhuma alteração foi especificada', 'warning');
            return;
        }

        // Verifica qual visualização está ativa
        const isKanbanView = document.querySelector('.kanban-board') !== null;
        const isListView = document.querySelector('.table') !== null;
        
        let selectedCards = [];
        
        if (isKanbanView) {
            selectedCards = Array.from(document.querySelectorAll('.kanban-card-select:checked'))
                .map(checkbox => checkbox.value);
        } else if (isListView) {
            selectedCards = Array.from(document.querySelectorAll('.list-card-select:checked'))
                .map(checkbox => checkbox.value);
        }
        
        if (selectedCards.length === 0) {
            showToast('Selecione pelo menos um chamado', 'warning');
            return;
        }
        
        document.getElementById('bulkSelectedCards').value = selectedCards.join(',');
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'bulk_update');
            
            const response = await fetch('api/chamados.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (isKanbanView) {
                    // Identifica quais colunas precisam ser atualizadas
                    const newStatus = form.new_status.value;
                    const columnsToUpdate = new Set();
                    
                    // Identifica todas as colunas de origem dos cards selecionados
                    const selectedCheckboxes = document.querySelectorAll('.kanban-card-select:checked');
                    selectedCheckboxes.forEach(checkbox => {
                        const card = checkbox.closest('.kanban-card');
                        const column = card.closest('.kanban-column');
                        const currentStatus = column.dataset.status;
                        if (currentStatus) {
                            columnsToUpdate.add(currentStatus);
                        }
                    });
                    
                    // Se mudou status, atualiza também a nova coluna
                    if (newStatus) {
                        columnsToUpdate.add(newStatus);
                    }
                    
                    // Se não há mudança de status mas há outras alterações (release, sprint, responsável)
                    // atualiza todas as colunas visíveis para refletir as mudanças
                    const hasOtherChanges = form.sprint_id.value || form.release_id.value || form.responsavel_id.value || form.prioridade_id.value || form.pontos_historia.value;
                    if (!newStatus && hasOtherChanges) {
                        // Atualiza todas as colunas visíveis
                        document.querySelectorAll('.kanban-column').forEach(column => {
                            const statusId = column.getAttribute('data-status');
                            if (statusId) {
                                columnsToUpdate.add(statusId);
                            }
                        });
                    }
                    
                    // Atualiza todas as colunas identificadas
                    const updatePromises = Array.from(columnsToUpdate).map(statusId => updateKanbanColumn(statusId));
                    await Promise.all(updatePromises);
                    
                    // Atualiza as estatísticas do dashboard
                    updateDashboardStats();
                    
                    // Desmarca todos os checkboxes
                    document.querySelectorAll('.kanban-card-select:checked').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    
                    // Desmarca os checkboxes "selecionar todos"
                    document.querySelectorAll('.select-all-cards').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                } else if (isListView) {
                    // Na lista, atualiza de forma reativa sem recarregar a página
                    await updateListView();
                    
                    // Desmarca todos os checkboxes
                    document.querySelectorAll('.list-card-select:checked').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    
                    // Desmarca o checkbox "selecionar todos"
                    const selectAllCheckbox = document.querySelector('#selectAllCards');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    }
                }
                
                showToast('Alterações aplicadas com sucesso!', 'success');
                modal.hide();
                
                // Reseta o formulário após o sucesso
                resetBulkActionsForm();
            } else {
                throw new Error(data.message || 'Erro ao realizar ação');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast('Erro: ' + error.message, 'danger');
        }
    });
}

  // Event listener de scroll removido - agora usando paginação

// Adiciona event listener para quando o modal é mostrado
document.getElementById('bulkActionsModal')?.addEventListener('show.bs.modal', function() {
    resetBulkActionsForm();
});

// Função para resetar o formulário de ações em massa
function resetBulkActionsForm() {
    const form = document.getElementById('bulkActionsForm');
    if (form) {
        form.reset();
        document.getElementById('bulkSelectedCards').value = '';
        document.getElementById('bulkCurrentStatus').value = '';
    }
    
    // Atualiza o contador de selecionados
    updateSelectedCounter();
}

// Adiciona event listener para quando o modal é fechado
document.getElementById('bulkActionsModal')?.addEventListener('hidden.bs.modal', function() {
    resetBulkActionsForm();
});
// Função para atualizar o contador de itens selecionados
function updateSelectedCounter() {
    let selectedCount = 0;
    
    // Verifica qual visualização está ativa
    const isKanbanView = document.querySelector('.kanban-board') !== null;
    const isListView = document.querySelector('.table') !== null;
    
    if (isKanbanView) {
        selectedCount = document.querySelectorAll('.kanban-card-select:checked').length;
    } else if (isListView) {
        selectedCount = document.querySelectorAll('.list-card-select:checked').length;
    }
    
    // Atualiza o contador
    const counter = document.getElementById('selectedItemsCounter');
    if (counter) {
        counter.textContent = selectedCount;
        
        // Mostra/oculta o badge baseado no contador
        if (selectedCount > 0) {
            counter.style.display = 'inline-block';
        } else {
            counter.style.display = 'none';
        }
    }
}

// Adiciona listeners para os checkboxes
function setupSelectionListeners() {
    // Para visualização kanban
    document.querySelectorAll('.kanban-card-select').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCounter);
    });
    
    // Para visualização lista
    document.querySelectorAll('.list-card-select').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCounter);
    });
    
    // Para os checkboxes "selecionar todos"
    document.querySelectorAll('.select-all-cards, .select-all-list').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Dá um pequeno delay para garantir que os checkboxes tenham sido atualizados
            setTimeout(updateSelectedCounter, 50);
        });
    });
}

// Inicializa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    setupSelectionListeners();
    updateSelectedCounter(); // Inicializa o contador
    initKanbanNavigation(); // Inicializa navegação do Kanban
});

    // Função para mostrar notificação
    function showToast(message, type = 'success') {
        const toastContainer = document.createElement('div');
        toastContainer.className = `toast align-items-center text-white bg-${type} border-0 position-fixed top-0 end-0 m-3`;
        toastContainer.style.zIndex = '1100';
        toastContainer.style.marginTop = '80px'; // Espaço para não sobrepor o header
        toastContainer.setAttribute('role', 'alert');
        toastContainer.setAttribute('aria-live', 'assertive');
        toastContainer.setAttribute('aria-atomic', 'true');
        
        toastContainer.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        document.body.appendChild(toastContainer);
        const toast = new bootstrap.Toast(toastContainer);
        toast.show();
        
        // Remove o toast após algum tempo
        setTimeout(() => {
            toastContainer.remove();
        }, 5000);
    }
    // Definir filtro como padrão
document.querySelectorAll('.set-default-filter').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const filtroId = this.dataset.id;
        
        try {
            const formData = new FormData();
            formData.append('filtro_id', filtroId);
            
            const response = await fetch('api/definir_filtro_padrao.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                // Recarrega a página para atualizar a lista
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast('Erro ao definir filtro padrão', 'danger');
        }
    });
});
// Editar filtro salvo
document.querySelectorAll('.edit-filter').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const filtroId = this.dataset.id;
        const filtroNome = this.dataset.nome;
        const filtroCompartilhado = this.dataset.compartilhado === '1';
        const filtroPadrao = this.dataset.padrao === '1';
        
        // Preenche o modal com os dados do filtro
        document.getElementById('filtroModalTitle').textContent = 'Editar Filtro';
        document.getElementById('filtroId').value = filtroId;
        document.getElementById('nomeFiltro').value = filtroNome;
        document.getElementById('compartilharFiltro').checked = filtroCompartilhado;
        document.getElementById('filtroPadrao').checked = filtroPadrao;
        
        // Abre o modal
        const modal = new bootstrap.Modal(document.getElementById('salvarFiltroModal'));
        modal.show();
    });
});

// Resetar o modal quando fechado
document.getElementById('salvarFiltroModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('filtroModalTitle').textContent = 'Salvar Filtro';
    document.getElementById('filtroId').value = '';
    document.getElementById('nomeFiltro').value = '';
    document.getElementById('compartilharFiltro').checked = false;
    document.getElementById('filtroPadrao').checked = false;
});

function updateFilterTooltips() {
    // Destrói todos os tooltips existentes
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (tooltip) tooltip.dispose();
    });
    
    // Cria novos tooltips
    const newTooltips = tooltipTriggerList.map(tooltipTriggerEl => {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'bottom',
            html: true
        });
    });
}
// Função para marcar/desmarcar todos os responsáveis
function toggleAllResponsaveis(source) {
    const checkboxes = document.querySelectorAll('.responsavel-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    updateResponsavelDropdownText();
}

// Função para atualizar o texto do botão dropdown
function updateResponsavelDropdownText() {
    const checkboxes = document.querySelectorAll('.responsavel-checkbox:checked');
    const dropdownBtn = document.getElementById('responsavelDropdown');
    
    if (checkboxes.length === 0) {
        dropdownBtn.textContent = 'Todos';
    } else {
        // Verifica se apenas "Sem responsável" está selecionado
        const semResponsavel = document.getElementById('responsavel-0').checked;
        const outrosSelecionados = checkboxes.length > (semResponsavel ? 1 : 0);
        
        if (semResponsavel && !outrosSelecionados) {
            dropdownBtn.textContent = 'Sem responsável';
        } else {
            dropdownBtn.textContent = checkboxes.length + ' selecionados';
        }
    }
}

// Atualiza o texto do dropdown quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    updateResponsavelDropdownText();
    
    // Atualiza o texto quando um checkbox é alterado
    document.querySelectorAll('.responsavel-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateResponsavelDropdownText);
    });
});
</script>
<!-- Fancybox JS -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

<!-- Plyr JS para vídeos -->
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js"></script>

<!-- Inicialização dos plugins -->
<script>
// Inicializa Fancybox
Fancybox.bind("[data-fancybox]", {
    // Opções do Fancybox
    Thumbs: {
        type: "classic",
    },
    Toolbar: {
        display: {
            left: [],
            middle: [],
            right: ["close"],
        },
    },
});

// Inicializa Plyr para todos os players de vídeo
document.addEventListener('DOMContentLoaded', () => {
    const players = Plyr.setup('.js-plyr');
});
</script>
<script>
// Função para inicializar os event listeners dos selects de pontos
function initPontosHistoriaListeners(container = document) {
    container.querySelectorAll('.pontos-historia-select').forEach(select => {
        // Remove event listeners existentes para evitar duplicação
        select.removeEventListener('change', handlePontosChange);
        select.addEventListener('change', handlePontosChange);
        
        // Armazena o valor original para possível reversão
        select.dataset.originalValue = select.value;
    });
}

// Handler para mudança de pontos
async function handlePontosChange() {
    const chamadoId = this.dataset.chamadoId;
    const pontos = this.value;
    
    try {
        const formData = new FormData();
        formData.append('action', 'update_pontos');
        formData.append('id', chamadoId);
        formData.append('pontos', pontos);
        
        const response = await fetch('api/chamados.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Falha na atualização');
        }
        
        showToast('Pontos atualizados com sucesso!', 'success');
        
        // Atualiza os totais da coluna
        const column = this.closest('.kanban-column');
        if (column) {
            const statusId = column.dataset.status;
            updateColumnStats(statusId);
        }
        
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao atualizar pontos', 'danger');
        // Reverte a seleção
        this.value = this.dataset.originalValue;
    }
}

// Inicializa os event listeners na carga da página
initPontosHistoriaListeners();
// Adicione este código na seção de scripts

// Salvar filtro - sucesso
document.getElementById('formSalvarFiltro')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('salvarFiltroModal'));
            modal.hide();
            
            // Recarrega a página para atualizar a lista de filtros salvos
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'danger');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao salvar filtro', 'danger');
    }
});

// Ao carregar a página, verifique se há um filtro salvo na URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const filtroSalvo = urlParams.get('filtro_salvo');
    
    if (filtroSalvo) {
        showToast(`Filtro "${filtroSalvo}" aplicado com sucesso`, 'success');
    }
});
// Adicione este código na seção de scripts

// Excluir filtro salvo
document.querySelectorAll('.delete-filter').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const filtroId = this.dataset.id;
        
        if (!confirm('Tem certeza que deseja excluir este filtro salvo?')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('filtro_id', filtroId);
            
            const response = await fetch('api/excluir_filtro.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                // Remove o item da lista visualmente
                this.closest('li').remove();
                
                // Se não houver mais filtros, recarrega a página
                if (document.querySelectorAll('.dropdown-menu li').length <= 2) {
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast('Erro ao excluir filtro', 'danger');
        }
    });
});

// Inicializa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    updateFilterTooltips();
    
    // Atualiza os tooltips quando o modal de filtros é fechado
    document.getElementById('salvarFiltroModal')?.addEventListener('hidden.bs.modal', function() {
        setTimeout(updateFilterTooltips, 300);
    });
});


// Configura o filtro para o campo de cliente
// Função para configurar o filtro de cliente
function setupClienteFilter() {
    const input = document.getElementById('cliente_filter');
    const options = document.getElementById('cliente_options');
    const select = document.getElementById('cliente');

    if (!input || !options || !select) return;

    // Configuração inicial do input
    input.style.textIndent = '0';
    input.style.paddingLeft = '12px';
    input.style.width = '100%';

    // Fecha o dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !options.contains(e.target)) {
            options.style.display = 'none';
        }
    });

    input.addEventListener('input', function() {
        const filter = input.value.toUpperCase();
        const divs = options.getElementsByTagName('div');
        let hasMatches = false;

        for (let i = 0; i < divs.length; i++) {
            const div = divs[i];
            if (div.classList.contains('no-results')) continue;
            
            const text = div.textContent.toUpperCase();
            if (text.includes(filter)) {
                div.style.display = '';
                hasMatches = true;
            } else {
                div.style.display = 'none';
            }
        }

        // Mostra "Nenhum resultado" se não houver matches
        const existingNoResults = options.querySelector('.no-results');
        if (existingNoResults) {
            existingNoResults.remove();
        }

        if (!hasMatches && filter.length > 0) {
            const noResults = document.createElement('div');
            noResults.textContent = 'Nenhum resultado encontrado';
            noResults.classList.add('no-results');
            options.appendChild(noResults);
        }

        options.style.display = 'block';
    });

    input.addEventListener('focus', function() {
        options.style.display = 'block';
        this.select();
        this.style.textIndent = '0';
        this.style.paddingLeft = '12px';
    });

    options.addEventListener('click', function(e) {
        if (e.target.tagName === 'DIV' && !e.target.classList.contains('no-results')) {
            input.value = e.target.textContent.trim();
            select.value = e.target.getAttribute('data-value');
            options.style.display = 'none';
            
            // Garante o alinhamento após seleção
            input.style.textIndent = '0';
            input.style.paddingLeft = '12px';
            
            const event = new Event('change');
            select.dispatchEvent(event);
        }
    });

    // Inicializa com o valor selecionado
    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        input.value = selectedOption.textContent.trim();
        input.style.textIndent = '0';
        input.style.paddingLeft = '12px';
    }
}

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    setupClienteFilter();
    
    // Observa mudanças no select para atualizar o input
    const select = document.getElementById('cliente');
    if (select) {
        select.addEventListener('change', function() {
            const input = document.getElementById('cliente_filter');
            if (input && this.value) {
                const selectedOption = this.options[this.selectedIndex];
                input.value = selectedOption.textContent.trim();
                input.style.textIndent = '0';
                input.style.paddingLeft = '12px';
            }
        });
    }
});

// Função para gerenciar containers recolhíveis
// Atualize a função setupCollapsibleContainers para:
function setupCollapsibleContainers() {
    // Inicializa os containers com o estado salvo no localStorage
    document.querySelectorAll('.collapsible-container').forEach(container => {
        const containerId = container.id;
        const isCollapsed = localStorage.getItem(`container_${containerId}`) === 'true';
        
        const header = container.querySelector('.collapsible-header');
        const content = container.querySelector('.collapsible-content');
        const icon = container.querySelector('.collapsible-icon');
        
        if (isCollapsed) {
            content.classList.add('collapsed');
            icon.classList.add('collapsed');
        }
        
        header.addEventListener('click', (e) => {
            // Verifica se o clique foi em um elemento que deve ignorar o recolhimento
            const ignoreElements = [
                '.dropdown-toggle', 
                '[data-bs-toggle="modal"]',
                '.dropdown-menu',
                '.change-status'
            ];
            
            let shouldIgnore = false;
            ignoreElements.forEach(selector => {
                if (e.target.closest(selector)) {
                    shouldIgnore = true;
                }
            });
            
            if (!shouldIgnore) {
                content.classList.toggle('collapsed');
                icon.classList.toggle('collapsed');
                
                // Salva o estado no localStorage
                localStorage.setItem(`container_${containerId}`, content.classList.contains('collapsed'));
            }
        });
    });
}

// Inicializa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    setupCollapsibleContainers();
});


</script>
<script>
// Função para mostrar o loading
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';
    setTimeout(() => overlay.classList.add('show'), 10);
}

// Função para esconder o loading
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.remove('show');
    setTimeout(() => overlay.style.display = 'none', 300);
}

// Adiciona eventos de submit aos formulários
document.addEventListener('DOMContentLoaded', function() {
    // Para editar.php
    if (document.getElementById('formChamado')) {
        document.getElementById('formChamado').addEventListener('submit', function() {
            showLoading();
        });
    }
    
    // Para visualizar.php
    if (document.getElementById('formComentario')) {
        document.getElementById('formComentario').addEventListener('submit', function() {
            showLoading();
        });
    }
    
    // Para os formulários de anexos
    const fileForms = document.querySelectorAll('form[enctype="multipart/form-data"]');
    fileForms.forEach(form => {
        form.addEventListener('submit', function() {
            showLoading();
        });
    });
    
    // Para os formulários de clientes e marcadores
    const actionForms = document.querySelectorAll('form[method="POST"]:not([enctype])');
    actionForms.forEach(form => {
        form.addEventListener('submit', function() {
            showLoading();
        });
    });
    
    // Para os formulários modais
    const modalForms = document.querySelectorAll('.modal form');
    modalForms.forEach(form => {
        form.addEventListener('submit', function() {
            showLoading();
        });
    });
});
// Variável global para armazenar o HTML gerado
let htmlGerado = '';

// Função para gerar a versão HTML
document.getElementById('gerarVersaoBtn')?.addEventListener('click', function() {
    // Verifica se há uma release filtrada
    const releaseId = new URLSearchParams(window.location.search).get('release');
    if (!releaseId) {
        showToast('Filtre por uma release antes de gerar a versão', 'warning');
        return;
    }

    // Busca os dados da release
    fetch(`api/get_release.php?id=${releaseId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Erro ao buscar dados da release');
            }

            const release = data.release;
            const chamados = data.chamados;

            // Cria a estrutura HTML
            htmlGerado = `<!-- Atualizações -->
<section class="updates">
    <h2>Versão - ${release.nome}</h2>`;

            // Adiciona os chamados
            chamados.forEach(chamado => {
                htmlGerado += `
    <div class="update-item">
        <h3>• ${chamado.tipo_nome} - ${chamado.titulo}</h3>
    </div>`;
            });

            // Adiciona o aprimoramento diverso padrão
            htmlGerado += `
    <div class="update-item">
        <h3>• Aprimoramentos Diversos - Alterações no sistema para melhorar o desempenho</h3>
    </div>
</section>`;

            // Exibe a visualização
            document.getElementById('versaoContainer').innerHTML = htmlGerado;
            
            // Mostra o modal
            const modal = new bootstrap.Modal(document.getElementById('versaoModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro ao gerar versão: ' + error.message, 'danger');
        });
});

// Função para copiar a versão gerada
function copiarVersao() {
    if (!htmlGerado) {
        showToast('Nada para copiar', 'warning');
        return;
    }
    
    navigator.clipboard.writeText(htmlGerado)
        .then(() => {
            showToast('HTML copiado para a área de transferência!', 'success');
        })
        .catch(err => {
            console.error('Erro ao copiar: ', err);
            showToast('Erro ao copiar HTML', 'danger');
        });
}

// Inicializa tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
        placement: 'bottom',
        trigger: 'hover'
    });
});
</script>
<script>
// Função para o filtro de marcadores
document.addEventListener('DOMContentLoaded', function() {
    const marcadorFilter = document.getElementById('marcador_filter');
    const marcadorOptions = document.getElementById('marcador_options');
    const marcadorSelect = document.getElementById('marcador');
    
    if (marcadorFilter && marcadorOptions) {
        // Função para ajustar posicionamento em telas pequenas
        function adjustOptionsPosition() {
            if (window.innerWidth <= 768) {
                marcadorOptions.style.position = 'fixed';
                marcadorOptions.style.top = '50%';
                marcadorOptions.style.left = '50%';
                marcadorOptions.style.transform = 'translate(-50%, -50%)';
                marcadorOptions.style.width = '90%';
                marcadorOptions.style.maxWidth = '300px';
                marcadorOptions.style.maxHeight = '60vh';
                marcadorOptions.style.zIndex = '9999';
            } else {
                marcadorOptions.style.position = 'absolute';
                marcadorOptions.style.top = '';
                marcadorOptions.style.left = '';
                marcadorOptions.style.transform = '';
                marcadorOptions.style.width = '';
                marcadorOptions.style.maxWidth = '';
                marcadorOptions.style.maxHeight = '200px';
                marcadorOptions.style.zIndex = '1000';
            }
        }
        
        // Ajustar inicialmente
        adjustOptionsPosition();
        
        // Reajustar quando a janela for redimensionada
        window.addEventListener('resize', adjustOptionsPosition);
        
        marcadorFilter.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const options = marcadorOptions.querySelectorAll('div[data-value]');
            let hasResults = false;
            
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(filter)) {
                    option.style.display = 'flex';
                    hasResults = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Mostrar mensagem se não houver resultados
            let noResults = marcadorOptions.querySelector('.no-results');
            if (!hasResults) {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = 'Nenhum marcador encontrado';
                    marcadorOptions.appendChild(noResults);
                }
                noResults.style.display = 'block';
            } else if (noResults) {
                noResults.style.display = 'none';
            }
            
            marcadorOptions.style.display = 'block';
        });
        
        // Mostrar opções ao focar
        marcadorFilter.addEventListener('focus', function() {
            adjustOptionsPosition();
            marcadorOptions.style.display = 'block';
            // Focar no primeiro item
            const firstOption = marcadorOptions.querySelector('div[data-value]:not([style*="display: none"])');
            if (firstOption) {
                firstOption.scrollIntoView({ block: 'nearest' });
            }
        });
        
        // Esconder opções ao clicar fora
        document.addEventListener('click', function(e) {
            if (!marcadorFilter.contains(e.target) && !marcadorOptions.contains(e.target)) {
                marcadorOptions.style.display = 'none';
            }
        });
        
        // Navegação com teclado
        marcadorFilter.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const visibleOptions = Array.from(marcadorOptions.querySelectorAll('div[data-value]:not([style*="display: none"])'));
                
                if (visibleOptions.length > 0) {
                    let currentIndex = -1;
                    
                    // Encontrar opção atualmente focada
                    visibleOptions.forEach((option, index) => {
                        if (option.classList.contains('focused')) {
                            currentIndex = index;
                        }
                    });
                    
                    if (e.key === 'ArrowDown') {
                        currentIndex = (currentIndex + 1) % visibleOptions.length;
                    } else if (e.key === 'ArrowUp') {
                        currentIndex = (currentIndex - 1 + visibleOptions.length) % visibleOptions.length;
                    }
                    
                    // Remover foco anterior e aplicar novo
                    visibleOptions.forEach(option => option.classList.remove('focused'));
                    visibleOptions[currentIndex].classList.add('focused');
                    visibleOptions[currentIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const focusedOption = marcadorOptions.querySelector('div.focused');
                if (focusedOption) {
                    focusedOption.click();
                }
            }
        });
        
        // Selecionar opção ao clicar
        const options = marcadorOptions.querySelectorAll('div[data-value]');
        options.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const text = this.textContent;
                
                marcadorSelect.value = value;
                marcadorFilter.value = text.trim();
                marcadorOptions.style.display = 'none';
                
                // Remover classe focused
                options.forEach(opt => opt.classList.remove('focused'));
            });
            
            // Efeito hover
            option.addEventListener('mouseenter', function() {
                options.forEach(opt => opt.classList.remove('focused'));
                this.classList.add('focused');
            });
        });
        
        // Preencher o campo de texto se já houver um valor selecionado
        if (marcadorSelect.value) {
            const selectedOption = marcadorOptions.querySelector(`div[data-value="${marcadorSelect.value}"]`);
            if (selectedOption) {
                marcadorFilter.value = selectedOption.textContent.trim();
            }
        }
    }
});
</script>


<!-- Loading Overlay Moderno -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>Aguarde, salvando as informações<span class="dots"><span>.</span><span>.</span><span>.</span></span></p>
    </div>
</div>
</body>
</html>