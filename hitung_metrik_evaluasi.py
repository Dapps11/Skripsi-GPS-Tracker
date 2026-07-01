import math
import numpy as np

def hitung_mae(aktual, prediksi):
    """Mean Absolute Error (rata-rata nilai absolut selisih)"""
    n = len(aktual)
    error = sum(abs(a - p) for a, p in zip(aktual, prediksi))
    return error / n

def hitung_rmse(aktual, prediksi):
    """Root Mean Square Error (akar dari rata-rata selisih kuadrat)"""
    n = len(aktual)
    error_kuadrat = sum((a - p) ** 2 for a, p in zip(aktual, prediksi))
    return math.sqrt(error_kuadrat / n)

def hitung_mape(aktual, prediksi):
    """Mean Absolute Percentage Error (persentase rata-rata error)"""
    n = len(aktual)
    # Hindari pembagian dengan 0 jika nilai aktualnya 0
    error_persen = sum(abs((a - p) / a) for a, p in zip(aktual, prediksi) if a != 0)
    return (error_persen / n) * 100

def hitung_pearson(x, y):
    """Koefisien Korelasi Pearson (mengukur hubungan linear)"""
    n = len(x)
    mean_x = sum(x) / n
    mean_y = sum(y) / n
    
    pembilang = sum((xi - mean_x) * (yi - mean_y) for xi, yi in zip(x, y))
    penyebut_x = sum((xi - mean_x) ** 2 for xi in x)
    penyebut_y = sum((yi - mean_y) ** 2 for yi in y)
    
    # Mencegah division by zero
    if penyebut_x == 0 or penyebut_y == 0:
        return 0.0
        
    korelasi = pembilang / math.sqrt(penyebut_x * penyebut_y)
    return korelasi

print("=" * 60)
print("1. SIMULASI METRIK UNTUK AKURASI GPS")
print("=" * 60)

# Contoh data GPS (diambil dari tabel data Anda)
# Untuk menyederhanakan array, kita langsung pakai array 1 dimensi
hdop_gps = [0.86, 1.09, 1.18, 0.99, 0.69, 0.97, 1.15, 0.60, 0.60, 0.70, 0.70, 0.60, 0.60, 0.60, 0.70, 0.50, 0.60, 0.60, 0.70, 0.60, 0.60]
satelit_gps = [18, 12, 12, 18, 18, 17, 14, 17, 19, 16, 16, 25, 17, 17, 20, 30, 27, 24, 15, 25, 24]
# Data Kesalahan Posisi yang terbaru
error_posisi = [3.24, 5.51, 4.81, 3.83, 3.63, 4.66, 4.50, 3.17, 3.62, 3.08, 3.80, 3.55, 3.80, 2.45, 2.73, 2.59, 3.05, 2.75, 3.07, 3.43, 3.29]
target_error_nol = [0] * len(error_posisi) # Anggap nilai aktual impian (tanpa error) adalah 0 meter

# Hitung Rata-Rata
mae_gps = hitung_mae(target_error_nol, error_posisi)
print(f"Rata-rata Kesalahan Posisi (MAE): {mae_gps:.2f} meter")

# Hitung Korelasi
korelasi_hdop = hitung_pearson(hdop_gps, error_posisi)
korelasi_satelit = hitung_pearson(satelit_gps, error_posisi)
print(f"Korelasi Pearson (HDOP vs Kesalahan)   : {korelasi_hdop:.3f}")
print(f"Korelasi Pearson (Satelit vs Kesalahan): {korelasi_satelit:.3f}")

print("\n" + "=" * 60)
print("2. SIMULASI METRIK UNTUK EVALUASI ETA")
print("=" * 60)

# Misal ini adalah durasi perjalanan dalam menit
# Durasi asli dari supir vs durasi tebakan dari sistem (Haversine & Google)
durasi_aktual = [30, 45, 60, 25, 40] 
tebakan_haversine = [20, 35, 50, 15, 25] # Secara teori meremehkan kemacetan
tebakan_google = [32, 42, 65, 27, 38]    # Secara teori lebih mendekati realita

# Metrik Haversine
mae_hav = hitung_mae(durasi_aktual, tebakan_haversine)
rmse_hav = hitung_rmse(durasi_aktual, tebakan_haversine)
mape_hav = hitung_mape(durasi_aktual, tebakan_haversine)

# Metrik Google Maps
mae_gmaps = hitung_mae(durasi_aktual, tebakan_google)
rmse_gmaps = hitung_rmse(durasi_aktual, tebakan_google)
mape_gmaps = hitung_mape(durasi_aktual, tebakan_google)

print(f"Metrik ETA Haversine   -> MAE: {mae_hav:.2f} mnt | RMSE: {rmse_hav:.2f} mnt | MAPE: {mape_hav:.2f}%")
print(f"Metrik ETA Google Maps -> MAE: {mae_gmaps:.2f} mnt | RMSE: {rmse_gmaps:.2f} mnt | MAPE: {mape_gmaps:.2f}%")
print("=" * 60)
