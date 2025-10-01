</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<script>
    // Funções para os dropdowns do header
document.addEventListener('DOMContentLoaded', function() {
    // Alternar visibilidade do dropdown de Suporte
    document.getElementById('supportButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        suporteDropdown.classList.toggle('visible');
        conhecimentoDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Conhecimento
    document.getElementById('knowledgeButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        conhecimentoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Treinamento
    document.getElementById('trainingButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        treinamentoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Cadastros
    document.getElementById('registerButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const cadastroDropdown = document.getElementById('registerMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const configContainer = document.getElementById('configContainer');

        cadastroDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        treinamentoDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(event) {
        const suporteButton = document.getElementById('supportButton');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoButton = document.getElementById('knowledgeButton');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const treinamentoButton = document.getElementById('trainingButton');
        const treinamentoDropdown = document.getElementById('trainingMenu');
        const cadastroButton = document.getElementById('registerButton');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configButton = document.getElementById('configButton');
        const configContainer = document.getElementById('configContainer');

        if (!suporteDropdown.contains(event.target) && !suporteButton.contains(event.target)) {
            suporteDropdown.classList.remove('visible');
        }
        
        if (!conhecimentoDropdown.contains(event.target) && !conhecimentoButton.contains(event.target)) {
            conhecimentoDropdown.classList.remove('visible');
        }
        
        if (!treinamentoDropdown.contains(event.target) && !treinamentoButton.contains(event.target)) {
            treinamentoDropdown.classList.remove('visible');
        }
        
        if (!cadastroDropdown.contains(event.target) && !cadastroButton.contains(event.target)) {
            cadastroDropdown.classList.remove('visible');
        }
        
        if (!configContainer.contains(event.target) && !configButton.contains(event.target)) {
            configContainer.classList.remove('visible');
        }
    });

    // Configurações
    document.getElementById('configButton')?.addEventListener('click', function() {
        const configContainer = document.getElementById('configContainer');
        configContainer.classList.toggle('visible');
    });

document.getElementById('logoutButton')?.addEventListener('click', function() {
    const confirmacao = confirm("Você realmente deseja sair?");
    if (confirmacao) {
        window.location.href = '/HelpDesk_Nutify/treinamentos/includes/logout.php';
    }
});

    // Tema escuro/claro
    const themeSwitch = document.getElementById('theme-switch');
    const themeLabel = document.getElementById('theme-label');
    const body = document.body;

    function applyTheme(isDark) {
        if (isDark) {
            body.classList.add('dark-theme');
            themeLabel.textContent = "Tema Escuro";
        } else {
            body.classList.remove('dark-theme');
            themeLabel.textContent = "Tema Claro";
        }
    }

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        themeSwitch.checked = true;
        applyTheme(true);
    } else {
        themeSwitch.checked = false;
        applyTheme(false);
    }

    themeSwitch.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('theme', 'dark');
            applyTheme(true);
        } else {
            localStorage.setItem('theme', 'light');
            applyTheme(false);
        }
    });
});
    // Inicializa datepicker
    flatpickr(".datepicker", {
        enableTime: true,
        dateFormat: "d/m/Y H:i",
        time_24hr: true,
        allowInput: true
    });

    // Carrega checklist quando plano é selecionado
    document.getElementById('plano_id')?.addEventListener('change', function() {
        const planoId = this.value;
        const checklistContainer = document.getElementById('checklist-container');
        
        if (!planoId) {
            checklistContainer.innerHTML = '<div class="alert alert-info">Selecione um plano para carregar o checklist</div>';
            return;
        }
        
        fetch(`api/treinamentos.php?action=get_checklist&plano_id=${planoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.checklist.length > 0) {
                    let html = '<h6 class="mt-3 mb-2">Checklist do Plano</h6>';
                    data.checklist.forEach(item => {
                        html += `
                            <div class="checklist-item">
                                <input type="checkbox" name="checklist_items[]" value="${item.id}" id="checklist_${item.id}">
                                <label for="checklist_${item.id}" class="item-text">${item.item}</label>
                            </div>
                        `;
                    });
                    checklistContainer.innerHTML = html;
                } else {
                    checklistContainer.innerHTML = '<div class="alert alert-info">Nenhum item de checklist encontrado para este plano</div>';
                }
            });
    });

// Código removido - duplicado com setupClienteFilter()


function setupClienteFilter() {
    const clienteFilter = document.getElementById('cliente_filter');
    const clienteResults = document.getElementById('cliente_results');
    const clienteSelect = document.getElementById('cliente_id');
    
    if (!clienteFilter || !clienteResults || !clienteSelect) return;
    
    // Dados dos clientes
    const clientes = [
        <?php foreach ($clientes_list as $cliente): ?>
        {            id: '<?= $cliente['id'] ?>',
            text: '<?= addslashes(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>',
            contrato: '<?= addslashes($cliente['contrato'] ?? '') ?>',
            nome: '<?= addslashes($cliente['nome']) ?>'
        },
        <?php endforeach; ?>
    ];
    
    // Mostrar TODOS os resultados ao focar no campo
    clienteFilter.addEventListener('focus', function() {
        mostrarResultados(clientes); // Mostra todos inicialmente
        clienteResults.style.display = 'block';
    });
    
    // Filtrar enquanto digita
    clienteFilter.addEventListener('input', function() {
        const termo = this.value.toLowerCase().trim();
        
        if (!termo) {
            mostrarResultados(clientes); // Mostra todos se campo vazio
        } else {
            const resultados = clientes.filter(cliente => {
                return cliente.text.toLowerCase().includes(termo) || 
                       cliente.contrato.toLowerCase().includes(termo) || 
                       cliente.nome.toLowerCase().includes(termo);
            });
            mostrarResultados(resultados);
        }
    });
    
    // Selecionar um resultado
    clienteResults.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            const clienteId = e.target.dataset.value;
            const cliente = clientes.find(c => c.id == clienteId);
            
            if (cliente) {
                clienteFilter.value = cliente.text;
                clienteSelect.value = cliente.id;
                clienteResults.style.display = 'none';
                
                // Dispara o evento change para carregar o plano
                const event = new Event('change');
                clienteSelect.dispatchEvent(event);
            }
        }
    });
    
    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-select')) {
            clienteResults.style.display = 'none';
        }
    });
    
    // Função para mostrar resultados (todos ou filtrados)
    function mostrarResultados(resultados) {
        clienteResults.innerHTML = '';
        
        if (resultados.length === 0) {
            clienteResults.innerHTML = '<div class="dropdown-item no-results">Nenhum cliente encontrado</div>';
            return;
        }
        
        const termo = clienteFilter.value.toLowerCase().trim();
        
        resultados.forEach(cliente => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.dataset.value = cliente.id;
            
            // Destacar apenas se estiver filtrando
            item.innerHTML = termo ? highlightMatch(cliente.text, termo) : cliente.text;
            
            clienteResults.appendChild(item);
        });
    }
    
    // Função para destacar o texto encontrado
    function highlightMatch(text, term) {
        const index = text.toLowerCase().indexOf(term);
        if (index >= 0) {
            return text.substring(0, index) + 
                   '<span class="highlight">' + text.substring(index, index + term.length) + '</span>' + 
                   text.substring(index + term.length);
        }
        return text;
    }
    
    // Inicializar com valor selecionado
    if (clienteSelect.value) {
        const selected = clientes.find(c => c.id == clienteSelect.value);
        if (selected) {
            clienteFilter.value = selected.text;
        }
    }
}

    // Manipula o cadastro de cliente rápido
// Manipula o cadastro de cliente rápido  // <- Duplicado
window.addEventListener('message', function(e) {
    if (e.data.type === 'clienteCadastrado') {
        const cliente = e.data.cliente;
        
        // Atualiza o select original (hidden)
        const selectCliente = document.getElementById('cliente_id');
        const option = document.createElement('option');
        option.value = cliente.id;
        option.textContent = (cliente.contrato ? cliente.contrato + ' - ' : '') + cliente.nome;
        option.dataset.plano = cliente.plano || '';
        selectCliente.appendChild(option);
        selectCliente.value = cliente.id;
        
        // Atualiza o input de busca
        const inputCliente = document.getElementById('cliente_filter');
        inputCliente.value = option.textContent;
        
        // Dispara o evento change para carregar o plano
        const event = new Event('change');
        selectCliente.dispatchEvent(event);
        
        // Fecha o modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cadastroClienteModal'));
        modal.hide();
        
        showToast('Cliente cadastrado com sucesso e selecionado!', 'success');
    }
});

    // Reseta o iframe quando o modal é fechado
    document.getElementById('cadastroClienteModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('iframeCadastroCliente').src = '../chamados/cadastrar_cliente_rapido.php';
    });

    // Inicialização quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function() {
        setupClienteFilter();
        
        // Função para mostrar notificação
        window.showToast = function(message, type = 'success') {
            const toastContainer = document.createElement('div');
            toastContainer.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
            toastContainer.style.zIndex = '1100';
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
            
            setTimeout(() => {
                toastContainer.remove();
            }, 5000);
        };
    });



    // Validar e formatar o input de tempo com feedback visual
document.querySelectorAll('.time-input').forEach(input => {
    input.addEventListener('blur', function() {
        const originalValue = this.value.trim();
        let isValid = true;
        let feedbackElement = this.nextElementSibling;
        
        // Criar elemento de feedback se não existir
        if (!feedbackElement || !feedbackElement.classList.contains('invalid-feedback')) {
            feedbackElement = document.createElement('div');
            feedbackElement.className = 'invalid-feedback';
            this.parentNode.appendChild(feedbackElement);
        }
        
        // Verificar se está vazio
        if (!originalValue) {
            this.value = '01:00'; // Valor padrão
            return;
        }
        
        // Verificar formato básico
        if (!/^[0-9]{1,2}(:[0-5]?[0-9]?)?$/.test(originalValue)) {
            isValid = false;
            feedbackElement.textContent = 'Formato inválido. Use HH:MM (ex: 01:30)';
        } else {
            // Separar horas e minutos
            const parts = originalValue.split(':');
            let hours = parseInt(parts[0]) || 0;
            let minutes = parseInt(parts[1]) || 0;
            
            // Validar limites
            if (hours < 0 || hours > 23) {
                isValid = false;
                feedbackElement.textContent = 'Horas devem estar entre 00 e 23';
            } else if (minutes < 0 || minutes > 59) {
                isValid = false;
                feedbackElement.textContent = 'Minutos devem estar entre 00 e 59';
            }
            
            // Formatando corretamente
            if (isValid) {
                this.value = 
                    hours.toString().padStart(2, '0') + ':' + 
                    minutes.toString().padStart(2, '0');
                feedbackElement.textContent = '';
            }
        }
        
        // Aplicar estilos de validação
        if (!isValid) {
            this.classList.add('is-invalid');
            this.value = '01:00'; // Valor padrão se inválido
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Validar antes do envio do formulário
    input.form?.addEventListener('submit', function(e) {
        input.dispatchEvent(new Event('blur'));
        if (input.classList.contains('is-invalid')) {
            e.preventDefault();
            input.focus();
        }
    });
});



// Adicionar estilos para validação
const style = document.createElement('style');
style.textContent = `
    .is-invalid {
        border-color: #dc3545 !important;
    }
    .invalid-feedback {
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
`;
document.head.appendChild(style);
    // Função para marcar/desmarcar todos os status
function toggleAllStatus(checkbox) {
    const statusCheckboxes = document.querySelectorAll('.status-checkbox');
    const selectAll = checkbox.checked;
    
    statusCheckboxes.forEach(cb => {
        cb.checked = selectAll;
    });
    
    // Desmarca "Padrão" se "Todos" for selecionado
    if (selectAll) {
        document.getElementById('default-status').checked = false;
    }
}

// Função para selecionar os status padrão (1 e 2)
function selectDefaultStatus() {
    const statusCheckboxes = document.querySelectorAll('.status-checkbox');
    const defaultStatus = document.getElementById('default-status').checked;
    
    if (defaultStatus) {
        // Marca apenas os status 1 e 2
        statusCheckboxes.forEach(cb => {
            cb.checked = (cb.value === '1' || cb.value === '2');
        });
        
        // Desmarca "Todos" se "Padrão" for selecionado
        document.getElementById('select-all-status').checked = false;
    }
}

// Função para verificar se todos os status estão selecionados
function checkAllStatusSelected() {
    const statusCheckboxes = document.querySelectorAll('.status-checkbox');
    const allChecked = Array.from(statusCheckboxes).every(cb => cb.checked);
    document.getElementById('select-all-status').checked = allChecked;
}

// Função para verificar se os status padrão estão selecionados
function checkDefaultStatusSelected() {
    const status1 = document.querySelector('input.status-checkbox[value="1"]');
    const status2 = document.querySelector('input.status-checkbox[value="2"]');
    const otherStatus = document.querySelectorAll('input.status-checkbox:not([value="1"]):not([value="2"])');
    
    const defaultChecked = status1.checked && status2.checked;
    const othersUnchecked = Array.from(otherStatus).every(cb => !cb.checked);
    
    document.getElementById('default-status').checked = defaultChecked && othersUnchecked;
}


document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover focus'
        })
    })
});

// Adiciona eventos aos checkboxes de status
document.addEventListener('DOMContentLoaded', function() {
    const statusCheckboxes = document.querySelectorAll('.status-checkbox');
    
    statusCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            checkAllStatusSelected();
            checkDefaultStatusSelected();
        });
    });
    
    // Inicializa os estados dos checkboxes
    const urlParams = new URLSearchParams(window.location.search);
    const statusParams = urlParams.getAll('status[]');
    
    // Se não houver parâmetros de status na URL, marca os padrões
    if (statusParams.length === 0) {
        document.getElementById('default-status').checked = true;
        selectDefaultStatus();
    } else {
        checkAllStatusSelected();
        checkDefaultStatusSelected();
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-comissao');
    const comissaoValue = document.querySelector('.comissao-value');
    const valorHoraValue = document.querySelector('.valor-hora-value');
    const realComissao = document.querySelector('.comissao-real-values');
    const realValorHora = document.querySelector('.valor-hora-real-values');
    
    // Verificar se há um estado salvo no localStorage
    const comissaoVisible = localStorage.getItem('comissaoVisible') === 'true';
    
    // Aplicar o estado inicial
    if (comissaoVisible) {
        toggleBtn.textContent = 'visibility';
        comissaoValue.textContent = realComissao.textContent;
        valorHoraValue.textContent = realValorHora.textContent;
    } else {
        toggleBtn.textContent = 'visibility_off';
        comissaoValue.textContent = '•••••';
        valorHoraValue.textContent = '•••/h';
    }
    
    // Adicionar evento de clique
    toggleBtn.addEventListener('click', function() {
        const isVisible = toggleBtn.textContent === 'visibility';
        
        if (isVisible) {
            // Ocultar valores
            toggleBtn.textContent = 'visibility_off';
            comissaoValue.textContent = '•••••';
            valorHoraValue.textContent = '•••/h';
            localStorage.setItem('comissaoVisible', 'false');
        } else {
            // Mostrar valores
            toggleBtn.textContent = 'visibility';
            comissaoValue.textContent = realComissao.textContent;
            valorHoraValue.textContent = realValorHora.textContent;
            localStorage.setItem('comissaoVisible', 'true');
        }
    });
});
</script>
</body>
</html>