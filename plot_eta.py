import matplotlib.pyplot as plt
import numpy as np
from matplotlib import rcParams

# Pengaturan Font agar sesuai untuk skripsi
rcParams['font.family'] = 'serif'
rcParams['font.size'] = 11

# Data 8 Percobaan Perjalanan dari Tabel 5.4
trips = ['1', '2', '3', '4', '5', '6', '7', '8']
aktual = [59, 23, 25, 50, 31, 18, 18, 15]
haversine = [51, 46, 17, 22, 29, 24, 16, 17]
gmaps = [44, 23, 10, 23, 18, 19, 10, 11]

x = np.arange(len(trips))  # the label locations
width = 0.25  # the width of the bars

fig, ax = plt.subplots(figsize=(12, 6))

# Membuat 3 bar per trip
rects1 = ax.bar(x - width, aktual, width, label='Waktu Tiba Aktual', color='#2ca02c', edgecolor='black', linewidth=0.7)
rects2 = ax.bar(x, haversine, width, label='ETA Haversine', color='#1f77b4', edgecolor='black', linewidth=0.7)
rects3 = ax.bar(x + width, gmaps, width, label='ETA Google Maps', color='#d62728', edgecolor='black', linewidth=0.7)

# Label dan Judul
ax.set_ylabel('Waktu (Menit)')
ax.set_xlabel('Percobaan Ke-')
ax.set_title('Perbandingan ETA Haversine, ETA Google Maps, dan Waktu Tiba Aktual pada 8 Percobaan Perjalanan', pad=20, fontweight='bold')
ax.set_xticks(x)
ax.set_xticklabels(trips)
ax.legend(loc='upper left')

# Menambahkan Grid untuk mempermudah pembacaan nilai
ax.grid(axis='y', linestyle='--', alpha=0.7)
ax.set_axisbelow(True)

# Menambahkan nilai di atas masing-masing bar
def autolabel(rects):
    """Attach a text label above each bar in *rects*, displaying its height."""
    for rect in rects:
        height = rect.get_height()
        ax.annotate(f'{height}',
                    xy=(rect.get_x() + rect.get_width() / 2, height),
                    xytext=(0, 3),  # 3 points vertical offset
                    textcoords="offset points",
                    ha='center', va='bottom', fontsize=9)

autolabel(rects1)
autolabel(rects2)
autolabel(rects3)

fig.tight_layout()

# Menyimpan ke file
plt.savefig('grafik_perbandingan_eta.png', dpi=300, bbox_inches='tight')
print("Grafik berhasil disimpan sebagai 'grafik_perbandingan_eta.png'")
# plt.show()
