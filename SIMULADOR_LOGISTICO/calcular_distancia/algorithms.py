from models import db  # Certifique-se de importar o db

def calcular_distancia(geom1, geom2):
    # Consulta SQL para calcular distância usando PostGIS
    return db.session.scalar(
        db.text("SELECT ST_Distance(ST_GeomFromEWKT(:geom1), ST_GeomFromEWKT(:geom2))"),
        {"geom1": geom1, "geom2": geom2}
    )