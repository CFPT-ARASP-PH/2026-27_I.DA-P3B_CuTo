# Sabre connecté

Firmware CircuitPython du sabre : il mesure l'accélération 50 fois par seconde, détecte une touche
(choc fort **et** variation brutale) et envoie `HIT` au Raspberry Pi en Bluetooth.

## Matériel

- Carte **Seeed XIAO nRF52840 Sense** (accéléromètre LSM6DS3TR-C intégré, Bluetooth LE)
- 3 LED : **rouge** sur `D1`, **verte** sur `D2`, **bleue** sur `D5`

| LED | Signification |
|-----|---------------|
| Verte | Prêt, sabre immobile |
| Bleue | Mouvement (swing) |
| Rouge (0,6 s) | Touche détectée et envoyée |
| Rouge fixe | Accéléromètre introuvable (erreur au démarrage) |

## Installation

1. Installer [CircuitPython 10](https://circuitpython.org/board/Seeed_XIAO_nRF52840_Sense/) sur la carte :
   double appui sur Reset, puis glisser le fichier `.uf2` sur le lecteur qui apparaît.
2. Copier dans `CIRCUITPY/lib/`, depuis le [bundle de bibliothèques](https://circuitpython.org/libraries)
   de la même version : `adafruit_ble/`, `adafruit_lsm6ds/`, `adafruit_register/`, `adafruit_bus_device/`.
3. Copier [`code.py`](code.py) à la racine de `CIRCUITPY`.
4. Changer `NOM_BLE` pour que chaque sabre ait un nom différent (`"Sabre-01"`, `"Sabre-02"`…).

## Enregistrer le sabre dans l'application

1. Brancher le sabre en USB et ouvrir le port série (Mu, Thonny, ou `screen /dev/ttyACM0 115200`) :
   le sabre affiche `Adresse MAC BLE du sabre : XX:XX:…`.
2. Dans **Admin › Sabres**, ajouter le sabre avec cette adresse et l'associer à un joueur.
3. Le récepteur du Raspberry s'y connecte tout seul ; chaque touche s'affiche en direct sur la page
   d'arbitrage et l'écran de salle.

## Réglages (`code.py`)

| Constante | Défaut | Rôle |
|-----------|--------|------|
| `SEUIL_CHOC` | `10.0` | Accélération minimale (m/s²) pour un mouvement / une touche |
| `SEUIL_JERK` | `22.0` | Variation brutale minimale entre deux mesures pour compter une touche |
| `COOLDOWN_APRES_HIT` | `0.6` | Délai (s) avant de pouvoir détecter une nouvelle touche |
| `DUREE_LED_ROUGE` | `0.6` | Durée (s) de la LED rouge après une touche |
