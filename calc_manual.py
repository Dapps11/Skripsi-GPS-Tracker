import math

Y = [3.24, 5.51, 4.81, 3.83, 3.63, 4.66, 4.50, 3.17, 3.62, 3.08, 3.80, 3.55, 3.80, 2.45, 2.73, 2.59, 3.05, 2.75, 3.07, 3.43, 3.29]
X = [18, 12, 12, 18, 18, 17, 14, 17, 19, 16, 16, 25, 17, 17, 20, 30, 27, 24, 15, 25, 24]
n = len(Y)

sum_Y = sum(Y)
mae = sum_Y / n

sum_Y_sq = sum(y**2 for y in Y)
rmse = math.sqrt(sum_Y_sq / n)

sum_X = sum(X)
sum_X_sq = sum(x**2 for x in X)
sum_XY = sum(x*y for x,y in zip(X, Y))

numerator = (n * sum_XY) - (sum_X * sum_Y)
denom_X = (n * sum_X_sq) - (sum_X**2)
denom_Y = (n * sum_Y_sq) - (sum_Y**2)
r = numerator / math.sqrt(denom_X * denom_Y)

print("--- MAE ---")
print(f"MAE = Jumlah Kesalahan Posisi / Banyaknya Data (n)")
print(f"MAE = ({Y[0]} + {Y[1]} + {Y[2]} + ... + {Y[-1]}) / {n}")
print(f"MAE = {sum_Y:.2f} / {n}")
print(f"MAE = {mae:.2f} meter")

print("\n--- RMSE ---")
print(f"RMSE = Akar Kuadrat dari [ Jumlah Kuadrat Kesalahan Posisi / Banyaknya Data (n) ]")
print(f"RMSE = Akar Kuadrat dari [ ({Y[0]}^2 + {Y[1]}^2 + {Y[2]}^2 + ... + {Y[-1]}^2) / {n} ]")
print(f"RMSE = Akar Kuadrat dari [ ({(Y[0]**2):.2f} + {(Y[1]**2):.2f} + {(Y[2]**2):.2f} + ... + {(Y[-1]**2):.2f}) / {n} ]")
print(f"RMSE = Akar Kuadrat dari [ {sum_Y_sq:.2f} / {n} ]")
print(f"RMSE = Akar Kuadrat dari [ {(sum_Y_sq/n):.4f} ]")
print(f"RMSE = {rmse:.2f} meter")

print("\n--- Pearson ---")
print(f"Jumlah X = {X[0]} + {X[1]} + {X[2]} + ... + {X[-1]} = {sum_X}")
print(f"Jumlah Kuadrat X = {X[0]}^2 + {X[1]}^2 + {X[2]}^2 + ... + {X[-1]}^2 = {sum_X_sq}")
print(f"Jumlah Perkalian X dan Y = ({X[0]} * {Y[0]}) + ({X[1]} * {Y[1]}) + ({X[2]} * {Y[2]}) + ... + ({X[-1]} * {Y[-1]}) = {sum_XY:.2f}")

print(f"\nr = [ {n} * ({sum_XY:.2f}) - ({sum_X}) * ({sum_Y:.2f}) ] / Akar Kuadrat dari [ ({n} * {sum_X_sq} - {sum_X}^2) * ({n} * {sum_Y_sq:.2f} - {sum_Y:.2f}^2) ]")
print(f"r = [ {(n * sum_XY):.2f} - {(sum_X * sum_Y):.2f} ] / Akar Kuadrat dari [ ({(n * sum_X_sq)} - {(sum_X**2)}) * ({(n * sum_Y_sq):.2f} - {(sum_Y**2):.2f}) ]")
print(f"r = [ {numerator:.2f} ] / Akar Kuadrat dari [ {denom_X} * {denom_Y:.2f} ]")
print(f"r = {numerator:.2f} / Akar Kuadrat dari [ {(denom_X * denom_Y):.2f} ]")
print(f"r = {numerator:.2f} / {math.sqrt(denom_X * denom_Y):.3f}")
print(f"r = {r:.3f}")
