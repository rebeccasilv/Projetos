# 📋 Lista de Tarefas CRUD - Sistema Full-Stack

[![Node.js](https://img.shields.io/badge/Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white)](https://nodejs.org/)
[![Express.js](https://img.shields.io/badge/Express.js-000000?style=for-the-badge&logo=express&logoColor=white)](https://expressjs.com/)
[![SQLite](https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white)](https://sqlite.org/)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

> 🚀 **Sistema completo de gerenciamento de tarefas** desenvolvido com Node.js, Express, Sequelize ORM e SQLite. Interface moderna e responsiva usando JavaScript puro.

---

## 🎯 **Sobre o Projeto**

Este é um sistema CRUD (Create, Read, Update, Delete) completo para gerenciamento de tarefas, ideal para demonstrar conhecimentos em desenvolvimento full-stack. O projeto combina:

- **Backend robusto** com API RESTful
- **Frontend moderno** com design responsivo
- **Banco de dados local** sem complexidade de setup
- **Código limpo** e bem documentado

### 🌟 **Por que este projeto?**

- ✅ **Portfólio**: Demonstra habilidades full-stack completas
- ✅ **Aprendizado**: Excelente para entender conceitos fundamentais
- ✅ **Escalabilidade**: Base sólida para projetos maiores
- ✅ **Simplicidade**: Fácil de entender e modificar

---

## 🚀 **Funcionalidades**

### 📋 **CRUD Completo**
- ➕ **Criar** novas tarefas com título, descrição e categoria
- 📖 **Listar** todas as tarefas com filtros por status
- ✏️ **Atualizar** status das tarefas (pendente/concluída)
- 🗑️ **Deletar** tarefas com confirmação

### 🎨 **Interface Moderna**
- 📱 **Design responsivo** para desktop e mobile
- 🌈 **Gradientes modernos** e animações suaves
- 🔍 **Filtros dinâmicos** por status (todas/pendentes/concluídas)
- ⚡ **Feedback visual** em tempo real

### 🔧 **Tecnologias**
- **Backend**: Node.js + Express.js
- **ORM**: Sequelize (mapeamento objeto-relacional)
- **Banco**: SQLite (arquivo local, sem setup complexo)
- **Frontend**: HTML5 + CSS3 + JavaScript ES6+
- **API**: RESTful com JSON

---

## 📁 **Estrutura do Projeto**

```
projeto-crud/
├── 📂 backend/                 # Servidor Node.js
│   ├── 📂 config/
│   │   └── 📄 database.js      # Configuração do banco SQLite
│   ├── 📂 models/
│   │   └── 📄 Task.js          # Modelo da entidade Tarefa
│   ├── 📂 routes/
│   │   └── 📄 tasks.js         # Rotas da API (CRUD)
│   ├── 📄 server.js            # Servidor principal
│   ├── 📄 package.json         # Dependências do backend
│   └── 📄 database.sqlite      # Banco de dados (criado automaticamente)
├── 📂 frontend/                # Interface do usuário
│   ├── 📄 index.html           # Estrutura da página
│   ├── 📄 style.css            # Estilos modernos
│   └── 📄 script.js            # Lógica JavaScript
└── 📄 README.md                # Este arquivo
```

---

## ⚡ **Instalação e Execução**

### 🔧 **Pré-requisitos**
- [Node.js](https://nodejs.org/) (versão 14 ou superior)
- NPM (incluído com Node.js)
- Navegador web moderno

### 🚀 **Passo a Passo**

#### 1. **Clone/Baixe o projeto**
```bash
# Se estiver no Git
git clone [url-do-repositorio]

# Ou baixe e extraia o ZIP
```

#### 2. **Instale as dependências do backend**
```bash
cd backend
npm install
```

#### 3. **Inicie o servidor**
```bash
# Modo desenvolvimento (auto-reload)
npm run dev

# Ou modo produção
npm start
```

#### 4. **Abra o frontend**
```bash
# Navegue para a pasta frontend
cd ../frontend

# Abra index.html no navegador
# Ou use um servidor local (recomendado)
python -m http.server 8080
```

#### 5. **Acesse a aplicação**
- **Frontend**: `http://localhost:8080` (ou arquivo local)
- **API**: `http://localhost:3000`
- **Teste da API**: `http://localhost:3000/api/health`

---

## 🔌 **Documentação da API**

### 📍 **Base URL**
```
http://localhost:3000/api
```

### 🛣️ **Endpoints**

#### **📋 Listar todas as tarefas**
```http
GET /tasks
```

**Resposta:**
```json
[
  {
    "id": 1,
    "title": "Estudar JavaScript",
    "description": "Revisar conceitos de ES6+",
    "category": "Estudo",
    "status": "pendente",
    "createdAt": "2024-01-15T10:30:00.000Z",
    "updatedAt": "2024-01-15T10:30:00.000Z"
  }
]
```

#### **➕ Criar nova tarefa**
```http
POST /tasks
Content-Type: application/json
```

**Body:**
```json
{
  "title": "Nova tarefa",
  "description": "Descrição opcional",
  "category": "Trabalho"
}
```

**Resposta:**
```json
{
  "id": 2,
  "title": "Nova tarefa",
  "description": "Descrição opcional",
  "category": "Trabalho",
  "status": "pendente",
  "createdAt": "2024-01-15T11:00:00.000Z",
  "updatedAt": "2024-01-15T11:00:00.000Z"
}
```

#### **✏️ Atualizar tarefa**
```http
PUT /tasks/:id
Content-Type: application/json
```

**Body:**
```json
{
  "status": "concluida"
}
```

#### **🗑️ Deletar tarefa**
```http
DELETE /tasks/:id
```

**Resposta:**
```json
{
  "message": "Tarefa deletada com sucesso",
  "id": 1
}
```

#### **🔍 Buscar tarefa específica**
```http
GET /tasks/:id
```

---

## 🌍 **Casos de Uso na Vida Real**

### 🏢 **1. Sistema de Gestão Empresarial**

**Cenário**: Pequena empresa precisa organizar tarefas de equipe

**Adaptações possíveis**:
- Adicionar campo `responsavel` nas tarefas
- Criar categorias por departamento
- Implementar notificações por email
- Dashboard com métricas de produtividade

```javascript
// Exemplo de extensão
const Task = sequelize.define('Task', {
  title: DataTypes.STRING,
  assignedTo: DataTypes.STRING,     // Responsável
  priority: DataTypes.ENUM('baixa', 'media', 'alta'),
  deadline: DataTypes.DATE,         // Prazo
  department: DataTypes.STRING      // Departamento
});
```

### 📚 **2. Plataforma Educacional**

**Cenário**: Escola quer organizar atividades dos alunos

**Adaptações possíveis**:
- Campo `materia` para organizar por disciplina
- Sistema de pontuação/notas
- Calendário de entregas
- Portal do aluno e professor

```javascript
// Exemplo de modelo para educação
const Assignment = sequelize.define('Assignment', {
  title: DataTypes.STRING,
  subject: DataTypes.STRING,        // Matéria
  studentId: DataTypes.INTEGER,     // ID do aluno
  points: DataTypes.INTEGER,        // Pontuação
  dueDate: DataTypes.DATE          // Data de entrega
});
```

### 🏥 **3. Sistema Hospitalar**

**Cenário**: Hospital precisa organizar tarefas de enfermagem

**Adaptações possíveis**:
- Prioridade médica (urgente/normal)
- Turno responsável
- Número do quarto/paciente
- Status médico detalhado

```javascript
// Exemplo para área da saúde
const MedicalTask = sequelize.define('MedicalTask', {
  task: DataTypes.STRING,
  patientRoom: DataTypes.STRING,
  shift: DataTypes.ENUM('manha', 'tarde', 'noite'),
  priority: DataTypes.ENUM('rotina', 'urgente', 'emergencia'),
  nurseId: DataTypes.INTEGER
});
```

### 🛒 **4. E-commerce**

**Cenário**: Loja online gerencia pedidos e estoque

**Adaptações possíveis**:
- Status de pedidos (processando/enviado/entregue)
- Controle de estoque
- Tarefas de reposição
- Atendimento ao cliente

```javascript
// Exemplo para e-commerce
const OrderTask = sequelize.define('OrderTask', {
  orderId: DataTypes.STRING,
  customerName: DataTypes.STRING,
  taskType: DataTypes.ENUM('pagamento', 'separacao', 'envio'),
  status: DataTypes.ENUM('pendente', 'processando', 'concluido'),
  priority: DataTypes.INTEGER
});
```

### 🏠 **5. Aplicativo Doméstico**

**Cenário**: Família organiza tarefas domésticas

**Adaptações possíveis**:
- Responsável por tarefa
- Recorrência (diária/semanal)
- Sistema de recompensas
- Lista de compras integrada

```javascript
// Exemplo para uso doméstico
const HouseTask = sequelize.define('HouseTask', {
  task: DataTypes.STRING,
  assignedTo: DataTypes.STRING,
  recurring: DataTypes.BOOLEAN,     // Tarefa recorrente
  frequency: DataTypes.STRING,      // Frequência
  room: DataTypes.STRING           // Cômodo
});
```

---

## 🔧 **Como Usar a API em Projetos Reais**

### 📱 **1. Aplicativo Mobile (React Native)**

```javascript
// Exemplo de integração com React Native
const TaskService = {
  baseURL: 'https://sua-api.herokuapp.com/api',
  
  async getTasks() {
    const response = await fetch(`${this.baseURL}/tasks`);
    return response.json();
  },
  
  async createTask(task) {
    return fetch(`${this.baseURL}/tasks`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(task)
    });
  }
};
```

### 🌐 **2. Integração com Frontend Frameworks**

**Vue.js:**
```javascript
// store/tasks.js
export const state = () => ({
  tasks: []
})

export const actions = {
  async fetchTasks({ commit }) {
    const tasks = await this.$axios.$get('/api/tasks')
    commit('SET_TASKS', tasks)
  }
}
```

**React:**
```javascript
// hooks/useTasks.js
const useTasks = () => {
  const [tasks, setTasks] = useState([]);
  
  const loadTasks = async () => {
    const response = await fetch('/api/tasks');
    const data = await response.json();
    setTasks(data);
  };
  
  return { tasks, loadTasks };
};
```

### 🔗 **3. Webhook/Integrações**

```javascript
// Exemplo de webhook para Slack
router.post('/tasks', async (req, res) => {
  const task = await Task.create(req.body);
  
  // Notificar no Slack
  await axios.post(process.env.SLACK_WEBHOOK, {
    text: `Nova tarefa criada: ${task.title}`
  });
  
  res.json(task);
});
```

### 📊 **4. Dashboard/Analytics**

```javascript
// Rota para estatísticas
router.get('/stats', async (req, res) => {
  const stats = await Task.findAll({
    attributes: [
      'status',
      [sequelize.fn('COUNT', sequelize.col('id')), 'count']
    ],
    group: ['status']
  });
  
  res.json(stats);
});
```

---

## 🎨 **Personalizações Sugeridas**

### 🔒 **1. Autenticação**
```bash
npm install jsonwebtoken bcryptjs
```

### 📧 **2. Notificações**
```bash
npm install nodemailer
```

### 📊 **3. Relatórios**
```bash
npm install excel4node
```

### 🔍 **4. Busca Avançada**
```bash
npm install fuse.js
```

### ⏰ **5. Agendamento**
```bash
npm install node-cron
```

---

## 🚀 **Deploy em Produção**

### **Heroku (Gratuito)**
```bash
# Instalar Heroku CLI
npm install -g heroku

# Login e criar app
heroku login
heroku create minha-lista-tarefas

# Deploy
git push heroku main
```

### **Railway**
```bash
# Conectar conta GitHub
# Deploy automático via interface
```

### **Netlify (Frontend)**
```bash
# Fazer build do frontend
# Fazer upload da pasta frontend
```

---

## 🔍 **Monitoramento e Debug**

### **Logs Detalhados**
```javascript
// middleware/logger.js
const logger = (req, res, next) => {
  console.log(`${new Date().toISOString()} - ${req.method} ${req.path}`);
  next();
};
```

### **Health Check**
```javascript
// Verificar saúde da aplicação
app.get('/health', (req, res) => {
  res.json({
    status: 'OK',
    timestamp: new Date(),
    uptime: process.uptime(),
    database: 'Connected'
  });
});
```

---

## 🤝 **Contribuindo**

1. **Fork** o projeto
2. **Crie** uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
3. **Commit** suas mudanças (`git commit -m 'Add: MinhaFeature'`)
4. **Push** para a branch (`git push origin feature/MinhaFeature`)
5. **Abra** um Pull Request

---

## 📝 **Licença**

Este projeto está sob a licença **MIT**. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

## 👨‍💻 **Autor**

**Seu Nome**
- LinkedIn: [seu-linkedin](https://linkedin.com/in/seu-perfil)
- GitHub: [seu-github](https://github.com/seu-usuario)
- Email: seu.email@exemplo.com

---

## 🙏 **Agradecimentos**

- [Express.js](https://expressjs.com/) - Framework web rápido e minimalista
- [Sequelize](https://sequelize.org/) - ORM moderno para Node.js
- [SQLite](https://sqlite.org/) - Banco de dados leve e eficiente
- Comunidade **JavaScript** pelo conhecimento compartilhado

---

## 📚 **Recursos Adicionais**

### **Documentação**
- [Node.js Docs](https://nodejs.org/docs/)
- [Express.js Guide](https://expressjs.com/guide/)
- [Sequelize Documentation](https://sequelize.org/docs/)

### **Tutoriais Relacionados**
- [REST API Best Practices](https://restfulapi.net/)
- [SQL Tutorial](https://www.w3schools.com/sql/)
- [JavaScript Modern Features](https://developer.mozilla.org/docs/Web/JavaScript)

### **Ferramentas Úteis**
- [Postman](https://postman.com/) - Testar APIs
- [DB Browser for SQLite](https://sqlitebrowser.org/) - Visualizar banco
- [VS Code](https://code.visualstudio.com/) - Editor recomendado

---

<div align="center">

**⭐ Se este projeto te ajudou, deixe uma estrela! ⭐**

**📢 Compartilhe com outros desenvolvedores!**

</div>