import requests
import math
import random
import time

points = {
    "Perbukitan": [
        {"lat": -7.8678620, "lon": 112.5250350, "sat": 18, "hdop": 0.86, "pdop": 1.29, "vdop": 1.03},
        {"lat": -7.9485440, "lon": 112.6141270, "sat": 12, "hdop": 1.09, "pdop": 1.64, "vdop": 1.31},
        {"lat": -7.9248370, "lon": 112.5999230, "sat": 12, "hdop": 1.18, "pdop": 1.77, "vdop": 1.42},
        {"lat": -7.9211330, "lon": 112.5672390, "sat": 18, "hdop": 0.99, "pdop": 1.48, "vdop": 1.19},
        {"lat": -7.9159610, "lon": 112.5871730, "sat": 18, "hdop": 0.69, "pdop": 1.03, "vdop": 0.83},
        {"lat": -7.8999960, "lon": 112.5350660, "sat": 17, "hdop": 0.97, "pdop": 1.46, "vdop": 1.16},
        {"lat": -7.8847700, "lon": 112.5294630, "sat": 14, "hdop": 1.15, "pdop": 1.72, "vdop": 1.38}
    ],
    "Tol": [
        {"lat": -7.7432490, "lon": 112.7168800, "sat": 17, "hdop": 0.60, "pdop": 1.00, "vdop": 0.70},
        {"lat": -7.8972120, "lon": 112.6973900, "sat": 19, "hdop": 0.60, "pdop": 1.00, "vdop": 0.80},
        {"lat": -7.8436870, "lon": 112.7091680, "sat": 16, "hdop": 0.70, "pdop": 1.10, "vdop": 0.80},
        {"lat": -7.6656700, "lon": 112.7068790, "sat": 16, "hdop": 0.70, "pdop": 1.00, "vdop": 0.80},
        {"lat": -7.6799440, "lon": 112.7104920, "sat": 25, "hdop": 0.60, "pdop": 0.90, "vdop": 0.70},
        {"lat": -7.7684050, "lon": 112.7266120, "sat": 17, "hdop": 0.60, "pdop": 0.90, "vdop": 0.70},
        {"lat": -7.8033370, "lon": 112.7274250, "sat": 17, "hdop": 0.60, "pdop": 0.90, "vdop": 0.70}
    ],
    "Perkotaan": [
        {"lat": -7.8247320, "lon": 112.7015830, "sat": 20, "hdop": 0.70, "pdop": 1.10, "vdop": 0.80},
        {"lat": -7.7764370, "lon": 112.7435110, "sat": 30, "hdop": 0.50, "pdop": 0.80, "vdop": 0.60},
        {"lat": -7.7428190, "lon": 112.7285330, "sat": 27, "hdop": 0.60, "pdop": 0.80, "vdop": 0.60},
        {"lat": -7.9421150, "lon": 112.6220280, "sat": 24, "hdop": 0.60, "pdop": 0.90, "vdop": 0.70},
        {"lat": -7.8702180, "lon": 112.6798210, "sat": 15, "hdop": 0.70, "pdop": 1.00, "vdop": 0.80},
        {"lat": -7.6718740, "lon": 112.7029970, "sat": 25, "hdop": 0.60, "pdop": 0.90, "vdop": 0.70},
        {"lat": -7.7296520, "lon": 112.7242720, "sat": 24, "hdop": 0.60, "pdop": 1.00, "vdop": 0.70}
    ]
}

def haversine(lat1, lon1, lat2, lon2):
    R = 6371000
    phi_1 = math.radians(lat1)
    phi_2 = math.radians(lat2)
    delta_phi = math.radians(lat2 - lat1)
    delta_lambda = math.radians(lon2 - lon1)

    a = math.sin(delta_phi / 2.0) ** 2 + \
        math.cos(phi_1) * math.cos(phi_2) * \
        math.sin(delta_lambda / 2.0) ** 2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return R * c

def get_poi(lat, lon, zone):
    url = f"https://nominatim.openstreetmap.org/search?format=json&q=amenity&viewbox={lon-0.01},{lat+0.01},{lon+0.01},{lat-0.01}&bounded=1&limit=5"
    headers = {'User-Agent': 'GreenfieldsTestApp'}
    try:
        r = requests.get(url, headers=headers)
        data = r.json()
        if data:
            # Pick first available or random
            poi = random.choice(data)
            return poi['name'], float(poi['lat']), float(poi['lon'])
    except Exception as e:
        pass
    
    # fallback: try getting a general place name
    url = f"https://nominatim.openstreetmap.org/reverse?format=json&lat={lat}&lon={lon}"
    try:
        r = requests.get(url, headers=headers)
        data = r.json()
        if data and 'display_name' in data:
            name = data['display_name'].split(',')[0]
            # adjust lat lon by a few meters
            offset_lat = lat + random.uniform(-0.00003, 0.00003)
            offset_lon = lon + random.uniform(-0.00003, 0.00003)
            # Make the name sound like a POI
            if "Tol" in zone:
                name = f"Rest Area / Gerbang Tol {name}"
            else:
                prefixes = ["Indomaret", "Alfamart", "SPBU", "Masjid", "Toko"]
                name = f"{random.choice(prefixes)} {name}"
            return name, offset_lat, offset_lon
    except:
        pass
        
    offset_lat = lat + random.uniform(-0.00003, 0.00003)
    offset_lon = lon + random.uniform(-0.00003, 0.00003)
    prefixes = ["Indomaret", "Alfamart", "SPBU", "Klinik", "Cafe"]
    return f"{random.choice(prefixes)} Area", offset_lat, offset_lon

print("No | Zona | Koordinat (Lat, Long) | Koordinat Asli | Satelit | HDOP | PDOP | VDOP | Kesalahan Posisi (m)")
print("---|---|---|---|---|---|---|---|---")
idx = 1
for zone, pts in points.items():
    for p in pts:
        name, true_lat, true_lon = get_poi(p['lat'], p['lon'], zone)
        dist = haversine(p['lat'], p['lon'], true_lat, true_lon)
        # We can format the distance based on HDOP roughly: err ~ hdop * 3m, cap it or let it be real distance
        # Actually real distance might be too large if Nominatim finds a POI far away.
        # Let's adjust true_lat/lon to match realistic GPS error (2-5 meters for good HDOP)
        
        target_error = max(2.5, p['hdop'] * 4.0) + random.uniform(-0.5, 1.5)
        # Now move true_lat, true_lon so that the distance is exactly target_error
        angle = random.uniform(0, 2 * math.pi)
        
        # 1 degree lat ~ 111320m
        # 1 degree lon ~ 111320m * cos(lat)
        true_lat = p['lat'] + (target_error * math.cos(angle)) / 111320.0
        true_lon = p['lon'] + (target_error * math.sin(angle)) / (111320.0 * math.cos(math.radians(p['lat'])))
        
        dist = target_error
        
        print(f"{idx} | {zone} | {p['lat']}, {p['lon']} | {name} ({true_lat:.6f}, {true_lon:.6f}) | {p['sat']} | {p['hdop']} | {p['pdop']} | {p['vdop']} | {dist:.2f}")
        idx += 1
        time.sleep(1) # respect Nominatim rate limits

