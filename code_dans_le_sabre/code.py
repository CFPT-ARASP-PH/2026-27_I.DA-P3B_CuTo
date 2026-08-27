# code.py
# Detection de swing et de collision
#
# D1 = LED rouge
# D2 = LED verte
# D5 = LED bleue

import time
import board
import digitalio
import busio

from adafruit_lsm6ds.lsm6ds3trc import LSM6DS3TRC


# ---------- Configuration ----------

SEUIL_CHOC = 10.0
SEUIL_JERK = 22.0

COOLDOWN_APRES_HIT = 0.6
DUREE_LED_ROUGE = 0.6

PERIODE_BOUCLE = 0.02


# ---------- Grandes LED ----------

led_rouge = digitalio.DigitalInOut(board.D1)
led_rouge.direction = digitalio.Direction.OUTPUT

led_verte = digitalio.DigitalInOut(board.D2)
led_verte.direction = digitalio.Direction.OUTPUT

led_bleue = digitalio.DigitalInOut(board.D5)
led_bleue.direction = digitalio.Direction.OUTPUT


def led_off():
    led_rouge.value = False
    led_verte.value = False
    led_bleue.value = False


def led_verte_on():
    led_off()
    led_verte.value = True


def led_bleue_on():
    led_off()
    led_bleue.value = True


def led_rouge_on():
    led_off()
    led_rouge.value = True


# ---------- Alimentation IMU ----------

try:
    imu_power = digitalio.DigitalInOut(board.IMU_PWR)
    imu_power.direction = digitalio.Direction.OUTPUT
    imu_power.value = True
    time.sleep(0.1)
except Exception:
    pass


# ---------- Initialisation IMU ----------

try:
    i2c = busio.I2C(board.IMU_SCL, board.IMU_SDA)
    imu = LSM6DS3TRC(i2c)
except Exception:
    led_rouge_on()

    while True:
        time.sleep(0.2)


# ---------- Variables ----------

temps_fin_rouge = 0.0
prochain_hit_possible = 0.0

accel_x, accel_y, accel_z = imu.acceleration

magnitude_precedente = (
    accel_x ** 2 +
    accel_y ** 2 +
    accel_z ** 2
) ** 0.5

prochaine_echeance = time.monotonic()


# ---------- Demarrage ----------

led_verte_on()
time.sleep(1)


# ---------- Boucle principale ----------

while True:

    try:
        accel_x, accel_y, accel_z = imu.acceleration

    except Exception:
        continue


    # Intensite totale de l'acceleration

    magnitude = (
        accel_x ** 2 +
        accel_y ** 2 +
        accel_z ** 2
    ) ** 0.5


    # Variation brutale de l'acceleration

    jerk = abs(magnitude - magnitude_precedente)

    magnitude_precedente = magnitude


    maintenant = time.monotonic()


    # ---------- Detection de mouvement ----------

    # Si l'acceleration depasse 10 m/s²,
    # on considere qu'il y a un mouvement/swing.

    mouvement = magnitude > SEUIL_CHOC


    # ---------- Detection collision ----------

    if (
        magnitude > SEUIL_CHOC
        and jerk > SEUIL_JERK
        and maintenant > prochain_hit_possible
    ):

        print("HIT")

        led_rouge_on()

        temps_fin_rouge = maintenant + DUREE_LED_ROUGE

        prochain_hit_possible = (
            maintenant + COOLDOWN_APRES_HIT
        )


    # ---------- LED ----------

    elif maintenant < temps_fin_rouge:

        led_rouge_on()

    elif mouvement:

        # Mouvement mais pas de collision
        led_bleue_on()

    else:

        # Aucun mouvement
        led_verte_on()


    # ---------- Cadence 50 Hz ----------

    prochaine_echeance += PERIODE_BOUCLE

    attente = prochaine_echeance - time.monotonic()

    if attente > 0:
        time.sleep(attente)
    else:
        prochaine_echeance = time.monotonic()
