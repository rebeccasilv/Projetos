// Rotas para operações CRUD das tarefas
const express = require('express');
const router = express.Router();
const Task = require('../models/Task');

// 📋 GET /api/tasks - Listar todas as tarefas
router.get('/', async (req, res) => {
  try {
    console.log('📋 Buscando todas as tarefas...');
    const tasks = await Task.findAll({
      order: [['createdAt', 'DESC']] // Ordena por data de criação (mais recentes primeiro)
    });
    console.log(`✅ ${tasks.length} tarefas encontradas`);
    res.json(tasks);
  } catch (error) {
    console.error('❌ Erro ao buscar tarefas:', error);
    res.status(500).json({ 
      error: 'Erro ao buscar tarefas',
      details: error.message 
    });
  }
});

// 🔍 GET /api/tasks/:id - Buscar tarefa específica
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    console.log(`🔍 Buscando tarefa ID: ${id}`);
    
    const task = await Task.findByPk(id);
    if (!task) {
      return res.status(404).json({ error: 'Tarefa não encontrada' });
    }
    
    res.json(task);
  } catch (error) {
    console.error('❌ Erro ao buscar tarefa:', error);
    res.status(500).json({ 
      error: 'Erro ao buscar tarefa',
      details: error.message 
    });
  }
});

// 📝 POST /api/tasks - Criar nova tarefa
router.post('/', async (req, res) => {
  try {
    const { title, description, category } = req.body;
    console.log('📝 Criando nova tarefa:', { title, description, category });
    
    // Validação básica
    if (!title || title.trim() === '') {
      return res.status(400).json({ error: 'Título é obrigatório' });
    }

    const task = await Task.create({
      title: title.trim(),
      description: description?.trim() || '',
      category: category?.trim() || 'Geral'
    });
    
    console.log('✅ Tarefa criada com ID:', task.id);
    res.status(201).json(task);
  } catch (error) {
    console.error('❌ Erro ao criar tarefa:', error);
    res.status(400).json({ 
      error: 'Erro ao criar tarefa',
      details: error.message 
    });
  }
});

// ✏️ PUT /api/tasks/:id - Atualizar tarefa existente
router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { title, description, category, status } = req.body;
    console.log(`✏️ Atualizando tarefa ID: ${id}`, req.body);

    const task = await Task.findByPk(id);
    if (!task) {
      return res.status(404).json({ error: 'Tarefa não encontrada' });
    }

    // Atualiza apenas os campos fornecidos
    await task.update({
      title: title?.trim() || task.title,
      description: description?.trim() || task.description,
      category: category?.trim() || task.category,
      status: status || task.status
    });

    console.log('✅ Tarefa atualizada com sucesso');
    res.json(task);
  } catch (error) {
    console.error('❌ Erro ao atualizar tarefa:', error);
    res.status(400).json({ 
      error: 'Erro ao atualizar tarefa',
      details: error.message 
    });
  }
});

// 🗑️ DELETE /api/tasks/:id - Deletar tarefa
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    console.log(`🗑️ Deletando tarefa ID: ${id}`);
    
    const task = await Task.findByPk(id);
    if (!task) {
      return res.status(404).json({ error: 'Tarefa não encontrada' });
    }

    await task.destroy();
    console.log('✅ Tarefa deletada com sucesso');
    res.json({ message: 'Tarefa deletada com sucesso', id: parseInt(id) });
  } catch (error) {
    console.error('❌ Erro ao deletar tarefa:', error);
    res.status(500).json({ 
      error: 'Erro ao deletar tarefa',
      details: error.message 
    });
  }
});

module.exports = router;