from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy import Column, Integer, String
from geoalchemy2 import Geometry
from flask_sqlalchemy import SQLAlchemy
    

Base = declarative_base()  # <-- Defina a Base aqui

class Location(Base):
    __tablename__ = 'locations'

    id = Column(Integer, primary_key=True)
    name = Column(String(100), nullable=False)
    coordinates = Column(Geometry(geometry_type='POINT', srid=4326))

db = SQLAlchemy()

class Location(db.Model):
    __tablename__ = 'locations'
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100))
    # Usaremos texto simples por enquanto (sem PostGIS)
    geom = db.Column(db.String(50))  # Ex: "-46.6339, -23.5505"