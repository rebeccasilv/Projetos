// Modelo da tabela de tarefas usando Sequelize ORM
const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');

// Define o modelo Task (Tarefa) com seus campos e validações
const Task = sequelize.define('Task', {
  id: {
    type: DataTypes.INTEGER,
    primaryKey: true,
    autoIncrement: true // ID auto-incrementável
  },
  title: {
    type: DataTypes.STRING(100),
    allowNull: false, // Campo obrigatório
    validate: {
      notEmpty: {
        msg: 'Título não pode estar vazio'
      },
      len: {
        args: [1, 100],
        msg: 'Título deve ter entre 1 e 100 caracteres'
      }
    }
  },
  description: {
    type: DataTypes.TEXT,
    allowNull: true // Campo opcional
  },
  category: {
    type: DataTypes.STRING(50),
    allowNull: true,
    defaultValue: 'Geral' // Valor padrão se não especificado
  },
  status: {
    type: DataTypes.ENUM('pendente', 'concluida'),
    defaultValue: 'pendente', // Status padrão para novas tarefas
    validate: {
      isIn: {
        args: [['pendente', 'concluida']],
        msg: 'Status deve ser pendente ou concluida'
      }
    }
  }
}, {
  tableName: 'tasks', // Nome da tabela no banco
  timestamps: true, // Adiciona createdAt e updatedAt automaticamente
  indexes: [
    {
      fields: ['status'] // Índice para otimizar filtros por status
    },
    {
      fields: ['category'] // Índice para otimizar filtros por categoria
    }
  ]
});

module.exports = Task;