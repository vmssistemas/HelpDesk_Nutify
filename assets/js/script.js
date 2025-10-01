let currentVideoIndex = 0; // Índice do vídeo atual
let videos = []; // Array para armazenar os vídeos

// Adicione estas linhas no início do seu script.js ou em um arquivo separado

// Função para mostrar notificações visuais na interface
function showVisualNotification(message, type = 'info') {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="notification-message">${message}</span>
        <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Adicionar estilos inline se não existirem
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                font-weight: bold;
                z-index: 10000;
                max-width: 300px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                animation: slideIn 0.3s ease-out;
            }
            .notification-success {
                background-color: #28a745;
            }
            .notification-error {
                background-color: #dc3545;
            }
            .notification-info {
                background-color: #17a2b8;
            }
            .notification-close {
                background: none;
                border: none;
                color: white;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                float: right;
                margin-left: 10px;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Adicionar ao body
    document.body.appendChild(notification);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Alias para compatibilidade
function showNotification(message, type = 'info') {
    showVisualNotification(message, type);
}

// Configuração do FullCalendar
document.addEventListener('DOMContentLoaded', function() {
    // Botão da agenda
    const agendaButton = document.getElementById('agendaButton');
    const agendaModal = document.getElementById('agendaModal');
    
    if (agendaButton && agendaModal) {
        agendaButton.addEventListener('click', function() {
            agendaModal.style.display = 'block';
            // Inicializa o calendário quando o modal é aberto
            setTimeout(() => {
                initCalendar();
                // Forçar re-renderização após o modal estar visível
                if (window.calendar) {
                    setTimeout(() => {
                        window.calendar.updateSize();
                        window.calendar.render();
                    }, 100);
                }
            }, 50);
        });
        
        // Fechar modal quando clicar no X
        agendaModal.querySelector('.close').addEventListener('click', function() {
            agendaModal.style.display = 'none';
        });
    }
    
    // Fechar modal quando clicar fora (com verificação para evitar fechamento durante seleção de texto)
    window.addEventListener('click', function(event) {
        if (event.target === agendaModal && !window.getSelection().toString()) {
            agendaModal.style.display = 'none';
        }
    });
    
    // Configurar o filtro de cliente na agenda
    setupAgendaClienteFilter();
    
    // Iniciar o sistema de atualizações em tempo real
    startRealTimeUpdates();
    
    // Solicitar permissão para notificações
    if ('Notification' in window) {
        if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    }
});

// Armazenar todos os eventos originais
let allEvents = [];
// Armazenar filtros de usuários ativos
let activeUserFilters = {};

// Funções para gerenciar o estado dos checkboxes de usuários
function saveUserFiltersState() {
    try {
        localStorage.setItem('agendaUserFilters', JSON.stringify(activeUserFilters));
    } catch (error) {
        console.error('Erro ao salvar estado dos filtros de usuários:', error);
    }
}

function loadUserFiltersState() {
    try {
        const savedFilters = localStorage.getItem('agendaUserFilters');
        if (savedFilters) {
            return JSON.parse(savedFilters);
        }
    } catch (error) {
        console.error('Erro ao carregar estado dos filtros de usuários:', error);
    }
    return {};
}

function initCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || calendarEl._fullCalendar) return; // Evitar inicialização múltipla
    
    // Carregar estado salvo dos filtros de usuários
    const savedFilters = loadUserFiltersState();
    
    // Inicializar todos os usuários com base no estado salvo ou como ativos por padrão
    document.querySelectorAll('.user-filter-checkbox').forEach(checkbox => {
        const userName = checkbox.dataset.user;
        const userColor = checkbox.dataset.color;
        
        // Verificar se há estado salvo para este usuário, senão usar o estado atual do checkbox
        const isChecked = savedFilters.hasOwnProperty(userName) ? savedFilters[userName] : checkbox.checked;
        
        // Aplicar o estado ao checkbox
        checkbox.checked = isChecked;
        activeUserFilters[userName] = isChecked;
        
        // Aplicar a cor do usuário ao checkbox
        checkbox.style.borderColor = userColor;
        
        // Se o checkbox estiver marcado, aplicar a cor de fundo
        if (checkbox.checked) {
            checkbox.style.backgroundColor = userColor;
        } else {
            checkbox.style.backgroundColor = 'white';
        }
        
        // Adicionar evento de mudança para cada checkbox
        checkbox.addEventListener('change', function() {
            activeUserFilters[userName] = this.checked;
            
            // Aplicar ou remover a cor de fundo baseado no estado
            if (this.checked) {
                this.style.backgroundColor = userColor;
            } else {
                this.style.backgroundColor = 'white';
            }
            
            // Salvar o estado atualizado
            saveUserFiltersState();
            
            applyUserFilters();
        });
    });
    
    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek', // Alterado de 'dayGridMonth' para 'timeGridWeek'
        timeZone: 'local', // Mudança para usar timezone local do navegador
        locale: 'pt-br',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia'
        },
        // Configuração para ordenar eventos por usuário alfabeticamente
        eventOrder: function(a, b) {
            // Primeiro, ordenar por nome do usuário alfabeticamente
            const userA = a.extendedProps?.usuario_nome || '';
            const userB = b.extendedProps?.usuario_nome || '';
            
            if (userA < userB) return -1;
            if (userA > userB) return 1;
            
            // Se os usuários são iguais, ordenar por horário de início
            if (a.start < b.start) return -1;
            if (a.start > b.start) return 1;
            
            return 0;
        },
        eventOrderStrict: true, // Garantir que a ordenação seja rigorosamente seguida
        // Configurações de horário para visualização semanal e diária
        slotMinTime: '08:00:00', // Horário mínimo: 8:00
        slotMaxTime: '19:00:00', // Horário máximo: 19:00
        slotDuration: '00:30:00', // Intervalos de 30 minutos
        slotLabelInterval: '01:00:00', // Labels de hora em hora
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        editable: false, // Desabilitado para evitar bugs de arrastar
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        navLinks: true,
        eventStartEditable: false, // Desabilita arrastar eventos
        eventDurationEditable: false, // Desabilita redimensionar eventos
        eventTimeFormat: { 
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            // Verificar se já existe uma requisição em andamento para evitar duplicações
            if (window.fetchingEvents) {
                return;
            }
            
            window.fetchingEvents = true;
            
            fetch('../paginas/agenda_ajax.php?action=fetch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `start=${fetchInfo.startStr}&end=${fetchInfo.endStr}&csrf_token=${document.getElementById('csrf_token').value}`
            })
            .then(response => response.json())
            .then(data => {
                // Armazenar todos os eventos originais
                allEvents = data;
                // Aplicar filtros de usuário
                const filteredEvents = filterEventsByUser(data);
                successCallback(filteredEvents);
            })
            .catch(error => {
                failureCallback(error);
                console.error('Erro ao carregar eventos:', error);
            })
            .finally(() => {
                window.fetchingEvents = false;
            });
        },
        dateClick: function(info) {
            openEventModal(null, info.dateStr);
        },
        eventClick: function(info) {
            openEventModal(info.event);
        },
        eventDidMount: function(info) {
            // Adicionar evento de clique direito
            info.el.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showContextMenu(e, info.event);
            });
            
            // Remover classes de status anteriores
            info.el.classList.remove('evento-concluido', 'evento-nao-concluido', 'evento-pendente');
            
            // Adicionar classe baseada no status
            const concluido = info.event.extendedProps.concluido;
            
            if (concluido == 1) {
                // Evento concluído - classe para borda verde
                info.el.classList.add('evento-concluido');
                info.el.title = 'Concluído';
            } else if (concluido == 2) {
                // Evento não concluído - classe para borda vermelha
                info.el.classList.add('evento-nao-concluido');
                info.el.title = 'Não Concluído';
            } else {
                // Evento pendente - classe para borda cinza
                info.el.classList.add('evento-pendente');
                info.el.title = 'Pendente';
            }
            
            // Garantir que o evento não seja arrastável
            info.el.style.cursor = 'pointer';
        },
        select: function(info) {
            openEventModal(null, info.startStr, info.endStr);
        },
        // Callback para quando a visualização muda
        viewDidMount: function(info) {
            // Adicionar linha do horário atual após mudança de visualização
            setTimeout(() => {
                addCurrentTimeLine();
                
                // Verificar se realmente mudou o tipo de visualização
                const currentViewType = info.view.type;
                const previousViewType = window.lastViewType || null;
                
                // Aplicar filtros apenas se mudou o tipo de visualização
                // Não aplicar na primeira carga (previousViewType é null) para evitar duplicação
                // pois o FullCalendar já carrega os eventos via events function
                if (previousViewType !== null && previousViewType !== currentViewType) {
                    applyAllFilters();
                }
                
                // Armazenar o tipo de visualização atual
                window.lastViewType = currentViewType;
            }, 100);
        },
        // Callback para quando a data muda (navegação)
        datesSet: function(dateInfo) {
            // Recriar o indicador de hora atual quando navegar entre datas
            setTimeout(() => {
                addCurrentTimeLine();
            }, 150);
        },
        // Funções de arrastar e redimensionar removidas para evitar bugs
    });
    
    // Renderizar o calendário com um pequeno delay para evitar bugs visuais
    setTimeout(() => {
        window.calendar.render();
        // Forçar uma nova renderização após um momento para garantir layout correto
        setTimeout(() => {
            window.calendar.updateSize();
            // Adicionar linha do horário atual após renderização
            addCurrentTimeLine();
        }, 100);
    }, 50);
    
    calendarEl._fullCalendar = window.calendar; // Marcar como inicializado
}

// Função para adicionar linha do horário atual
function addCurrentTimeLine() {
    // Remover linha existente se houver
    const existingLine = document.querySelector('.current-time-line');
    if (existingLine) {
        existingLine.remove();
    }
    
    // Verificar se estamos na visualização semanal ou diária
    const currentView = window.calendar.view.type;
    if (currentView !== 'timeGridWeek' && currentView !== 'timeGridDay') {
        return; // Não mostrar linha na visualização mensal
    }
    
    // Obter container do calendário
    const calendarEl = document.querySelector('.fc-timegrid-body');
    if (!calendarEl) return;
    
    // Criar elemento da linha
    const timeLine = document.createElement('div');
    timeLine.className = 'current-time-line';
    
    // Criar indicador circular
    const timeIndicator = document.createElement('div');
    timeIndicator.className = 'current-time-indicator';
    timeLine.appendChild(timeIndicator);
    
    // Calcular posição baseada no horário atual
    updateTimeLinePosition(timeLine);
    
    // Adicionar ao container
    calendarEl.appendChild(timeLine);
    
    // Configurar atualização automática
    startTimeLineUpdates();
}

// Função para calcular e atualizar posição da linha
function updateTimeLinePosition(timeLine) {
    if (!timeLine) {
        timeLine = document.querySelector('.current-time-line');
        if (!timeLine) return;
    }
    
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    
    // Verificar se está dentro do horário de exibição (08:00 - 19:00)
    const minHour = 8;
    const maxHour = 19;
    
    if (currentHour < minHour || currentHour >= maxHour) {
        timeLine.style.display = 'none';
        return;
    }
    
    // Verificar se estamos no dia atual - melhorada a lógica
    const today = new Date();
    const todayDateString = today.toISOString().split('T')[0]; // YYYY-MM-DD
    
    // Obter a data atual sendo visualizada no calendário
    const currentView = window.calendar.view;
    const viewType = currentView.type;
    
    // Para visualização diária, verificar se a data visualizada é hoje
    if (viewType === 'timeGridDay') {
        const viewDate = new Date(currentView.currentStart);
        const viewDateString = viewDate.toISOString().split('T')[0];
        
        if (viewDateString !== todayDateString) {
            timeLine.style.display = 'none';
            return;
        }
    } else {
        // Para visualização semanal, verificar se hoje está na semana atual
        const viewStart = new Date(currentView.activeStart);
        const viewEnd = new Date(currentView.activeEnd);
        
        if (today < viewStart || today >= viewEnd) {
            timeLine.style.display = 'none';
            return;
        }
    }
    
    timeLine.style.display = 'block';
    
    // Encontrar a coluna do dia atual ou usar toda a largura na visualização diária
    const containerRect = document.querySelector('.fc-timegrid-body').getBoundingClientRect();
    
    if (viewType === 'timeGridDay') {
        // Na visualização diária, ocupar toda a largura disponível
        timeLine.style.left = '0px';
        timeLine.style.width = '100%';
        timeLine.style.right = 'auto';
    } else {
        // Na visualização semanal, encontrar a coluna do dia atual
        const todayColumn = findTodayColumn();
        if (!todayColumn) {
            timeLine.style.display = 'none';
            return;
        }
        
        const columnRect = todayColumn.getBoundingClientRect();
        const leftPosition = columnRect.left - containerRect.left;
        const lineWidth = columnRect.width;
        
        timeLine.style.left = leftPosition + 'px';
        timeLine.style.width = lineWidth + 'px';
        timeLine.style.right = 'auto';
    }
    
    // Obter o slot de tempo atual no FullCalendar
    const timeSlots = document.querySelectorAll('.fc-timegrid-slot');
    if (timeSlots.length === 0) return;
    
    // Encontrar o slot correspondente ao horário atual
    let targetSlot = null;
    for (let slot of timeSlots) {
        const slotTime = slot.getAttribute('data-time');
        if (slotTime) {
            const slotHour = parseInt(slotTime.split(':')[0]);
            const slotMinute = parseInt(slotTime.split(':')[1]);
            
            // Se encontrou o slot exato ou o próximo slot
            if ((slotHour === currentHour && slotMinute <= currentMinute) ||
                (slotHour === currentHour + 1 && currentMinute > 30)) {
                targetSlot = slot;
            }
        }
    }
    
    if (!targetSlot) {
        // Fallback para o método anterior se não encontrar slot
        const totalMinutesInView = (maxHour - minHour) * 60;
        const currentMinutesFromStart = (currentHour - minHour) * 60 + currentMinute;
        
        const timeGrid = document.querySelector('.fc-timegrid-body');
        if (!timeGrid) return;
        
        const containerHeight = timeGrid.offsetHeight;
        const positionPercentage = currentMinutesFromStart / totalMinutesInView;
        const topPosition = containerHeight * positionPercentage;
        
        timeLine.style.top = topPosition + 'px';
    } else {
        // Usar a posição do slot encontrado
        const slotRect = targetSlot.getBoundingClientRect();
        
        // Calcular posição relativa dentro do slot baseada nos minutos
        const slotTime = targetSlot.getAttribute('data-time');
        const slotHour = parseInt(slotTime.split(':')[0]);
        const slotMinute = parseInt(slotTime.split(':')[1]);
        
        const minutesIntoSlot = (currentHour - slotHour) * 60 + (currentMinute - slotMinute);
        const slotHeight = slotRect.height;
        const positionInSlot = (minutesIntoSlot / 30) * slotHeight; // 30 minutos por slot
        
        const topPosition = (slotRect.top - containerRect.top) + positionInSlot;
        timeLine.style.top = topPosition + 'px';
    }
}

// Função auxiliar para encontrar a coluna do dia atual
function findTodayColumn() {
    const today = new Date();
    const todayDateString = today.toISOString().split('T')[0]; // YYYY-MM-DD
    
    // Procurar pela coluna do dia atual
    const dayHeaders = document.querySelectorAll('.fc-col-header-cell');
    for (let header of dayHeaders) {
        const dateAttr = header.getAttribute('data-date');
        if (dateAttr === todayDateString) {
            // Encontrar a coluna correspondente no corpo do calendário
            const columnIndex = Array.from(header.parentNode.children).indexOf(header);
            const bodyColumns = document.querySelectorAll('.fc-timegrid-col');
            return bodyColumns[columnIndex];
        }
    }
    
    // Fallback: procurar diretamente nas colunas do corpo
    const bodyColumns = document.querySelectorAll('.fc-timegrid-col');
    for (let column of bodyColumns) {
        const dateAttr = column.getAttribute('data-date');
        if (dateAttr === todayDateString) {
            return column;
        }
    }
    
    return null;
}

// Variável para controlar o intervalo de atualização
let timeLineInterval = null;

// Função para iniciar atualizações automáticas da linha
function startTimeLineUpdates() {
    // Limpar intervalo existente se houver
    if (timeLineInterval) {
        clearInterval(timeLineInterval);
    }
    
    // Atualizar a cada minuto
    timeLineInterval = setInterval(() => {
        updateTimeLinePosition();
    }, 60000); // 60 segundos
}

// Função para parar atualizações da linha
function stopTimeLineUpdates() {
    if (timeLineInterval) {
        clearInterval(timeLineInterval);
        timeLineInterval = null;
    }
}

// Função para filtrar eventos por usuário
function filterEventsByUser(events) {
    if (!events || events.length === 0) return [];
    
    return events.filter(event => {
        // Se o evento não tem um nome de usuário associado, mostrar sempre
        if (!event.extendedProps || !event.extendedProps.usuario_nome) {
            return true;
        }
        
        // Verificar se o usuário está ativo nos filtros
        return activeUserFilters[event.extendedProps.usuario_nome] === true;
    });
}

// Função para aplicar filtros de usuário aos eventos existentes
function applyUserFilters() {
    // Usar a nova função que aplica todos os filtros
    applyAllFilters();
}

function getCurrentBrasiliaTime() {
    // Retorna a hora local do sistema (que já deve estar configurado para Brasília)
    return new Date();
}

function openEventModal(event, dateStart, dateEnd) {
    const modal = document.getElementById('eventoModal');
    const form = document.getElementById('eventoForm');
    const deleteBtn = document.getElementById('deleteEventBtn');
    const historicoContent = document.getElementById('historicoContent');
    
    // Resetar o formulário
    form.reset();

    if (event) {
        // Modo edição - mantém os dados existentes do evento
        document.getElementById('modalEventTitle').textContent = 'Editar Evento';
        document.getElementById('eventoId').value = event.id;
        document.getElementById('eventoTitulo').value = event.title;
        document.getElementById('eventoDescricao').value = event.extendedProps.descricao || '';
        document.getElementById('eventoInicio').value = formatDateTimeForInput(event.start);
        document.getElementById('eventoFim').value = event.end ? formatDateTimeForInput(event.end) : '';
        document.getElementById('eventoCor').value = event.backgroundColor || '#3788d8';
        document.getElementById('eventoTipo').value = event.extendedProps.tipo || '';
        document.getElementById('eventoUsuario').value = event.extendedProps.usuario_id;
        // Campo de visibilidade removido
        
        // Definir o cliente no select oculto e no campo de filtro
        const clienteId = event.extendedProps.cliente_id;
        document.getElementById('eventoCliente').value = clienteId || '';
        
        // Atualizar o campo de filtro com o nome do cliente
        if (clienteId) {
            const clienteOption = document.querySelector(`#cliente_options div[data-value="${clienteId}"]`);
            if (clienteOption) {
                document.getElementById('cliente_filter').value = clienteOption.textContent;
            }
        } else {
            document.getElementById('cliente_filter').value = '';
        }
        
        deleteBtn.style.display = 'inline-block';
        loadEventHistory(event.id);
    } else {
        // Modo criação - define valores padrão
        document.getElementById('modalEventTitle').textContent = 'Adicionar Evento';
        document.getElementById('eventoId').value = '';
        document.getElementById('historicoContent').innerHTML = '<p>Nenhum histórico disponível para novos eventos.</p>';
        
        // Obtém a hora atual de Brasília
        const now = getCurrentBrasiliaTime();
        const horaAtual = now.getHours();
        
        if (dateStart) {
            // Cria um objeto Date a partir da string (funciona para todas as visualizações)
            let dataSelecionada;
            
            // Se for uma visualização mensal (dateStart é uma string no formato YYYY-MM-DD)
            if (typeof dateStart === 'string' && dateStart.indexOf('T') === -1) {
                // Cria a data no fuso horário local sem conversão
                dataSelecionada = new Date(dateStart + 'T00:00:00');
                
                // Define a hora atual (sem minutos) apenas para visualização mensal
                dataSelecionada.setHours(horaAtual, 0, 0, 0);
            } else {
                // Se já for um objeto Date (visualização diária/semanal) - mantém o horário clicado
                dataSelecionada = new Date(dateStart);
                // Não altera a hora - mantém a hora do slot clicado
            }
            
            document.getElementById('eventoInicio').value = formatDateTimeForInput(dataSelecionada);
            
            // Define o fim como 1 hora depois do início
            const dataFim = new Date(dataSelecionada);
            dataFim.setHours(dataSelecionada.getHours() + 1, 0, 0, 0);
            
            // Se foi selecionado um intervalo (arrastando no calendário)
            if (dateEnd) {
                let dataFinalSelecionada;
                
                // Se for uma string (visualização mensal)
                if (typeof dateEnd === 'string' && dateEnd.indexOf('T') === -1) {
                    // Cria a data no fuso horário local sem conversão
                    dataFinalSelecionada = new Date(dateEnd + 'T00:00:00');
                    
                    // Define a hora 1 hora depois do início
                    dataFinalSelecionada.setHours(horaAtual + 1, 0, 0, 0);
                } else {
                    // Se já for um objeto Date (visualização diária/semanal) - mantém o horário selecionado
                    dataFinalSelecionada = new Date(dateEnd);
                    // Não altera a hora - mantém a hora do slot final selecionado
                }
                
                document.getElementById('eventoFim').value = formatDateTimeForInput(dataFinalSelecionada);
            } else {
                document.getElementById('eventoFim').value = formatDateTimeForInput(dataFim);
            }
        } else {
            // Se não foi selecionada data (abriu o modal sem clicar no calendário)
            // Usa data/hora atual completa
            document.getElementById('eventoInicio').value = formatDateTimeForInput(now);
            
            // Define o fim como 1 hora depois
            const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
            document.getElementById('eventoFim').value = formatDateTimeForInput(oneHourLater);
        }
        
        deleteBtn.style.display = 'none';
    }
    
    // Configura eventos para fechar o modal (apenas uma vez)
    if (!modal.hasAttribute('data-listeners-added')) {
        // Fechar modal quando clicar no X
        modal.querySelector('.close').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Fechar modal quando clicar em Cancelar
        const cancelButton = modal.querySelector('.close-modal');
        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Fechar modal quando clicar fora (com verificação para evitar fechamento durante seleção de texto)
        window.addEventListener('click', function(event) {
            if (event.target === modal && !window.getSelection().toString()) {
                modal.style.display = 'none';
            }
        });
        
        // Prevenir que cliques dentro do conteúdo do modal fechem o modal
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        }
        
        modal.setAttribute('data-listeners-added', 'true');
    }
    
    modal.style.display = 'block';
}

// Função auxiliar para obter hora atual de Brasília
function getCurrentBrasiliaTime() {
    // Retorna o horário local do servidor (assumindo que já está configurado para Brasília)
    return new Date();
}

// Função auxiliar para formatar a data no formato do input datetime-local
function formatDateTimeForInput(date) {
    if (!date) return '';
    
    // Se for uma string, converte para objeto Date
    if (typeof date === 'string') {
        date = new Date(date);
    }
    
    // Formatação simples sem conversão de timezone
    const pad = (num) => String(num).padStart(2, '0');
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    
    const formatted = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    return formatted;
}

function loadEventHistory(eventId) {
    const historicoContent = document.getElementById('historicoContent');
    
    fetch('../paginas/agenda_ajax.php?action=history', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `event_id=${eventId}&csrf_token=${document.getElementById('csrf_token').value}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.history.length > 0) {
            let html = '';
            data.history.forEach(item => {
                html += `
                    <div class="historico-item">
                        <div class="historico-acao">${getActionText(item.acao)}</div>
                        <div class="historico-usuario">Por: ${item.usuario_nome}</div>
                        <div class="historico-data">Em: ${new Date(item.data_acao).toLocaleString()}</div>
                        ${item.dados_anteriores ? `<div class="historico-dados">${formatHistoryData(item.dados_anteriores)}</div>` : ''}
                    </div>
                `;
            });
            historicoContent.innerHTML = html;
        } else {
            historicoContent.innerHTML = '<p>Nenhum histórico encontrado para este evento.</p>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar histórico:', error);
        historicoContent.innerHTML = '<p>Erro ao carregar histórico.</p>';
    });
}

function getActionText(action) {
    const actions = {
        'criado': 'Evento criado',
        'editado': 'Evento editado',
        'excluido': 'Evento excluído'
    };
    return actions[action] || action;
}

function formatHistoryData(data) {
    try {
        const parsed = JSON.parse(data);
        let html = '<ul>';
        for (const key in parsed) {
            html += `<li><strong>${key}:</strong> ${parsed[key]}</li>`;
        }
        html += '</ul>';
        return html;
    } catch (e) {
        return data;
    }
}

// Manipulação do formulário de evento
// Função auxiliar para atualizar o histórico de excluídos se necessário
function updateDeletedEventsIfActive() {
    const deletedEventsTab = document.getElementById('deletedEventsTab');
    if (deletedEventsTab && deletedEventsTab.classList.contains('active')) {
        loadDeletedEvents(currentPage);
    }
}

document.getElementById('eventoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        id: document.getElementById('eventoId').value,
        titulo: document.getElementById('eventoTitulo').value,
        descricao: document.getElementById('eventoDescricao').value,
        inicio: document.getElementById('eventoInicio').value,
        fim: document.getElementById('eventoFim').value,
        cor: document.getElementById('eventoCor').value,
        tipo: document.getElementById('eventoTipo').value,
        usuario_id: document.getElementById('eventoUsuario').value,
        cliente_id: document.getElementById('eventoCliente').value,
        // visibilidade: 'publico', // Campo removido - sempre público
        csrf_token: document.getElementById('csrf_token').value
    };
    
    // Validação básica no frontend
    if (!formData.titulo.trim()) {
        showNotification('Título é obrigatório', 'error');
        return;
    }
    
    if (!formData.tipo.trim()) {
        showNotification('Tipo é obrigatório', 'error');
        return;
    }
    
    if (!formData.inicio) {
        showNotification('Data e hora de início são obrigatórias', 'error');
        return;
    }
    
    if (!formData.fim) {
        showNotification('Data e hora de fim são obrigatórias', 'error');
        return;
    }
    
    // Validação adicional: verificar se a data/hora de fim é posterior à de início
    if (formData.inicio && formData.fim) {
        const inicioDate = new Date(formData.inicio);
        const fimDate = new Date(formData.fim);
        
        if (fimDate <= inicioDate) {
            showNotification('A data e hora de fim deve ser posterior à data e hora de início', 'error');
            return;
        }
    }
    
    if (!formData.usuario_id) {
        showNotification('Usuário responsável é obrigatório', 'error');
        return;
    }
    
    if (!formData.cliente_id) {
        showNotification('Cliente é obrigatório', 'error');
        return;
    }
    
    // Validação de conflito de horários no frontend (opcional - o backend já valida)
    if (formData.inicio && formData.usuario_id) {
        // Verificar se há eventos conflitantes no calendário atual
        const eventos = window.calendar.getEvents();
        const inicioNovo = new Date(formData.inicio);
        const fimNovo = formData.fim ? new Date(formData.fim) : inicioNovo;
        
        for (let evento of eventos) {
            // Pular o próprio evento se estiver editando
            if (formData.id && evento.id == formData.id) continue;
            
            // Verificar se é do mesmo usuário
            if (evento.extendedProps.usuario_id == formData.usuario_id) {
                const inicioExistente = new Date(evento.start);
                const fimExistente = evento.end ? new Date(evento.end) : inicioExistente;
                
                // Verificar conflito de horários
                if ((inicioNovo >= inicioExistente && inicioNovo < fimExistente) ||
                    (fimNovo > inicioExistente && fimNovo <= fimExistente) ||
                    (inicioNovo <= inicioExistente && fimNovo >= fimExistente) ||
                    (inicioNovo.getTime() === inicioExistente.getTime())) {
                    
                    // Só permite agendamento no mesmo horário se o evento existente estiver marcado como "Não Concluído" (concluido = 2)
                    // Bloqueia se estiver "Pendente" (concluido IS NULL) ou "Concluído" (concluido = 1)
                    if (evento.extendedProps.concluido === null || evento.extendedProps.concluido === 1) {
                        const inicioFormatado = inicioExistente.toLocaleString('pt-BR');
                        const fimFormatado = fimExistente.toLocaleString('pt-BR');
                        const statusTexto = evento.extendedProps.concluido === 1 ? 'concluído' : 'pendente';
                        showNotification(`Conflito de horário detectado! Já existe o agendamento ${statusTexto} "${evento.title}" no período de ${inicioFormatado} às ${fimFormatado}`, 'error');
                        return;
                    }
                }
            }
        }
    }
    
    const action = formData.id ? 'update' : 'create';
    
    fetch(`../paginas/agenda_ajax.php?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData).toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Evento salvo com sucesso!', 'success');
            document.getElementById('eventoModal').style.display = 'none';
            refreshCalendar();
            updateDeletedEventsIfActive();
        } else {
            showNotification('Erro ao salvar evento: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao salvar evento.', 'error');
    });
});

// Função para mostrar menu de contexto
function showContextMenu(event, calendarEvent) {
    // Remover menu existente se houver
    const existingMenu = document.getElementById('contextMenu');
    if (existingMenu) {
        existingMenu.remove();
    }
    
    // Criar menu de contexto
    const menu = document.createElement('div');
    menu.id = 'contextMenu';
    menu.className = 'context-menu';
    
    const concluido = calendarEvent.extendedProps.concluido;
    const eventoTipo = calendarEvent.extendedProps.tipo;
    
    // Verificar se é um evento de treinamento (tipo 'tarefa')
    if (eventoTipo === 'tarefa') {
        // Menu específico para treinamentos
        menu.innerHTML = `
            <div class="context-menu-item" onclick="setEventStatus('${calendarEvent.id}', 1)">
                <span class="context-menu-icon">✓</span>
                Marcar como Concluído
            </div>
            <div class="context-menu-item submenu-trigger" id="submenu-trigger-${calendarEvent.id}">
                <span class="context-menu-icon">✗</span>
                Marcar como Não Concluído
                <span class="submenu-arrow">▶</span>
            </div>
            <div class="context-menu-item" onclick="setEventStatus('${calendarEvent.id}', null)">
                <span class="context-menu-icon">○</span>
                Remover Status
            </div>
        `;
    } else {
        // Menu padrão para outros tipos de eventos
        menu.innerHTML = `
            <div class="context-menu-item" onclick="setEventStatus('${calendarEvent.id}', 1)">
                <span class="context-menu-icon">✓</span>
                Marcar como Concluído
            </div>
            <div class="context-menu-item" onclick="setEventStatus('${calendarEvent.id}', 2)">
                <span class="context-menu-icon">✗</span>
                Marcar como Não Concluído
            </div>
            <div class="context-menu-item" onclick="setEventStatus('${calendarEvent.id}', null)">
                <span class="context-menu-icon">○</span>
                Remover Status
            </div>
        `;
    }
    
    // Posicionar menu
    menu.style.left = event.pageX + 'px';
    menu.style.top = event.pageY + 'px';
    
    // Adicionar ao body
    document.body.appendChild(menu);
    
    // Adicionar eventos de hover para o submenu trigger se for um evento de treinamento
    if (eventoTipo === 'tarefa') {
        const submenuTrigger = document.getElementById(`submenu-trigger-${calendarEvent.id}`);
        if (submenuTrigger) {
            submenuTrigger.addEventListener('mouseenter', function(e) {
                showSubmenu(e, calendarEvent.id);
            });
            
            submenuTrigger.addEventListener('mouseleave', function(e) {
                // Verificar se o mouse não está indo para o submenu
                setTimeout(() => {
                    const submenu = document.getElementById('contextSubmenu');
                    if (submenu && !submenu.matches(':hover') && !submenuTrigger.matches(':hover')) {
                        submenu.remove();
                    }
                }, 100);
            });
        }
    }
    
    // Fechar menu ao clicar fora
    setTimeout(() => {
        document.addEventListener('click', function closeMenu(e) {
            if (!menu.contains(e.target)) {
                menu.remove();
                // Remover também submenu se existir
                const submenu = document.getElementById('contextSubmenu');
                if (submenu) {
                    submenu.remove();
                }
                document.removeEventListener('click', closeMenu);
            }
        });
    }, 10);
}

// Função para mostrar submenu de opções de não conclusão para treinamentos
function showSubmenu(event, eventId) {
    // Remover submenu existente se houver
    const existingSubmenu = document.getElementById('contextSubmenu');
    if (existingSubmenu) {
        existingSubmenu.remove();
    }
    
    // Criar submenu
    const submenu = document.createElement('div');
    submenu.id = 'contextSubmenu';
    submenu.className = 'context-submenu';
    
    submenu.innerHTML = `
        <div class="context-menu-item" onclick="setTrainingEventStatus('${eventId}', 2, null)">
            <span class="context-menu-icon">✗</span>
            Não Concluído
        </div>
        <div class="context-menu-item" onclick="setTrainingEventStatus('${eventId}', 2, 'Não efetuado cliente')">
            <span class="context-menu-icon">👤</span>
            Não efetuado cliente
        </div>
        <div class="context-menu-item" onclick="setTrainingEventStatus('${eventId}', 2, 'Não efetuado Nutify')">
            <span class="context-menu-icon">🏢</span>
            Não efetuado Nutify
        </div>
    `;
    
    // Posicionar submenu à direita do menu principal
    const mainMenu = document.getElementById('contextMenu');
    const rect = mainMenu.getBoundingClientRect();
    submenu.style.left = (rect.right + 5) + 'px';
    submenu.style.top = rect.top + 'px';
    
    // Adicionar ao body
    document.body.appendChild(submenu);
    
    // Adicionar eventos de hover para manter o submenu aberto
    submenu.addEventListener('mouseenter', function() {
        // Manter submenu aberto quando o mouse está sobre ele
    });
    
    submenu.addEventListener('mouseleave', function() {
        // Fechar submenu quando o mouse sai dele
        setTimeout(() => {
            const submenuTrigger = document.querySelector('.submenu-trigger:hover');
            if (!submenuTrigger) {
                submenu.remove();
            }
        }, 100);
    });
}

// Função para definir status específico de eventos de treinamento com motivo de cancelamento
function setTrainingEventStatus(eventId, status, motivoCancelamento = null) {
    const formData = new URLSearchParams();
    formData.append('event_id', eventId);
    formData.append('status', status);
    if (motivoCancelamento) {
        formData.append('motivo_cancelamento', motivoCancelamento);
    }
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    fetch('../paginas/agenda_ajax.php?action=setTrainingStatus', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Atualizar o evento específico no array global
            const eventIndex = allEvents.findIndex(event => event.id == eventId);
            if (eventIndex !== -1) {
                allEvents[eventIndex].extendedProps.concluido = status === 'null' ? null : parseInt(status);
            }
            
            // Aplicar filtros para renderizar corretamente sem duplicação
            applyAllFilters();
        } else {
            showNotification('Erro: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao atualizar status do evento de treinamento.', 'error');
    });
    
    // Fechar menus de contexto
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.remove();
    }
    const submenu = document.getElementById('contextSubmenu');
    if (submenu) {
        submenu.remove();
    }
}

// Função para definir status específico do evento
function setEventStatus(eventId, status) {
    const formData = new URLSearchParams();
    formData.append('event_id', eventId);
    formData.append('status', status);
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    fetch('../paginas/agenda_ajax.php?action=setStatus', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Atualizar o evento específico no array global
            const eventIndex = allEvents.findIndex(event => event.id == eventId);
            if (eventIndex !== -1) {
                allEvents[eventIndex].extendedProps.concluido = status === 'null' ? null : parseInt(status);
            }
            
            // Aplicar filtros para renderizar corretamente sem duplicação
            applyAllFilters();
        } else {
            showNotification('Erro: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao atualizar status do evento.', 'error');
    });
    
    // Fechar menu de contexto
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.remove();
    }
    // Fechar submenu se existir
    const submenu = document.getElementById('contextSubmenu');
    if (submenu) {
        submenu.remove();
    }
}

// Função para alternar status de conclusão do evento (mantida para compatibilidade)
function toggleEventStatus(eventId) {
    const formData = new URLSearchParams();
    formData.append('event_id', eventId);
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    fetch('../paginas/agenda_ajax.php?action=toggleConcluido', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Atualizar o evento específico no array global
            const eventIndex = allEvents.findIndex(event => event.id == eventId);
            if (eventIndex !== -1) {
                allEvents[eventIndex].extendedProps.concluido = data.concluido;
            }
            
            // Aplicar filtros para renderizar corretamente
            applyAllFilters();
        } else {
            showNotification('Erro: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao atualizar status do evento.', 'error');
    });
    
    // Fechar menu de contexto
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.remove();
    }
}

// Excluir evento
document.getElementById('deleteEventBtn').addEventListener('click', function() {
    if (confirm('Tem certeza que deseja excluir este evento?')) {
        const eventId = document.getElementById('eventoId').value;
        
        fetch('../paginas/agenda_ajax.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${eventId}&csrf_token=${document.getElementById('csrf_token').value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Evento excluído com sucesso!', 'success');
                document.getElementById('eventoModal').style.display = 'none';
                refreshCalendar();
                
                // Atualizar o histórico de excluídos se a aba estiver ativa
                updateDeletedEventsIfActive();
            } else {
                showNotification('Erro ao excluir evento: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showNotification('Erro ao excluir evento.', 'error');
        });
    }
});

function refreshCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl && calendarEl._fullCalendar) {
        // Verificar se já existe uma requisição em andamento para evitar duplicações
        if (window.refreshingCalendar) {
            return;
        }
        
        window.refreshingCalendar = true;
        
        // Buscar eventos atualizados do servidor para sincronizar com filtros
        const formData = new URLSearchParams();
        formData.append('csrf_token', document.getElementById('csrf_token').value);
        formData.append('start', window.calendar.view.currentStart.toISOString().split('T')[0]);
        formData.append('end', window.calendar.view.currentEnd.toISOString().split('T')[0]);
        
        fetch('../paginas/agenda_ajax.php?action=fetch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            // A resposta da ação 'fetch' já retorna diretamente o array de eventos
            allEvents = data || [];
            
            // Aplicar filtros para renderizar corretamente sem duplicação
            applyAllFilters();
        })
        .catch(error => {
            console.error('Erro ao atualizar calendário:', error);
            // Fallback para refetchEvents em caso de erro
            calendarEl._fullCalendar.refetchEvents();
        })
        .finally(() => {
            window.refreshingCalendar = false;
        });
    }
}

// Função para verificar atualizações na agenda - sistema simplificado sem notificações
let lastEventTimestamp = localStorage.getItem('lastEventTimestamp') || (Date.now() - 3600000);
let updateInterval = null;
let visibilityListenerAdded = false;

// Salvar timestamp no localStorage quando houver atualizações
function saveLastEventTimestamp(timestamp) {
    // Garantir que o timestamp seja sempre numérico (milissegundos)
    const numericTimestamp = typeof timestamp === 'string' ? 
        new Date(timestamp).getTime() : 
        (typeof timestamp === 'number' ? timestamp : Date.now());
    
    localStorage.setItem('lastEventTimestamp', numericTimestamp);
    lastEventTimestamp = numericTimestamp;
}

function startRealTimeUpdates() {
    // Evitar múltiplos intervalos
    if (updateInterval) {
        clearInterval(updateInterval);
    }
    
    // Verificar atualizações a cada 10 segundos para sincronização entre usuários
    updateInterval = setInterval(checkForUpdates, 10000);
    
    // Também verificar quando o usuário volta para a aba (apenas uma vez)
    if (!visibilityListenerAdded) {
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                checkForUpdates();
            }
        });
        visibilityListenerAdded = true;
    }
}

function checkForUpdates() {
    // Verificar se já existe uma verificação em andamento para evitar múltiplas requisições
    if (window.checkingUpdates) {
        return;
    }
    
    window.checkingUpdates = true;
    
    // Verificar atualizações apenas para atualizar o calendário, sem notificações
    console.log('Verificando atualizações com timestamp:', lastEventTimestamp);
    
    fetch('../paginas/agenda_ajax.php?action=checkUpdates', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `last_timestamp=${lastEventTimestamp}&csrf_token=${document.getElementById('csrf_token').value}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Resposta do servidor:', data);
        if (data.success && data.hasUpdates) {
            // Atualizar timestamp para o momento atual para próximas verificações
            const currentTimestamp = Date.now();
            saveLastEventTimestamp(currentTimestamp);
            
            // Atualizar o calendário apenas se estivermos na página da agenda
            const calendarEl = document.getElementById('calendar');
            if (calendarEl && calendarEl._fullCalendar) {
                refreshCalendar();
            }
            
            // Atualizar o histórico de excluídos se a aba estiver ativa
            updateDeletedEventsIfActive();
        }
    })
    .catch(error => {
        console.error('Erro ao verificar atualizações:', error);
    })
    .finally(() => {
        window.checkingUpdates = false;
    });
}

// Funções de notificação removidas para melhorar performance

// Função para carregar o conteúdo do submenu selecionado
function loadContent(submenuId) {
    // Oculta a mensagem de boas-vindas ao carregar outro conteúdo
    const welcomeMessage = document.getElementById('welcome-message');
    if (welcomeMessage) {
        welcomeMessage.style.display = 'none';
    }

    // Cria um novo objeto XMLHttpRequest
    const xhr = new XMLHttpRequest();
    xhr.open("GET", `../paginas/carregar_conteudo.php?submenu_id=${submenuId}`, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            document.getElementById('content-container').innerHTML = xhr.responseText;
            document.getElementById('content-container').style.display = 'block'; // Exibe o conteúdo carregado
        } else {
            console.error("Erro ao carregar conteúdo:", xhr.statusText);
            document.getElementById('content-container').innerHTML = "<p>Erro ao carregar o conteúdo. Tente novamente.</p>";
        }
    };
    xhr.onerror = function () {
        console.error("Erro de rede ao tentar carregar o conteúdo.");
        document.getElementById('content-container').innerHTML = "<p>Erro de rede ao carregar o conteúdo. Tente novamente.</p>";
    };
    xhr.send();
}

// Função para mostrar o vídeo anterior
function showPreviousVideo() {
    if (currentVideoIndex > 0) {
        currentVideoIndex--;
        updateVideoDisplay();
        updateVideoCounter();
    }
}

// Função para mostrar o próximo vídeo
function showNextVideo() {
    if (currentVideoIndex < videos.length - 1) {
        currentVideoIndex++;
        updateVideoDisplay();
        updateVideoCounter();
    }
}

// Função para atualizar a exibição do vídeo ativo
function updateVideoDisplay() {
    if (videos.length === 0) return; // Sai da função se não houver vídeos

    videos.forEach((iframe, index) => {
        if (index === currentVideoIndex) {
            iframe.classList.add('active');
        } else {
            iframe.classList.remove('active');
        }
    });
}

// Função para atualizar o indicador de vídeo atual
function updateVideoCounter() {
    const videoCounter = document.getElementById('videoCounter');
    if (videoCounter) {
        videoCounter.textContent = `${currentVideoIndex + 1}/${videos.length}`;
    }
}

// Função para alternar a visibilidade dos submenus
function toggleMenu(id) {
    const submenu = document.getElementById(id);
    if (submenu.style.display === "block") {
        submenu.style.display = "none";
    } else {
        submenu.style.display = "block";
    }
}

// Função para limpar o campo de pesquisa e resetar o filtro
function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    filterMenu();
    document.getElementById('clearSearch').style.display = 'none';
}

// Função para filtrar os itens do menu
function filterMenu() {
    const input = document.getElementById('searchInput');
    const clearIcon = document.getElementById('clearSearch');
    const filter = input.value.trim().toUpperCase();

    clearIcon.style.display = filter ? 'block' : 'none';

    const submenus = document.querySelectorAll('.submenu');
    submenus.forEach((submenu) => {
        const menuItems = submenu.querySelectorAll('a');
        let hasMatch = false;

        menuItems.forEach((menuItem) => {
            const textValue = menuItem.textContent || menuItem.innerText;
            const isMatch = textValue.toUpperCase().includes(filter);

            menuItem.style.display = isMatch ? "" : "none";
            menuItem.style.color = isMatch ? "#8CC053" : "";
            menuItem.style.fontWeight = isMatch ? "bold" : "";
            menuItem.style.backgroundColor = isMatch ? "transparent" : "";

            if (isMatch && !menuItem.querySelector('ion-icon')) {
                const icon = document.createElement('ion-icon');
                icon.setAttribute('name', 'checkmark-done-outline');
                icon.style.marginLeft = "10px";
                menuItem.appendChild(icon);
            } else if (!isMatch && menuItem.querySelector('ion-icon')) {
                menuItem.removeChild(menuItem.querySelector('ion-icon'));
            }

            hasMatch = hasMatch || isMatch;
        });

        submenu.style.display = hasMatch ? "block" : "none";
    });

    if (!filter) {
        const allMenuItems = document.querySelectorAll('.submenu a');
        allMenuItems.forEach((menuItem) => {
            menuItem.style.display = "";
            menuItem.style.color = "";
            menuItem.style.fontWeight = "";
            menuItem.style.backgroundColor = "";

            const icon = menuItem.querySelector('ion-icon');
            if (icon) {
                menuItem.removeChild(icon);
            }
        });

        const allSubmenus = document.querySelectorAll('.submenu');
        allSubmenus.forEach((submenu) => {
            submenu.style.display = "none";
        });
    }
}

// Validação do formulário de alteração de senha
document.getElementById('formAlterarSenha').addEventListener('submit', function (event) {
    const novaSenha = document.getElementById('nova_senha').value;
    const confirmarSenha = document.getElementById('confirmar_senha').value;

    if (novaSenha !== confirmarSenha) {
        alert('A nova senha e a confirmação não coincidem.');
        event.preventDefault();
    }
});

function toggleSenhaForm() {
    const form = document.getElementById('alterarSenhaForm');
    const overlay = document.getElementById('overlay');

    form.classList.toggle('visible');
    overlay.classList.toggle('visible');
}

// Fechar o formulário ao clicar no overlay
document.getElementById('overlay').addEventListener('click', function () {
    toggleSenhaForm();
});

// Fechar o formulário ao pressionar a tecla ESC
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const form = document.getElementById('alterarSenhaForm');
        const overlay = document.getElementById('overlay');

        if (form.classList.contains('visible')) {
            toggleSenhaForm();
        }
    }
});

document.getElementById('configButton').addEventListener('click', function () {
    const configContainer = document.getElementById('configContainer');
    configContainer.classList.toggle('visible');
});

// Fechar o container de configuração ao clicar fora dele
document.addEventListener('click', function (event) {
    const configContainer = document.getElementById('configContainer');
    const configButton = document.getElementById('configButton');

    if (!configContainer.contains(event.target) && !configButton.contains(event.target)) {
        configContainer.classList.remove('visible');
    }
});

// Alternar visibilidade do dropdown de Suporte
document.getElementById('supportButton').addEventListener('click', function (event) {
    event.stopPropagation(); // Evita que o clique propague para o documento
    const suporteDropdown = document.getElementById('supportMenu');
    const conhecimentoDropdown = document.getElementById('knowledgeMenu');
    const treinamentoDropdown = document.getElementById('trainingMenu');
    const cadastroDropdown = document.getElementById('registerMenu');

    suporteDropdown.classList.toggle('visible');
    conhecimentoDropdown.classList.remove('visible');
    treinamentoDropdown.classList.remove('visible');
    cadastroDropdown.classList.remove('visible');
});

// Fechar o dropdown ao clicar fora dele
document.addEventListener('click', function (event) {
    const suporteDropdown = document.getElementById('supportMenu');
    const suporteButton = document.getElementById('supportButton');

    if (!suporteDropdown.contains(event.target) && !suporteButton.contains(event.target)) {
        suporteDropdown.classList.remove('visible');
    }
});

// Alternar visibilidade do dropdown de Conhecimento
document.getElementById('knowledgeButton').addEventListener('click', function (event) {
    event.stopPropagation(); // Evita que o clique propague para o documento
    const conhecimentoDropdown = document.getElementById('knowledgeMenu');
    const suporteDropdown = document.getElementById('supportMenu');
    const treinamentoDropdown = document.getElementById('trainingMenu');
    const cadastroDropdown = document.getElementById('registerMenu');

    conhecimentoDropdown.classList.toggle('visible');
    suporteDropdown.classList.remove('visible');
    treinamentoDropdown.classList.remove('visible');
    cadastroDropdown.classList.remove('visible');
});

// Fechar o dropdown ao clicar fora dele
document.addEventListener('click', function (event) {
    const conhecimentoDropdown = document.getElementById('knowledgeMenu');
    const conhecimentoButton = document.getElementById('knowledgeButton');

    if (!conhecimentoDropdown.contains(event.target) && !conhecimentoButton.contains(event.target)) {
        conhecimentoDropdown.classList.remove('visible');
    }
});

// Alternar visibilidade do dropdown de Treinamento
document.getElementById('trainingButton').addEventListener('click', function (event) {
    event.stopPropagation(); // Evita que o clique propague para o documento
    const treinamentoDropdown = document.getElementById('trainingMenu');
    const suporteDropdown = document.getElementById('supportMenu');
    const conhecimentoDropdown = document.getElementById('knowledgeMenu');
    const cadastroDropdown = document.getElementById('registerMenu');

    treinamentoDropdown.classList.toggle('visible');
    suporteDropdown.classList.remove('visible');
    conhecimentoDropdown.classList.remove('visible');
    cadastroDropdown.classList.remove('visible');
});

// Fechar o dropdown ao clicar fora dele
document.addEventListener('click', function (event) {
    const treinamentoDropdown = document.getElementById('trainingMenu');
    const treinamentoButton = document.getElementById('trainingButton');

    if (!treinamentoDropdown.contains(event.target) && !treinamentoButton.contains(event.target)) {
        treinamentoDropdown.classList.remove('visible');
    }
});

// Alternar visibilidade do dropdown de Cadastros
document.getElementById('registerButton').addEventListener('click', function (event) {
    event.stopPropagation(); // Evita que o clique propague para o documento
    const cadastroDropdown = document.getElementById('registerMenu');
    const suporteDropdown = document.getElementById('supportMenu');
    const conhecimentoDropdown = document.getElementById('knowledgeMenu');
    const treinamentoDropdown = document.getElementById('trainingMenu');

    cadastroDropdown.classList.toggle('visible');
    suporteDropdown.classList.remove('visible');
    conhecimentoDropdown.classList.remove('visible');
    treinamentoDropdown.classList.remove('visible');
});

// Fechar o dropdown ao clicar fora dele
document.addEventListener('click', function (event) {
    const cadastroDropdown = document.getElementById('registerMenu');
    const cadastroButton = document.getElementById('registerButton');

    if (!cadastroDropdown.contains(event.target) && !cadastroButton.contains(event.target)) {
        cadastroDropdown.classList.remove('visible');
    }
});

// Adicionar evento de clique ao botão de sair
document.getElementById('logoutButton').addEventListener('click', function () {
    // Exibe a caixa de diálogo de confirmação
    const confirmacao = confirm("Você realmente deseja sair?");

    // Se o usuário confirmar, redireciona para a página de logout
    if (confirmacao) {
        window.location.href = 'logout.php';
    }
});

// Função para alternar a visibilidade do menu e do cabeçalho fixo
function toggleMenuVisibility() {
    const menu = document.getElementById('menu');
    const fixedHeader = document.getElementById('fixed-header');
    const content = document.getElementById('content');
    const toggleButton = document.getElementById('toggle-menu-button');

    if (menu.style.display === 'none') {
        // Mostrar menu
        menu.style.display = 'block';
        fixedHeader.style.display = 'block';
        content.style.marginLeft = '300px';
        content.style.width = 'calc(100% - 300px)';
        toggleButton.classList.remove('menu-closed'); // Remove a classe para voltar ao ícone normal
    } else {
        // Esconder menu
        menu.style.display = 'none';
        fixedHeader.style.display = 'none';
        content.style.marginLeft = '0';
        content.style.width = '100%';
        toggleButton.classList.add('menu-closed'); // Adiciona a classe para girar o ícone
    }
}

// Verifica o estado do tema no localStorage
const themeSwitch = document.getElementById('theme-switch');
const themeLabel = document.getElementById('theme-label');
const body = document.body;

// Função para aplicar o tema
function applyTheme(isDark) {
    if (isDark) {
        body.classList.add('dark-theme'); // Adiciona a classe para o tema escuro
        body.style.backgroundImage = "url('../assets/img/fundo_escuro.png')";
        themeLabel.textContent = "Tema Escuro";
    } else {
        body.classList.remove('dark-theme'); // Remove a classe do tema escuro
        body.style.backgroundImage = "url('../assets/img/fundo_claro.png')";
        themeLabel.textContent = "Tema Claro";
    }
}

// Verifica o estado salvo no localStorage
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'dark') {
    themeSwitch.checked = true;
    applyTheme(true);
} else {
    themeSwitch.checked = false;
    applyTheme(false);
}

// Adiciona um listener para o botão de alternância
themeSwitch.addEventListener('change', function () {
    if (this.checked) {
        localStorage.setItem('theme', 'dark');
        applyTheme(true);
    } else {
        localStorage.setItem('theme', 'light');
        applyTheme(false);
    }
});

// Adiciona um listener para o evento de tecla pressionada
document.addEventListener('keydown', function (event) {
    // Verifica se a tecla pressionada é ESC (código 27)
    if (event.keyCode === 27) {
        // Verificar se algum modal está aberto antes de voltar
        const eventoModal = document.getElementById('eventoModal');
        const agendaModal = document.getElementById('agendaModal');
        const atendimentoModal = document.getElementById('atendimentoModal');
        
        // Se nenhum modal estiver aberto, volta para a página anterior
        if ((!eventoModal || eventoModal.style.display !== 'block') &&
            (!agendaModal || agendaModal.style.display !== 'block') &&
            (!atendimentoModal || atendimentoModal.style.display !== 'block')) {
            window.history.back();
        }
    }
});

function openTab(evt, tabName) {
    const tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove("active");
    }

    const tabButtons = document.getElementsByClassName("tab-button");
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }

    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");

    // Controlar visibilidade do filtro de cliente
    const agendaSearchContainer = document.querySelector('.agenda-search-container');
    if (agendaSearchContainer) {
        if (tabName === 'calendarTab') {
            agendaSearchContainer.style.display = 'flex';
        } else {
            agendaSearchContainer.style.display = 'none';
        }
    }

    if (tabName === 'deletedEventsTab') {
        loadDeletedEvents();
    } else if (tabName === 'changesHistoryTab') {
        loadChangesHistory();
    } else if (tabName === 'calendarTab') {
        // Apenas ajustar o tamanho do calendário sem re-renderizar
        setTimeout(() => {
            if (window.calendar) {
                window.calendar.updateSize();
            }
        }, 100);
    }
}

// Variáveis para controle de paginação
let currentPage = 1;
let itemsPerPage = 10;
let totalDeletedEvents = 0;
let allDeletedEvents = [];

// Variáveis para controle do histórico de alterações
let currentChangesPage = 1;
let totalChangesEvents = 0;
let allChangesEvents = [];

function loadDeletedEvents(page = 1) {
    currentPage = page;
    
    fetch('../paginas/agenda_ajax.php?action=fetchDeleted', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `csrf_token=${document.getElementById('csrf_token').value}`
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('deletedEventsList');
        
        if (data.success && data.events.length > 0) {
            allDeletedEvents = data.events;
            totalDeletedEvents = allDeletedEvents.length;
            renderDeletedEventsPage();
        } else {
            container.innerHTML = '<p>Nenhum evento excluído encontrado.</p>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar eventos excluídos:', error);
        document.getElementById('deletedEventsList').innerHTML = '<p>Erro ao carregar eventos excluídos.</p>';
    });
}

function renderDeletedEventsPage() {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const eventsToShow = allDeletedEvents.slice(startIndex, endIndex);
    const totalPages = Math.ceil(totalDeletedEvents / itemsPerPage);
    const container = document.getElementById('deletedEventsList');
    
    let html = '<div style="overflow-x: auto;">';
    html += '<table class="deleted-events-table">';
    html += '<thead><tr><th>ID</th><th>Título</th><th>Data</th><th>Usuário</th><th>Excluído por</th><th>Data Exclusão</th><th>Ações</th></tr></thead><tbody>';
    
    if (eventsToShow.length === 0) {
        html += '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">Nenhum evento excluído encontrado.</td></tr>';
    } else {
        eventsToShow.forEach(event => {
            html += `
                <tr>
                    <td>${event.id}</td>
                    <td>${event.titulo}</td>
                    <td>${new Date(event.inicio).toLocaleString()}</td>
                    <td>${event.usuario_nome}</td>
                    <td>${event.excluido_por}</td>
                    <td>${new Date(event.data_exclusao).toLocaleString()}</td>
                    <td>
                        <button class="btn-restore" data-id="${event.id}">Restaurar</button>
                    </td>
                </tr>
            `;
        });
    }
    
    html += '</tbody></table></div>';
    
    // Adicionar controles de paginação
    if (totalPages > 1) {
        html += `
            <div class="pagination-controls">
                <div class="pagination-info">
                    Mostrando ${startIndex + 1}-${Math.min(endIndex, totalDeletedEvents)} de ${totalDeletedEvents} eventos
                </div>
                <div class="pagination-buttons">
                    <button class="pagination-btn" onclick="loadDeletedEvents(1)" ${currentPage === 1 ? 'disabled' : ''}>
                        Primeira
                    </button>
                    <button class="pagination-btn" onclick="loadDeletedEvents(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                        Anterior
                    </button>
                    <span class="pagination-info">Página ${currentPage} de ${totalPages}</span>
                    <button class="pagination-btn" onclick="loadDeletedEvents(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                        Próxima
                    </button>
                    <button class="pagination-btn" onclick="loadDeletedEvents(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>
                        Última
                    </button>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    
    // Adiciona eventos aos botões de restaurar
    document.querySelectorAll('.btn-restore').forEach(button => {
        button.addEventListener('click', function() {
            restoreEvent(this.getAttribute('data-id'));
        });
    });
}

function restoreEvent(eventId) {
    if (confirm('Deseja restaurar este evento?')) {
        fetch('../paginas/agenda_ajax.php?action=restore', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${eventId}&csrf_token=${document.getElementById('csrf_token').value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Evento restaurado com sucesso!', 'success');
                loadDeletedEvents(currentPage);
                refreshCalendar();
            } else {
                showNotification('Erro ao restaurar evento: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showNotification('Erro ao restaurar evento.', 'error');
        });
    }
}

// Função para abrir o modal
function openModal() {
    const modal = document.getElementById('atendimentoModal');
    const iframe = document.getElementById('modalIframe');
    iframe.src = 'atendimento/incluir_atendimento_rapido.php';
    modal.style.display = 'block';
}

// Função para fechar o modal e recarregar a página
function closeModal() {
    const modal = document.getElementById('atendimentoModal');
    modal.style.display = 'none';
    window.location.reload(); // Recarrega a página
}

// Adiciona o evento de tecla pressionada
document.addEventListener('keydown', function (event) {
    if (event.key === 'F2') {
        openModal();
    }
});

// Fecha o modal ao clicar no botão de fechar
const atendimentoModalClose = document.querySelector('#atendimentoModal .close');
if (atendimentoModalClose) {
    atendimentoModalClose.addEventListener('click', closeModal);
}

// Fecha o modal ao clicar fora do conteúdo do modal (com verificação para evitar fechamento durante seleção de texto)
window.addEventListener('click', function (event) {
    const modal = document.getElementById('atendimentoModal');
    if (event.target === modal && !window.getSelection().toString()) {
        closeModal();
    }
});

// Carregar gráficos ao iniciar a página
document.addEventListener('DOMContentLoaded', function () {
    // Código dos gráficos de atendimentos removido
});

// Configura o filtro para o campo de cliente
function setupClienteFilter() {
    const input = document.getElementById('cliente_filter');
    const options = document.getElementById('cliente_options');
    const select = document.getElementById('eventoCliente');

    if (!input || !options || !select) return;

    // Inicialmente oculta o dropdown
    options.style.display = 'none';

    input.addEventListener('input', function() {
        const filter = input.value.toUpperCase();
        const divs = options.getElementsByTagName('div');
        let hasVisibleOptions = false;

        for (let i = 0; i < divs.length; i++) {
            const div = divs[i];
            const text = div.textContent.toUpperCase();
            if (text.indexOf(filter) > -1) {
                div.style.display = '';
                hasVisibleOptions = true;
            } else {
                div.style.display = 'none';
            }
        }

        // Só mostra o dropdown se houver texto no input e opções visíveis
        if (filter.length > 0 && hasVisibleOptions) {
            options.style.display = 'block';
        } else {
            options.style.display = 'none';
        }
    });

    input.addEventListener('focus', function() {
        // Aplica o filtro baseado no texto atual do campo
        const filter = input.value.toUpperCase();
        const divs = options.getElementsByTagName('div');
        let hasVisibleOptions = false;

        for (let i = 0; i < divs.length; i++) {
            const div = divs[i];
            const text = div.textContent.toUpperCase();
            if (filter.length === 0 || text.indexOf(filter) > -1) {
                div.style.display = '';
                hasVisibleOptions = true;
            } else {
                div.style.display = 'none';
            }
        }

        // Só mostra o dropdown se houver opções visíveis
        if (hasVisibleOptions) {
            options.style.display = 'block';
        }
    });

    input.addEventListener('blur', function() {
        setTimeout(() => {
            options.style.display = 'none';
        }, 200);
    });

    options.addEventListener('click', function(e) {
        if (e.target.tagName === 'DIV') {
            input.value = e.target.textContent;
            select.value = e.target.getAttribute('data-value');
            options.style.display = 'none';
        }
    });

    // Sincroniza o valor inicial se já houver um selecionado
    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        input.value = selectedOption.textContent;
    }
}

// Função para lidar com o clique no título "Cliente"
function handleClienteClick() {
    const clienteSelect = document.getElementById('eventoCliente');
    const clienteValue = clienteSelect.value;
    
    if (clienteValue && clienteValue !== '') {
        // Se há um cliente selecionado, abrir editar_cliente em nova aba
        const url = `clientes/editar_cliente.php?id=${clienteValue}`;
        window.open(url, '_blank');
    } else {
        // Se não há cliente selecionado, redirecionar para clientes.php
        window.open('clientes/clientes.php', '_blank');
    }
}

// Inicializar busca de cliente quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    setupClienteFilter();
});

// Funções para marcar/desmarcar todos os usuários
function selectAllUsers() {
    const checkboxes = document.querySelectorAll('.user-filter-checkbox');
    
    // Usar requestAnimationFrame para melhor performance
    requestAnimationFrame(() => {
        checkboxes.forEach(checkbox => {
            if (!checkbox.checked) {
                checkbox.checked = true;
                const userName = checkbox.getAttribute('data-user');
                const userColor = checkbox.getAttribute('data-color');
                activeUserFilters[userName] = true;
                
                // Aplicar a cor do usuário ao checkbox
                checkbox.style.borderColor = userColor;
                checkbox.style.backgroundColor = userColor;
            }
        });
        
        // Salvar o estado atualizado
        saveUserFiltersState();
        
        // Usar debounce para evitar múltiplas chamadas
        clearTimeout(window.userFilterTimeout);
        window.userFilterTimeout = setTimeout(() => {
            applyUserFilters();
        }, 100);
    });
}

function deselectAllUsers() {
    const checkboxes = document.querySelectorAll('.user-filter-checkbox');
    
    // Usar requestAnimationFrame para melhor performance
    requestAnimationFrame(() => {
        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                checkbox.checked = false;
                const userName = checkbox.getAttribute('data-user');
                const userColor = checkbox.getAttribute('data-color');
                activeUserFilters[userName] = false;
                
                // Manter a borda da cor do usuário mas remover o fundo
                checkbox.style.borderColor = userColor;
                checkbox.style.backgroundColor = 'white';
            }
        });
        
        // Salvar o estado atualizado
        saveUserFiltersState();
        
        // Usar debounce para evitar múltiplas chamadas
        clearTimeout(window.userFilterTimeout);
        window.userFilterTimeout = setTimeout(() => {
            applyUserFilters();
        }, 100);
    });
}

// Variável para armazenar o filtro de cliente ativo
let activeClienteFilter = '';

// Função para filtrar eventos por cliente
function filterEventsByCliente(events, clienteFilter) {
    if (!clienteFilter || clienteFilter.trim() === '') {
        return events;
    }
    
    const filter = clienteFilter.toLowerCase().trim();
    
    return events.filter(event => {
        // Verificar se o evento tem informações de cliente
        if (!event.extendedProps) {
            return false;
        }
        
        // Usar o campo cliente_nome_completo que já vem formatado como "CONTRATO - NOME"
        const clienteNomeCompleto = event.extendedProps.cliente_nome_completo;
        
        if (!clienteNomeCompleto) {
            return false;
        }
        
        // Buscar no nome completo formatado (contrato + nome)
        return clienteNomeCompleto.toLowerCase().includes(filter);
    });
}

// Função para aplicar todos os filtros (usuário + cliente)
function applyAllFilters() {
    if (!window.calendar) return;
    
    // Usar batch operations para melhor performance e evitar duplicação
    window.calendar.batchRendering(() => {
        // Remover todos os eventos atuais de forma mais eficiente
        const existingEvents = window.calendar.getEvents();
        existingEvents.forEach(event => event.remove());
        
        // Primeiro aplicar filtro de usuário
        let filteredEvents = filterEventsByUser(allEvents);
        
        // Depois aplicar filtro de cliente se houver
        if (activeClienteFilter) {
            filteredEvents = filterEventsByCliente(filteredEvents, activeClienteFilter);
        }
        
        // Criar um Set para evitar duplicação de eventos por ID
        const eventIds = new Set();
        const uniqueEvents = filteredEvents.filter(eventData => {
            if (eventIds.has(eventData.id)) {
                return false; // Evento duplicado, pular
            }
            eventIds.add(eventData.id);
            return true;
        });
        
        // Adicionar os eventos únicos filtrados ao calendário
        uniqueEvents.forEach(eventData => {
            try {
                const addedEvent = window.calendar.addEvent(eventData);
                
                // Aplicar classes CSS de status de forma mais eficiente
                if (addedEvent && addedEvent.el) {
                    // Remover classes de status anteriores
                    addedEvent.el.classList.remove('evento-concluido', 'evento-nao-concluido', 'evento-pendente');
                    
                    // Adicionar classe baseada no status
                    const concluido = addedEvent.extendedProps.concluido;
                    
                    if (concluido == 1) {
                        addedEvent.el.classList.add('evento-concluido');
                        addedEvent.el.title = 'Concluído';
                    } else if (concluido == 2) {
                        addedEvent.el.classList.add('evento-nao-concluido');
                        addedEvent.el.title = 'Não Concluído';
                    } else {
                        addedEvent.el.classList.add('evento-pendente');
                        addedEvent.el.title = 'Pendente';
                    }
                }
            } catch (error) {
                console.error('Erro ao adicionar evento:', error, eventData);
            }
        });
    });
}

// Função para configurar o filtro de cliente na agenda
function setupAgendaClienteFilter() {
    const agendaClienteFilter = document.getElementById('agendaClienteFilter');
    
    if (!agendaClienteFilter) return;
    
    // Evento de input para filtrar em tempo real
    agendaClienteFilter.addEventListener('input', function() {
        activeClienteFilter = this.value;
        applyAllFilters();
    });
    
    // Evento de keyup para capturar teclas especiais
    agendaClienteFilter.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            activeClienteFilter = this.value;
            applyAllFilters();
        }
    });
}

// Função para limpar o filtro de cliente
function clearAgendaClienteFilter() {
    const agendaClienteFilter = document.getElementById('agendaClienteFilter');
    
    if (agendaClienteFilter) {
        agendaClienteFilter.value = '';
        activeClienteFilter = '';
        applyAllFilters();
        agendaClienteFilter.focus();
    }
}

// Funções para o histórico de alterações
function loadChangesHistory(page = 1) {
    currentChangesPage = page;
    
    fetch('../paginas/agenda_ajax.php?action=fetchChangesHistory', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `csrf_token=${document.getElementById('csrf_token').value}`
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('changesHistoryList');
        
        if (data.success && data.events.length > 0) {
            allChangesEvents = data.events;
            totalChangesEvents = allChangesEvents.length;
            renderChangesHistoryPage();
        } else {
            container.innerHTML = '<p>Nenhum histórico de alterações encontrado.</p>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar histórico de alterações:', error);
        document.getElementById('changesHistoryList').innerHTML = '<p>Erro ao carregar histórico de alterações.</p>';
    });
}

function renderChangesHistoryPage() {
    const startIndex = (currentChangesPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const eventsToShow = allChangesEvents.slice(startIndex, endIndex);
    const totalPages = Math.ceil(totalChangesEvents / itemsPerPage);
    const container = document.getElementById('changesHistoryList');
    
    let html = '<div style="overflow-x: auto;">';
    html += '<table class="changes-history-table">';
    html += '<thead><tr><th>ID</th><th>Título</th><th>Data Original</th><th>Ação</th><th>Alterado por</th><th>Data da Alteração</th><th>Ações</th></tr></thead><tbody>';
    
    if (eventsToShow.length === 0) {
        html += '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">Nenhum histórico de alterações encontrado.</td></tr>';
    } else {
        eventsToShow.forEach(event => {
            html += `
                <tr>
                    <td>${event.evento_id}</td>
                    <td>${event.titulo}</td>
                    <td>${event.inicio_original ? new Date(event.inicio_original).toLocaleString() : new Date(event.inicio).toLocaleString()}</td>
                    <td>${getActionText(event.acao)}</td>
                    <td>${event.usuario_nome}</td>
                    <td>${new Date(event.data_acao).toLocaleString()}</td>
                    <td>
                        <button class="btn-view-event" data-id="${event.evento_id}">Ver Detalhes</button>
                    </td>
                </tr>
            `;
        });
    }
    
    html += '</tbody></table></div>';
    
    // Adicionar controles de paginação
    if (totalPages > 1) {
        html += `
            <div class="pagination-controls">
                <div class="pagination-info">
                    Mostrando ${startIndex + 1}-${Math.min(endIndex, totalChangesEvents)} de ${totalChangesEvents} alterações
                </div>
                <div class="pagination-buttons">
                    <button class="pagination-btn" onclick="loadChangesHistory(1)" ${currentChangesPage === 1 ? 'disabled' : ''}>
                        Primeira
                    </button>
                    <button class="pagination-btn" onclick="loadChangesHistory(${currentChangesPage - 1})" ${currentChangesPage === 1 ? 'disabled' : ''}>
                        Anterior
                    </button>
                    <span class="pagination-info">Página ${currentChangesPage} de ${totalPages}</span>
                    <button class="pagination-btn" onclick="loadChangesHistory(${currentChangesPage + 1})" ${currentChangesPage === totalPages ? 'disabled' : ''}>
                        Próxima
                    </button>
                    <button class="pagination-btn" onclick="loadChangesHistory(${totalPages})" ${currentChangesPage === totalPages ? 'disabled' : ''}>
                        Última
                    </button>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    
    // Adiciona eventos aos botões de ver detalhes
    document.querySelectorAll('.btn-view-event').forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            viewEventDetails(eventId);
        });
    });
}

function viewEventDetails(eventId) {
    // Buscar os detalhes do evento
    fetch('../paginas/agenda_ajax.php?action=fetchEvent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `csrf_token=${document.getElementById('csrf_token').value}&event_id=${eventId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.event) {
            // Abrir o modal de evento com os dados do banco
            openEventModalFromDatabase(data.event);
        } else {
            alert('Erro ao carregar detalhes do evento.');
        }
    })
    .catch(error => {
        console.error('Erro ao carregar detalhes do evento:', error);
        alert('Erro ao carregar detalhes do evento.');
    });
}

function openEventModalFromDatabase(eventData) {
    const modal = document.getElementById('eventoModal');
    const form = document.getElementById('eventoForm');
    const deleteBtn = document.getElementById('deleteEventBtn');
    const historicoContent = document.getElementById('historicoContent');
    
    // Resetar o formulário
    form.reset();

    // Modo edição - preencher com dados do banco
    document.getElementById('modalEventTitle').textContent = 'Visualizar Evento';
    document.getElementById('eventoId').value = eventData.id;
    document.getElementById('eventoTitulo').value = eventData.titulo || '';
    document.getElementById('eventoDescricao').value = eventData.descricao || '';
    document.getElementById('eventoInicio').value = formatDateTimeForInput(new Date(eventData.inicio));
    document.getElementById('eventoFim').value = eventData.fim ? formatDateTimeForInput(new Date(eventData.fim)) : '';
    document.getElementById('eventoCor').value = eventData.cor || '#3788d8';
    document.getElementById('eventoTipo').value = eventData.tipo || '';
    document.getElementById('eventoUsuario').value = eventData.usuario_id;
    
    // Definir o cliente no select oculto e no campo de filtro
    const clienteId = eventData.cliente_id;
    document.getElementById('eventoCliente').value = clienteId || '';
    
    // Atualizar o campo de filtro com o nome do cliente
    if (clienteId && eventData.cliente_nome) {
        document.getElementById('cliente_filter').value = eventData.cliente_nome;
    }

    // Mostrar botão de exclusão
    deleteBtn.style.display = 'inline-block';

    // Carregar histórico do evento
    loadEventHistory(eventData.id);

    // Configura eventos para fechar o modal (apenas uma vez)
    if (!modal.hasAttribute('data-listeners-added')) {
        // Fechar modal quando clicar no X
        modal.querySelector('.close').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Fechar modal quando clicar em Cancelar
        const cancelButton = modal.querySelector('.close-modal');
        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Fechar modal quando clicar fora (com verificação para evitar fechamento durante seleção de texto)
        window.addEventListener('click', function(event) {
            if (event.target === modal && !window.getSelection().toString()) {
                modal.style.display = 'none';
            }
        });
        
        // Prevenir que cliques dentro do conteúdo do modal fechem o modal
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        }
        
        modal.setAttribute('data-listeners-added', 'true');
    }

    // Mostrar o modal
    modal.style.display = 'block';
}