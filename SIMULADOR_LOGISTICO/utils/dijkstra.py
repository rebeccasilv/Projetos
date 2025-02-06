import sys
import os

# Adicione o caminho absoluto do projeto ao sys.path
project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(project_root)


import heapq
from math import radians, sin, cos, sqrt, atan2
from sqlalchemy import func
from models.delivery import Location
from database.init_db import session  # Importe a sessão do banco de dados


def haversine(lat1, lon1, lat2, lon2):
    R = 6371  # Raio da Terra em quilômetros
    dLat = radians(lat2 - lat1)
    dLon = radians(lon2 - lon1)
    lat1, lat2 = radians(lat1), radians(lat2)
    a = sin(dLat/2)**2 + cos(lat1)*cos(lat2)*sin(dLon/2)**2
    c = 2 * atan2(sqrt(a), sqrt(1-a))
    return R * c  # Distância em quilômetros

def build_graph():
    # Busca todas as localizações do banco de dados
    locations = session.query(
        Location.id,
        func.ST_Y(Location.coordinates).label('latitude'),  # Latitude
        func.ST_X(Location.coordinates).label('longitude')   # Longitude
    ).all()

    # Cria o grafo (dicionário de dicionários)
    graph = {}
    for loc in locations:
        graph[loc.id] = {}
        for other_loc in locations:
            if loc.id != other_loc.id:
                # Calcula a distância usando Haversine
                distance = haversine(
                    loc.latitude, loc.longitude,
                    other_loc.latitude, other_loc.longitude
                )
                graph[loc.id][other_loc.id] = distance
    return graph


def dijkstra(graph, start_id, end_id):
    # Verifica se os IDs existem no grafo
    if start_id not in graph or end_id not in graph:
        return (float('inf'), [])

    # Fila prioritária: (distância acumulada, nó atual, caminho)
    queue = [(0, start_id, [])]
    seen = set()  # Nós já visitados

    while queue:
        cost, current_id, path = heapq.heappop(queue)

        if current_id not in seen:
            seen.add(current_id)
            path = path + [current_id]

            # Se chegou ao destino, retorna o custo e caminho
            if current_id == end_id:
                return (cost, path)

            # Explora os vizinhos
            for neighbor_id, distance in graph[current_id].items():
                if neighbor_id not in seen:
                    heapq.heappush(queue, (cost + distance, neighbor_id, path))

    return (float('inf'), [])  # Caso não encontre caminho


if __name__ == "__main__":
    # Constroi o grafo
    graph = build_graph()

    # Exemplo: IDs do Ponto A e Ponto B (consulte seu banco de dados)
    start_id = 1
    end_id = 2

    # Calcula a rota
    distance, path = dijkstra(graph, start_id, end_id)

    print(f"Distância mais curta: {distance} km")
    print(f"Caminho: {path}")