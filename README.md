🚚 Simulador Logístico - Otimização de Rotas
Projeto de Backend para Estudos | Primeira Experiência com Banco de Dados

✨Se você está vendo isso, consegui subir o projeto no GitHub! E olha que até semana passada eu nem sabia como integrar um banco de dados...✨

📌 Sobre o Projeto
Este é o meu primeiro projeto integrando Python (Flask) com PostgreSQL! Decidi focar apenas no backend porque queria entender profundamente como:

🗺️ Armazenar localizações geográficas (sim, descobri que coordenadas são números mágicos que o PostGIS entende!)

🤖 Implementar algoritmos de otimização de rotas (Dijkstra me fez suar, mas sobrevivi!)

🔄 Criar uma API funcional que "conversa" com o banco de dados.

Nota de Humildade: Ainda não há interface visual – tudo roda no terminal e via requisições HTTP. Um dia chego lá!

🛠️ Funcionalidades Atuais
✅ API Básica:

GET /locations → Lista todos os pontos de entrega cadastrados (com IDs!).

POST /optimize_route → Recebe IDs de origem/destino e retorna a rota mais curta (sim, eu testei com Postman e até printou "Hello World" na primeira vez!).

✅ Banco de Dados:

Tabela locations armazenando nomes e coordenadas (até aprendi a fazer INSERT manual no pgAdmin!).

🧩 Tecnologias Usadas
Linguagem: Python (e umas 50 xícaras de café)

Framework: Flask (o herói que me salvou de configurar rotas manualmente)

Banco de Dados: PostgreSQL + PostGIS (pra guardar coordenadas sem virar um meme)

Ferramentas: Git, VS Code, Postman (e muita paciência com erros 500)

🚀 Primeiros Passos com Banco de Dados
Aqui está o que aprendi (e quase me fez chorar):

PostgreSQL ≠ MySQL → Descobri isso na marra quando o código não funcionou.

PostGIS é mágico → ST_Distance calcula distâncias entre pontos geográficos (e eu achando que era só subtrair números!).

IDs são importantes → Descobrir que cada registro tem um ID único foi um "PQP" moment.

⚙️ Como Rodar Localmente
(Requisito: Ter mais coragem do que eu tive no primeiro commit!)

Clone o Repositório:

bash
Copy
git clone https://github.com/rebeccasilv/Projetos)  
Instale as Dependências:

bash
Copy
pip install -r requirements.txt  
Configure o Banco de Dados:

Crie um banco simulador_logistico no pgAdmin.

Popule a tabela locations com:

sql
Copy
INSERT INTO locations (name, geom) VALUES  
('Centro', '-46.6339, -23.5505'),  
('Pinheiros', '-46.6972, -23.5656');  
Inicie o Servidor Flask:

bash
Copy
python app.py  


📝 Próximos Passos (Sonhos de Júnior)
Adicionar frontend básico com mapas (OpenStreetMap, talvez?).

Implementar autenticação de usuários (quem sabe não vira um TCC?).

Aprender a debugar sem xingar o computador em 2 idiomas.

Feito com 💜 (e um pouco de desespero) por Rebeca Silva (rebeccasilv) – uma iniciante que agora sabe que COMMIT não é só algo que você faz num relacionamento.

👀Se você é sênior e está lendo isso: Sim, aceito dicas! (E mentoria, se possível) 😅

👉 Nota da Autora:
Esse projeto é parte da minha jornada para entender backend. Se encontrar um bug, provavelmente já sei que existe – mas aceito ajuda no Discord: (beccasilva)
