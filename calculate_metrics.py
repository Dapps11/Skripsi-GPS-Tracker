import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
from scipy.stats import pearsonr

data = [
    ["Perbukitan", 18, 0.86, 1.29, 1.03, 3.24],
    ["Perbukitan", 12, 1.09, 1.64, 1.31, 5.51],
    ["Perbukitan", 12, 1.18, 1.77, 1.42, 4.81],
    ["Perbukitan", 18, 0.99, 1.48, 1.19, 3.83],
    ["Perbukitan", 18, 0.69, 1.03, 0.83, 3.63],
    ["Perbukitan", 17, 0.97, 1.46, 1.16, 4.66],
    ["Perbukitan", 14, 1.15, 1.72, 1.38, 4.50],
    ["Tol", 17, 0.60, 1.00, 0.70, 3.17],
    ["Tol", 19, 0.60, 1.00, 0.80, 3.62],
    ["Tol", 16, 0.70, 1.10, 0.80, 3.08],
    ["Tol", 16, 0.70, 1.00, 0.80, 3.80],
    ["Tol", 25, 0.60, 0.90, 0.70, 3.55],
    ["Tol", 17, 0.60, 0.90, 0.70, 3.80],
    ["Tol", 17, 0.60, 0.90, 0.70, 2.45],
    ["Perkotaan", 20, 0.70, 1.10, 0.80, 2.73],
    ["Perkotaan", 30, 0.50, 0.80, 0.60, 2.59],
    ["Perkotaan", 27, 0.60, 0.80, 0.60, 3.05],
    ["Perkotaan", 24, 0.60, 0.90, 0.70, 2.75],
    ["Perkotaan", 15, 0.70, 1.00, 0.80, 3.07],
    ["Perkotaan", 25, 0.60, 0.90, 0.70, 3.43],
    ["Perkotaan", 24, 0.60, 1.00, 0.70, 3.29]
]

df = pd.DataFrame(data, columns=["Zona", "Satelit", "HDOP", "PDOP", "VDOP", "Kesalahan"])

# Metrics
mae = df["Kesalahan"].mean()
r_hdop, _ = pearsonr(df["HDOP"], df["Kesalahan"])
r_pdop, _ = pearsonr(df["PDOP"], df["Kesalahan"])
r_sat, _ = pearsonr(df["Satelit"], df["Kesalahan"])

min_error = df["Kesalahan"].min()
min_error_row = df.loc[df["Kesalahan"].idxmin()]

max_error = df["Kesalahan"].max()
max_error_row = df.loc[df["Kesalahan"].idxmax()]

mae_perbukitan = df[df["Zona"] == "Perbukitan"]["Kesalahan"].mean()
mae_perkotaan = df[df["Zona"] == "Perkotaan"]["Kesalahan"].mean()
mae_tol = df[df["Zona"] == "Tol"]["Kesalahan"].mean()

print(f"MAE Keseluruhan: {mae:.2f}")
print(f"r HDOP: {r_hdop:.3f}")
print(f"r PDOP: {r_pdop:.3f}")
print(f"r Sat: {r_sat:.3f}")
print(f"MAE Perbukitan: {mae_perbukitan:.2f}")
print(f"MAE Tol: {mae_tol:.2f}")
print(f"MAE Perkotaan: {mae_perkotaan:.2f}")

# Plot
sns.set_theme(style="whitegrid")
plt.figure(figsize=(10, 6))
colors = {"Perbukitan": "red", "Tol": "blue", "Perkotaan": "green"}

for zone in df["Zona"].unique():
    subset = df[df["Zona"] == zone]
    plt.scatter(subset["HDOP"], subset["Kesalahan"], label=zone, color=colors[zone], s=100, alpha=0.7, edgecolors='k')

# Add trendline
z = np.polyfit(df["HDOP"], df["Kesalahan"], 1)
p = np.poly1d(z)
plt.plot(df["HDOP"], p(df["HDOP"]), "k--", alpha=0.5, label="Tren Linear")

plt.title("Hubungan Nilai HDOP terhadap Kesalahan Posisi GPS", fontsize=14, fontweight='bold')
plt.xlabel("Nilai HDOP", fontsize=12)
plt.ylabel("Kesalahan Posisi (meter)", fontsize=12)
plt.legend(title="Zona Pengujian")
plt.grid(True, linestyle='--', alpha=0.6)

plt.savefig("c:\\laragon\\www\\greenfields\\plot_hdop_error.png", dpi=300, bbox_inches="tight")
print("Plot updated!")
