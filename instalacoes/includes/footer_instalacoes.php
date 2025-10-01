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
        const instalacaoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        suporteDropdown.classList.toggle('visible');
        conhecimentoDropdown.classList.remove('visible');
        instalacaoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Instalações
    document.getElementById('trainingButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const instalacaoDropdown = document.getElementById('trainingMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        instalacaoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Conhecimento
    document.getElementById('knowledgeButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const instalacaoDropdown = document.getElementById('trainingMenu');
        const cadastroDropdown = document.getElementById('registerMenu');
        const configContainer = document.getElementById('configContainer');

        conhecimentoDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        instalacaoDropdown.classList.remove('visible');
        cadastroDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Alternar visibilidade do dropdown de Cadastros
    document.getElementById('registerButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        const cadastroDropdown = document.getElementById('registerMenu');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const instalacaoDropdown = document.getElementById('trainingMenu');
        const configContainer = document.getElementById('configContainer');

        cadastroDropdown.classList.toggle('visible');
        suporteDropdown.classList.remove('visible');
        conhecimentoDropdown.classList.remove('visible');
        instalacaoDropdown.classList.remove('visible');
        configContainer.classList.remove('visible');
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(event) {
        const suporteButton = document.getElementById('supportButton');
        const suporteDropdown = document.getElementById('supportMenu');
        const conhecimentoButton = document.getElementById('knowledgeButton');
        const conhecimentoDropdown = document.getElementById('knowledgeMenu');
        const instalacaoButton = document.getElementById('trainingButton');
        const instalacaoDropdown = document.getElementById('trainingMenu');
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
        
        if (!instalacaoDropdown.contains(event.target) && !instalacaoButton.contains(event.target)) {
            instalacaoDropdown.classList.remove('visible');
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
        window.location.href = '/HelpDesk_Nutify/instalacoes/includes/logout.php';
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
        
        fetch(`api/instalacoes.php?action=get_checklist&plano_id=${planoId}`)
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

    // Configura o filtro para o campo de cliente
    function setupClienteFilter() {
        const input = document.getElementById('cliente_filter');
        const options = document.getElementById('cliente_options');
        const select = document.getElementById('cliente_id') || document.getElementById('cliente');

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

    // Mantenha o evento de change do cliente para carregar o plano
    document.getElementById('cliente_id')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const planoId = selectedOption.dataset.plano;
        
        if (planoId && document.getElementById('plano_id')) {
            document.getElementById('plano_id').value = planoId;
            // Dispara o evento change do plano para carregar o checklist
            const event = new Event('change');
            document.getElementById('plano_id').dispatchEvent(event);
        }
        
        // Atualiza o input de filtro
        const input = document.getElementById('cliente_filter');
        if (input) {
            input.value = selectedOption.textContent.trim();
            input.style.textIndent = '0';
            input.style.paddingLeft = '12px';
        }
    });

    // Manipula o cadastro de cliente rápido
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
            
            // Atualiza o input de busca e options
            const inputCliente = document.getElementById('cliente_filter');
            const optionsContainer = document.getElementById('cliente_options');
            
            // Cria novo option na lista dinâmica
            const newOption = document.createElement('div');
            newOption.dataset.value = cliente.id;
            newOption.textContent = (cliente.contrato ? cliente.contrato + ' - ' : '') + cliente.nome;
            optionsContainer.appendChild(newOption);
            
            // Seta o valor no input
            inputCliente.value = newOption.textContent;
            
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
</body>
</html>