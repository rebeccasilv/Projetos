// Configuração da API
const API_BASE_URL = 'http://localhost:3000/api';

// Elementos DOM
const taskForm = document.getElementById('taskForm');
const tasksContainer = document.getElementById('tasksContainer');
const loadingElement = document.getElementById('loading');
const emptyState = document.getElementById('emptyState');
const filterButtons = document.querySelectorAll('.filter-btn');

// Estado global da aplicação
let tasks = [];
let currentFilter = 'all';

// 🚀 Inicialização da aplicação
document.addEventListener('DOMContentLoaded', function() {
    console.log('📱 Aplicação iniciando...');
    loadTasks();
    setupEventListeners();
});

// 🎯 Configurar event listeners
function setupEventListeners() {
    // Formulário de nova tarefa
    taskForm.addEventListener('submit', handleTaskSubmit);
    
    // Botões de filtro
    filterButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const filter = e.target.dataset.filter;
            setActiveFilter(filter);
        });
    });
}

// 📋 Carregar todas as tarefas da API
async function loadTasks() {
    showLoading(true);
    
    try {
        console.log('🔄 Buscando tarefas da API...');
        const response = await fetch(`${API_BASE_URL}/tasks`);
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        tasks = await response.json();
        console.log(`✅ ${tasks.length} tarefas carregadas`);
        
        displayTasks();
    } catch (error) {
        console.error('❌ Erro ao carregar tarefas:', error);
        showError('Erro ao carregar tarefas. Verifique se o servidor está rodando.');
    } finally {
        showLoading(false);
    }
}

// ➕ Criar nova tarefa
async function handleTaskSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const taskData = {
        title: document.getElementById('title').value.trim(),
        description: document.getElementById('description').value.trim(),
        category: document.getElementById('category').value
    };
    
    // Validação básica no frontend
    if (!taskData.title) {
        alert('Por favor, insira um título para a tarefa.');
        return;
    }
    
    try {
        console.log('📤 Enviando nova tarefa:', taskData);
        
        const response = await fetch(`${API_BASE_URL}/tasks`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(taskData)
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Erro ao criar tarefa');
        }
        
        const newTask = await response.json();
        console.log('✅ Tarefa criada:', newTask);
        
        // Limpar formulário
        taskForm.reset();
        
        // Recarregar lista de tarefas
        await loadTasks();
        
        // Feedback visual
        showSuccess('Tarefa adicionada com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro ao criar tarefa:', error);
        alert('Erro ao criar tarefa: ' + error.message);
    }
}

// ✅ Marcar tarefa como concluída/pendente
async function toggleTaskStatus(taskId, currentStatus) {
    const newStatus = currentStatus === 'pendente' ? 'concluida' : 'pendente';
    
    try {
        console.log(`🔄 Alterando status da tarefa ${taskId} para: ${newStatus}`);
        
        const response = await fetch(`${API_BASE_URL}/tasks/${taskId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status: newStatus })
        });
        
        if (!response.ok) {
            throw new Error('Erro ao atualizar tarefa');
        }
        
        console.log('✅ Status atualizado com sucesso');
        await loadTasks(); // Recarregar lista
        
    } catch (error) {
        console.error('❌ Erro ao atualizar status:', error);
        alert('Erro ao atualizar tarefa: ' + error.message);
    }
}

// 🗑️ Deletar tarefa
async function deleteTask(taskId) {
    if (!confirm('Tem certeza que deseja deletar esta tarefa?')) {
        return;
    }
    
    try {
        console.log(`🗑️ Deletando tarefa ${taskId}`);
        
        const response = await fetch(`${API_BASE_URL}/tasks/${taskId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            throw new Error('Erro ao deletar tarefa');
        }
        
        console.log('✅ Tarefa deletada com sucesso');
        await loadTasks(); // Recarregar lista
        
        showSuccess('Tarefa deletada com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro ao deletar tarefa:', error);
        alert('Erro ao deletar tarefa: ' + error.message);
    }
}

// 🎨 Exibir tarefas na interface
function displayTasks() {
    const filteredTasks = filterTasks(tasks, currentFilter);
    
    if (filteredTasks.length === 0) {
        tasksContainer.style.display = 'none';
        emptyState.style.display = 'block';
        return;
    }
    
    tasksContainer.style.display = 'block';
    emptyState.style.display = 'none';
    
    tasksContainer.innerHTML = filteredTasks.map(task => createTaskHTML(task)).join('');
    
    // Adicionar event listeners aos botões das tarefas
    addTaskEventListeners();
}

// 🏗️ Criar HTML de uma tarefa
function createTaskHTML(task) {
    const isCompleted = task.status === 'concluida';
    const createdDate = new Date(task.createdAt).toLocaleDateString('pt-BR');
    
    return `
        <div class="task-card ${isCompleted ? 'completed' : ''}" data-task-id="${task.id}">
            <div class="task-header">
                <h3 class="task-title">${escapeHtml(task.title)}</h3>
                <span class="task-category">${escapeHtml(task.category)}</span>
            </div>
            
            ${task.description ? `<p class="task-description">${escapeHtml(task.description)}</p>` : ''}
            
            <div class="task-meta">
                <span>Criado em: ${createdDate}</span>
                <span class="task-status">Status: ${task.status}</span>
            </div>
            
            <div class="task-actions">
                <button class="btn-small ${isCompleted ? 'btn-undo' : 'btn-complete'}" 
                        onclick="toggleTaskStatus(${task.id}, '${task.status}')">
                    ${isCompleted ? '↩️ Reabrir' : '✅ Concluir'}
                </button>
                <button class="btn-small btn-delete" onclick="deleteTask(${task.id})">
                    🗑️ Deletar
                </button>
            </div>
        </div>
    `;
}

// 🔍 Filtrar tarefas
function filterTasks(tasks, filter) {
    switch (filter) {
        case 'pendente':
            return tasks.filter(task => task.status === 'pendente');
        case 'concluida':
            return tasks.filter(task => task.status === 'concluida');
        default:
            return tasks;
    }
}

// 🎯 Definir filtro ativo
function setActiveFilter(filter) {
    currentFilter = filter;
    
    // Atualizar botões ativos
    filterButtons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    
    // Redisplay tasks
    displayTasks();
}

// 📤 Event listeners para botões das tarefas
function addTaskEventListeners() {
    // Os event listeners são adicionados inline no HTML via onclick
    // Isso é feito para simplificar o código neste exemplo
}

// 🔄 Controle de loading
function showLoading(show) {
    loadingElement.style.display = show ? 'flex' : 'none';
    if (!show) {
        tasksContainer.style.display = 'block';
    }
}

// ✅ Exibir mensagem de sucesso
function showSuccess(message) {
    // Implementação simples usando alert
    // Em uma aplicação real, usaria toast notifications
    console.log('✅ Sucesso:', message);
}

// ❌ Exibir mensagem de erro
function showError(message) {
    console.error('❌ Erro:', message);
    alert(message);
}

// 🛡️ Escapar HTML para prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 🔍 Função de debug para verificar estado da aplicação
function debugApp() {
    console.log('🔍 Estado atual da aplicação:');
    console.log('- Tarefas carregadas:', tasks.length);
    console.log('- Filtro atual:', currentFilter);
    console.log('- Tarefas:', tasks);
}

// Disponibilizar função de debug globalmente
window.debugApp = debugApp;