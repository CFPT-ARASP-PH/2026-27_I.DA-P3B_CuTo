# Télécommande d'arbitre

Chaque arbitre peut donner ses points avec une télécommande Bluetooth, exactement comme avec le pupitre
du site : **rouge = +1 Aka**, **bleu = +1 Ao**, **les deux ensemble = annule le dernier point**.

## Matériel

- Carte CircuitPython avec Bluetooth LE (nRF52840)
- Écran OLED **SSD1306 128×64** en I2C (adresse `0x3C`)
- Bouton bleu sur `D10`, bouton rouge sur `D7` (vers la masse, pull-up interne)
- LED bleue sur `D9`, LED rouge sur `D8`

## Installation

1. Installer CircuitPython 10 sur la carte.
2. Copier dans `CIRCUITPY/lib/`, depuis le [bundle de bibliothèques](https://circuitpython.org/libraries) :
   `adafruit_ble/`, `adafruit_display_text/`, `adafruit_displayio_ssd1306.mpy`.
3. Copier [`circuitpython/code.py`](circuitpython/code.py) à la racine de `CIRCUITPY`.
4. Changer `DEVICE_NAME` : il doit **commencer par `TEL`** et être différent pour chaque télécommande
   (`"TEL_1"`, `"TEL_2"`…).
5. Allumer la télécommande à portée du Raspberry : elle apparaît dans **Admin › Sabres › Télécommandes**,
   où on lui associe un arbitre.

## Écran

| Affichage | Signification |
|-----------|---------------|
| Score + chrono | Points donnés par cet arbitre dans le combat en cours |
| `STOP` | Combat en pause |
| Vainqueur | Fin du combat (affiché 2 min) |
| `SANS ARBITRE` | La télécommande n'est associée à aucun arbitre |
| `PI ?` | Aucune nouvelle du Raspberry depuis 3 s |

## Protocole (Nordic UART, une ligne par message)

- Télécommande → Pi : `PT:R`, `PT:B`, `UNDO`
- Pi → télécommande : `NOARB`, `IDLE`, `FIGHT`, `STOP`, `TIME:<s>`, `SCORE:<rouge>:<bleu>`, `WIN:R|B|D`,
  `OK`, `ERR:<raison>`

Suivre les échanges sur le Pi : `docker logs -f sabre_ble`.
