// Configuração da conexão com o banco de dados SQLite
const { Sequelize } = require('sequelize');
const path = require('path');

// Caminho absoluto para o arquivo do banco
const dbPath = path.join(__dirname, '..', 'database.sqlite');

// Cria uma instância do Sequelize conectada ao SQLite
const sequelize = new Sequelize({
  dialect: 'sqlite',
  storage: dbPath, // Caminho completo para o arquivo
  logging: console.log, // Mostra SQL queries (útil para debug)
  define: {
    timestamps: true, // Adiciona createdAt e updatedAt
    underscored: false, // Usa camelCase ao invés de snake_case
  }
});

module.exports = sequelize;