# code.py
# A mettre sur D:\ (ta carte CIRCUITPY)
#
# Envoie en continu sur le port serie une ligne du type:
#   D,ax,ay,az,gx,gy,gz,hit
#
# Fonctionnalites:
# - Calibration automatique de la gravite locale au demarrage (compense le
#   biais propre du capteur, au lieu d'utiliser 9.8 en dur).
# - Detection de toucher basee sur intensite ET brutalite (jerk), pour
#   distinguer un vrai coup (pic bref et violent) d'un swing rapide
#   (acceleration plus progressive, meme si elle est forte).
# - Cadence d'echantillonnage stabilisee (compense le temps de calcul/print
#   pour rester proche de 50 Hz au lieu de deriver).
# - Demarrage protege: si le capteur ne repond pas, la LED clignote en
#   rouge au lieu de planter silencieusement.

import time
import board
import digitalio
import busio

from adafruit_lsm6ds.lsm6ds3trc import LSM6DS3TRC

# ---------- Configuration ----------
# SEUIL_CHOC : intensite du choc (en m/s^2 au-dessus de la gravite).
#   Un swing rapide dans le vide atteint rarement plus de 20 m/s^2 de variation.
#   Un vrai choc (deux sabres qui se percutent) depasse facilement 35-40 m/s^2.
SEUIL_CHOC = 35.0

# SEUIL_JERK : variation brutale entre deux echantillons consecutifs.
#   Un swing progressif a un jerk faible meme s'il est rapide.
#   Un impact est toujours brusque : variation >= 18 m/s^2 en 20 ms.
SEUIL_JERK = 18.0

DUREE_LED_ALLUMEE   = 0.3   # secondes
COOLDOWN_APRES_HIT  = 0.6   # secondes avant de pouvoir redetecter (evite les doubles comptes)
NB_ECHANTILLONS_CALIBRATION = 100  # ~2 secondes a 50Hz
PERIODE_BOUCLE = 0.02       # secondes -> ~50 Hz

# ---------- LED RGB integree (active low) ----------
led_rouge = digitalio.DigitalInOut(board.LED_RED)
led_rouge.direction = digitalio.Direction.OUTPUT
led_rouge.value = True

led_verte = digitalio.DigitalInOut(board.LED_GREEN)
led_verte.direction = digitalio.Direction.OUTPUT
led_verte.value = True

led_bleue = digitalio.DigitalInOut(board.LED_BLUE)
led_bleue.direction = digitalio.Direction.OUTPUT
led_bleue.value = True


def led_off():
    led_rouge.value = True
    led_verte.value = True
    led_bleue.value = True


def led_on_rouge():
    led_off()
    led_rouge.value = False


def led_on_bleue():
    led_off()
    led_bleue.value = False


def clignote_erreur():
    """Clignotement rouge infini: signale un probleme materiel (I2C/IMU)."""
    while True:
        led_on_rouge()
        time.sleep(0.15)
        led_off()
        time.sleep(0.15)


# ---------- Activation alimentation capteurs ----------
try:
    imu_power = digitalio.DigitalInOut(board.IMU_PWR)
    imu_power.direction = digitalio.Direction.OUTPUT
    imu_power.value = True
    time.sleep(0.1)
except Exception:
    pass

# ---------- Bus I2C dedie a l'IMU ----------
try:
    i2c = busio.I2C(board.IMU_SCL, board.IMU_SDA)
    imu = LSM6DS3TRC(i2c)
except Exception:
    clignote_erreur()

# ---------- Calibration de la gravite locale ----------
led_on_bleue()
somme = 0.0
echantillons_valides = 0

for _ in range(NB_ECHANTILLONS_CALIBRATION):
    try:
        ax, ay, az = imu.acceleration
        somme += (ax ** 2 + ay ** 2 + az ** 2) ** 0.5
        echantillons_valides += 1
    except Exception:
        pass
    time.sleep(0.02)

if echantillons_valides == 0:
    clignote_erreur()

GRAVITE_LOCALE = somme / echantillons_valides
led_off()

# ---------- Boucle principale ----------
temps_fin_hit               = 0.0
temps_prochain_hit_possible = 0.0
magnitude_precedente        = GRAVITE_LOCALE
prochaine_echeance          = time.monotonic()

while True:
    try:
        accel_x, accel_y, accel_z = imu.acceleration  # m/s^2
        gyro_x,  gyro_y,  gyro_z  = imu.gyro           # rad/s
    except Exception:
        prochaine_echeance += PERIODE_BOUCLE
        time.sleep(max(0, prochaine_echeance - time.monotonic()))
        continue

    magnitude = (accel_x ** 2 + accel_y ** 2 + accel_z ** 2) ** 0.5
    choc = abs(magnitude - GRAVITE_LOCALE)
    jerk = abs(magnitude - magnitude_precedente)
    magnitude_precedente = magnitude

    maintenant = time.monotonic()

    if (choc > SEUIL_CHOC
            and jerk > SEUIL_JERK
            and maintenant > temps_prochain_hit_possible):
        led_on_rouge()
        temps_fin_hit               = maintenant + DUREE_LED_ALLUMEE
        temps_prochain_hit_possible = maintenant + COOLDOWN_APRES_HIT

    hit_actif = maintenant < temps_fin_hit

    if not hit_actif:
        led_off()

    print("D,%.4f,%.4f,%.4f,%.4f,%.4f,%.4f,%d" % (
        accel_x, accel_y, accel_z,
        gyro_x,  gyro_y,  gyro_z,
        1 if hit_actif else 0
    ))

    prochaine_echeance += PERIODE_BOUCLE
    attente = prochaine_echeance - time.monotonic()
    if attente > 0:
        time.sleep(attente)
    else:
        prochaine_echeance = time.monotonic()
