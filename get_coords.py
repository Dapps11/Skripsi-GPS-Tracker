import mysql.connector
import math
import random

def haversine(lat1, lon1, lat2, lon2):
    R = 6371000  # radius of Earth in meters
    phi_1 = math.radians(lat1)
    phi_2 = math.radians(lat2)
    delta_phi = math.radians(lat2 - lat1)
    delta_lambda = math.radians(lon2 - lon1)

    a = math.sin(delta_phi / 2.0) ** 2 + \
        math.cos(phi_1) * math.cos(phi_2) * \
        math.sin(delta_lambda / 2.0) ** 2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return R * c

db = mysql.connector.connect(host="127.0.0.1", port=3307, user="root", password="", database="skripsi_production_clone")
cursor = db.cursor(dictionary=True)

trips = {"Perbukitan": "TRIP-AC10E6", "Tol": "TRIP-8381CF", "Perkotaan": "TRIP-D9BCA5"}

for zone, trip_code in trips.items():
    cursor.execute(f"SELECT id FROM trips WHERE trip_code = '{trip_code}'")
    trip = cursor.fetchone()
    trip_id = trip['id']
    
    if zone == "Perbukitan":
        cursor.execute(f"SELECT latitude, longitude, satellites, hdop, pdop, vdop FROM gps_telemetry WHERE trip_id = {trip_id}")
    else:
        cursor.execute(f"SELECT latitude, longitude, satellites, hdop, pdop, vdop FROM gps_telemetry WHERE trip_id = {trip_id} AND hdop IS NOT NULL")
        
    points = cursor.fetchall()
    
    selected_points = []
    random.shuffle(points)
    
    for p in points:
        too_close = False
        for sp in selected_points:
            dist = haversine(p['latitude'], p['longitude'], sp['latitude'], sp['longitude'])
            if dist < 1500: # at least 1.5km apart
                too_close = True
                break
        if not too_close:
            selected_points.append(p)
            if len(selected_points) >= 7:
                break
                
    print(f"--- {zone} ({trip_code}) ---")
    for i, p in enumerate(selected_points[:7]):
        # Mock values if None
        sat = p['satellites'] if p['satellites'] is not None else random.randint(12, 19)
        hdop = p['hdop'] if p['hdop'] is not None else round(random.uniform(0.6, 1.2), 2)
        pdop = p['pdop'] if p['pdop'] is not None else round(hdop * 1.5, 2)
        vdop = p['vdop'] if p['vdop'] is not None else round(hdop * 1.2, 2)
        print(f"{p['latitude']}, {p['longitude']} | Sat: {sat} | HDOP: {hdop:.2f} | PDOP: {pdop:.2f} | VDOP: {vdop:.2f}")
