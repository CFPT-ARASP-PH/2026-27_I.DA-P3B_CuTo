# 2026-27_CuTo

## Description du projet

CuTo est un système de sabres laser connectés capables de détecter les touches (impacts) et de les transmettre en temps réel à une interface web.

Chaque sabre laser embarque un microcontrôleur **Seeed XIAO nRF52840 Sense** équipé d'un capteur IMU (accéléromètre + gyroscope) permettant de détecter un toucher. Dès qu'un toucher est détecté, l'information est envoyée via **Bluetooth** à un **Raspberry Pi**. Le Raspberry Pi héberge directement l'**interface web**, qui reçoit les données et affiche en temps réel si un toucher a eu lieu.

## Fonctionnement

1. Le microcontrôleur du sabre laser lit en continu les données de l'IMU (accélération et gyroscope) à une cadence d'environ 50 Hz.
2. Un toucher est détecté lorsque deux conditions sont réunies simultanément :
   - **Intensité du choc** : l'accélération dépasse un seuil au-dessus de la gravité locale (calibrée automatiquement au démarrage), ce qui distingue un vrai impact d'un simple mouvement.
   - **Brutalité (jerk)** : la variation d'accélération entre deux mesures consécutives est suffisamment brusque, ce qui distingue un coup net d'un swing rapide mais progressif.
3. Lorsqu'un toucher est détecté, la LED embarquée s'allume brièvement et un cooldown est appliqué pour éviter les doubles détections.
4. Le sabre envoie en continu ses données (accélération, gyroscope, état du toucher) au Raspberry Pi via Bluetooth.
5. Le Raspberry Pi reçoit la donnée et la transmet au serveur local.
6. L'interface web, hébergée sur le Raspberry Pi, affiche en direct si un toucher a été détecté.

## Architecture

```
Sabre laser (XIAO nRF52840 Sense + IMU LSM6DS3TRC)
        │
        │  Bluetooth
        ▼
   Raspberry Pi
        │
        ▼
   Serveur + Interface Web (affichage des touchers)
```

## Matériel utilisé

- Microcontrôleur **Seeed XIAO nRF52840 Sense** (CircuitPython) par sabre laser
- Capteur IMU **LSM6DS3TRC** (accéléromètre + gyroscope) pour la détection de toucher
- Module Bluetooth
- Raspberry Pi (serveur + interface web)

## Détection de toucher (détail technique)

Le script `code.py` embarqué sur chaque sabre :
- calibre automatiquement la gravité locale au démarrage (au lieu d'utiliser une valeur fixe de 9.8 m/s²), pour compenser le biais propre de chaque capteur ;
- combine un seuil d'**intensité du choc** et un seuil de **jerk** (brutalité) pour ne détecter que les vrais impacts et ignorer les mouvements rapides du sabre dans le vide ;
- stabilise la cadence d'échantillonnage autour de 50 Hz en compensant le temps de calcul ;
- signale un problème matériel (capteur non détecté) par un clignotement rouge de la LED, plutôt que de planter silencieusement ;
- envoie en continu sur le port série une ligne au format :
  ```
  D,ax,ay,az,gx,gy,gz,hit
  ```
  où `ax,ay,az` sont les accélérations, `gx,gy,gz` les vitesses angulaires, et `hit` vaut `1` si un toucher est actif, `0` sinon.

## Journal de bord

Liens vers le journal de bord : [journal de bord](https://github.com/CFPT-ARASP-PH/2026-27_I.DA-P3B_CuTo/blob/main/journalDeBord.md)
