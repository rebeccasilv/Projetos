// Servidor principal da aplicação
const express = require('express');
const cors = require('cors');
const sequelize = require('./config/database');
const taskRoutes = require('./routes/tasks');

const app = express();
// PORTA CONFIGURÁVEL - pode usar variável de ambiente ou padrão 3000
const PORT = process.env.PORT || 3000;

// Middlewares
app.use(cors({
  origin: '*', // Permite qualquer origem (para desenvolvimento)
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(express.json()); // Parse de JSON no body das requisições

// Rotas
app.use('/api/tasks', taskRoutes);

// Rota de teste para verificar se a API está funcionando
app.get('/', (req, res) => {
  res.json({ 
    message: '🚀 API do Projeto CRUD funcionando!', 
    timestamp: new Date(),
    endpoints: {
      tasks: '/api/tasks',
      health: '/api/health'
    }
  });
});

app.get('/api/health', (req, res) => {
  res.json({ 
    status: 'OK',
    message: 'API funcionando corretamente!', 
    timestamp: new Date() 
  });
});

// Função para inicializar o servidor e o banco de dados
async function startServer() {
  try {
    console.log('🔄 Iniciando servidor...');
    
    // Conecta ao banco e cria as tabelas se não existirem
    await sequelize.authenticate();
    console.log('✅ Conexão com banco de dados estabelecida!');
    
    await sequelize.sync(); // Cria tabelas baseadas nos modelos
    console.log('✅ Tabelas sincronizadas!');
    
    // Inicia o servidor
    app.listen(PORT, () => {
      console.log('');
      console.log('🎉 ===================================');
      console.log(`🚀 Servidor rodando na porta ${PORT}`);
      console.log(`📡 API disponível em: http://localhost:${PORT}`);
      console.log(`🔍 Teste: http://localhost:${PORT}/api/health`);
      console.log('🎉 ===================================');
      console.log('');
      console.log('📋 Rotas disponíveis:');
      console.log(`   GET    http://localhost:${PORT}/api/tasks`);
      console.log(`   POST   http://localhost:${PORT}/api/tasks`);
      console.log(`   PUT    http://localhost:${PORT}/api/tasks/:id`);
      console.log(`   DELETE http://localhost:${PORT}/api/tasks/:id`);
      console.log('');
      console.log('🛑 Para parar o servidor: Ctrl + C');
    });
  } catch (error) {
    console.error('❌ Erro ao iniciar servidor:', error);
    process.exit(1);
  }
}

// Tratamento de erros não capturados
process.on('unhandledRejection', (err) => {
  console.error('❌ Erro não tratado:', err);
  process.exit(1);
});

startServer();