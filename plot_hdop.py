import matplotlib.pyplot as plt
import numpy as np
from matplotlib import rcParams

# Font adjustments for scientific paper
rcParams['font.family'] = 'serif'
rcParams['font.size'] = 11

# Data dari Tabel 5.14
# Format: [HDOP, Kesalahan Posisi (m)]
perbukitan = np.array([
    [1.20, 3.60],
    [0.60, 2.50],
    [1.20, 4.20],
    [1.00, 3.90],
    [0.90, 3.80],
    [0.60, 3.10],
    [1.10, 3.60]
])

tol = np.array([
    [0.60, 3.00],
    [0.60, 3.00],
    [0.60, 3.00],
    [0.50, 2.50],
    [0.60, 3.00],
    [0.60, 3.00],
    [0.70, 3.50]
])

perkotaan = np.array([
    [0.70, 3.50],
    [0.70, 3.50],
    [0.60, 3.00],
    [0.60, 3.00],
    [0.70, 3.50],
    [0.80, 4.00]
])

# Create the plot
plt.figure(figsize=(8, 5))

# Plot scatter points
plt.scatter(perbukitan[:, 0], perbukitan[:, 1], color='#2ca02c', label='Perbukitan', s=50, alpha=0.9, edgecolors='w', linewidth=0.5)
plt.scatter(tol[:, 0], tol[:, 1], color='#1f77b4', label='Tol', s=50, alpha=0.9, edgecolors='w', linewidth=0.5)
plt.scatter(perkotaan[:, 0], perkotaan[:, 1], color='#d62728', label='Perkotaan', s=50, alpha=0.9, edgecolors='w', linewidth=0.5)

# Calculate and plot the trendline (Linear Regression)
all_data = np.vstack((perbukitan, tol, perkotaan))
x = all_data[:, 0]
y = all_data[:, 1]
m, b = np.polyfit(x, y, 1)

# Plot line of best fit over the range of x
x_line = np.linspace(min(x)-0.05, max(x)+0.05, 100)
plt.plot(x_line, m*x_line + b, color='black', linestyle='--', alpha=0.6, label='Garis Tren')

# Labels and title
plt.title('Hubungan Nilai HDOP terhadap Kesalahan Posisi GPS', pad=15, fontweight='bold')
plt.xlabel('Nilai HDOP')
plt.ylabel('Kesalahan Posisi (meter)')

# Grid and Legend
plt.grid(True, linestyle='-', alpha=0.3)
plt.legend(loc='upper left', framealpha=0.9)

# Adjust layout and save
plt.tight_layout()
plt.savefig('grafik_hdop_error.png', dpi=300, bbox_inches='tight')
print("Grafik berhasil disimpan sebagai 'grafik_hdop_error.png'")
# plt.show()
