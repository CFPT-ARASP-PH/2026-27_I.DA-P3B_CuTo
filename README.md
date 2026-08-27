# 2026-27_CuTo

## Description du projet

CuTo est un système de sabres laser connectés capables de détecter les touches (impacts) et de les transmettre en temps réel à une interface web.

Chaque sabre laser est équipé d'un capteur permettant de détecter un toucher. Dès qu'un toucher est détecté, l'information est envoyée via **Bluetooth** à un **Raspberry Pi**. Le Raspberry Pi héberge directement l'**interface web**, qui reçoit les données et affiche en temps réel si un toucher a eu lieu.

## Fonctionnement

1. Le sabre laser détecte un toucher grâce à son capteur.
2. Le sabre envoie l'information de toucher au Raspberry Pi via Bluetooth.
3. Le Raspberry Pi reçoit la donnée et la transmet au serveur local.
4. L'interface web, hébergée sur le Raspberry Pi, affiche en direct si un toucher a été détecté.

## Architecture

```
Sabre laser (capteur de toucher)
        │
        │  Bluetooth
        ▼
   Raspberry Pi
        │
        ▼
   Serveur + Interface Web (affichage des toucher)
```

## Matériel utilisé

- Sabres laser équipés d'un capteur de toucher
- Module Bluetooth
- Raspberry Pi (serveur + interface web)

## Journal de bord

Liens vers le journal de bord : [journal de bord](https://github.com/CFPT-ARASP-PH/2026-27_I.DA-P3B_CuTo/blob/main/journalDeBord.md)
