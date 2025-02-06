from flask import Flask, request, jsonify
from utils.dijkstra import build_graph, dijkstra
from database.init_db import session
from models.delivery import Location  
from sqlalchemy import func
import folium
from sqlalchemy import func
from flask import render_template
from models import db
from algorithms import calcular_distancia, dijkstra

def calcular_distancia(geom1, geom2):
    # Consulta SQL para calcular distância usando PostGIS
    return db.session.scalar(
        db.text("SELECT ST_Distance(ST_GeomFromEWKT(:geom1), ST_GeomFromEWKT(:geom2))"),
        {"geom1": geom1, "geom2": geom2}
    )


app = Flask(__name__)

@app.route('/')
def home():
    return "Servidor Flask está funcionando! ✅"

@app.route('/optimize_route', methods=['POST'])
def optimize_route():
    data = request.json
    start_id = data['start_id']
    end_id = data['end_id'] 

    with app.app_context():
        locations = Location.query.all()  # "Location" com L maiúsculo + indentação

    graph = {}
    for loc in locations:
        graph[loc.id] = {}
        for other_loc in locations:
            if loc.id != other_loc.id:
                distance = calcular_distancia(loc.geom, other_loc.geom)
                graph[loc.id][other_loc.id] = distance

    path, cost = dijkstra(graph, start_id, end_id)
    return jsonify({"path": path, "cost": cost})

    # Correção 1: Adicionado dois pontos e indentação correta
    if not start_id or not end_id:
        return jsonify({"error": "start_id e end_id são obrigatórios"}), 400

    try:
        graph = build_graph()
        distance, path = dijkstra(graph, start_id, end_id)

        # Correção 2: Substituído ; por : e corrigida indentação
        if distance == float('inf'):
            return jsonify({"error": "Rota não encontrada"}), 404

        # Correção 3: Usado Location com L maiúsculo
        locations = session.query(Location).filter(Location.id.in_(path)).all()
        
        route_details = [
            {
                "id": loc.id, 
                "name": loc.name, 
                "coordinates": loc.coordinates
            } for loc in locations
        ]

        return jsonify({
            "distance_km": round(distance, 2),
            "path": path,
            "route": route_details
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500

# Correção 4: Indentação correta do bloco main
if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5800)


@app.route('/map/<int:start_id>/<int:end_id>')
def show_map(start_id, end_id):
    try:
        graph = build_graph()
        distance, path = dijkstra(graph, start_id, end_id)
        
        if distance == float('inf'):
            return "Rota não encontrada", 404

        locations = session.query(Location).filter(Location.id.in_(path)).all()

        # Cria o mapa centrado na primeira localização
        first_loc = locations[0]
        lat = session.scalar(func.ST_Y(first_loc.coordinates))
        lon = session.scalar(func.ST_X(first_loc.coordinates))
        m = folium.Map(location=[lat, lon], zoom_start=12)

        # Adiciona marcadores e linha da rota
        route_coords = []
        for loc in locations:
            lat = session.scalar(func.ST_Y(loc.coordinates))
            lon = session.scalar(func.ST_X(loc.coordinates))
            folium.Marker([lat, lon], tooltip=loc.name).add_to(m)
            route_coords.append([lat, lon])

        folium.PolyLine(route_coords, color="blue", weight=2.5).add_to(m)

        return m._repr_html_()  # Retorna o HTML do mapa

    except Exception as e:
        return f"Erro: {str(e)}", 500

@app.route('/')
def home():
    return render_template('index.html')