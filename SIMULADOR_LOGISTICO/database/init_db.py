import sys
import os

# Adicione o caminho da pasta raiz ao Python
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from models.delivery import Location, Base

# String de conexão (substitua com suas credenciais)
DATABASE_URI = "postgresql+psycopg2://postgres:%40Conta123@localhost:5432/logistic_simulator"

# Configurar engine e sessão
engine = create_engine(DATABASE_URI)
Session = sessionmaker(bind=engine)
session = Session()

# Criar tabelas no banco de dados
Base.metadata.create_all(engine)

# Adicionar dados iniciais (exemplo)
locations = [
    Location(name="Ponto A", coordinates="SRID=4326;POINT(-46.6333 -23.5505)"),
    Location(name="Ponto B", coordinates="SRID=4326;POINT(-43.1729 -22.9068)"),
]

session.add_all(locations)
session.commit()
print("✅ Banco de dados inicializado com sucesso!")