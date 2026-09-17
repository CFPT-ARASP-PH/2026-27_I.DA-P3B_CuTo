# 2026-27_CuTo

## Description du projet

CuTo est un système de sabres laser connectés capables de détecter les touches (impacts) et de les transmettre en temps réel à une interface web d'arbitrage.

Chaque sabre laser embarque un microcontrôleur **Seeed XIAO nRF52840 Sense** équipé d'un capteur IMU (accéléromètre + gyroscope) permettant de détecter un toucher. Dès qu'un toucher est détecté, l'information est envoyée via **Bluetooth Low Energy (BLE)** à un **Raspberry Pi**. Le Raspberry Pi héberge l'ensemble du système via Docker : base de données, interface web d'arbitrage et daemon de réception BLE. L'interface affiche en temps réel les touches, gère les combats et les tournois.

## Fonctionnement

1. Le microcontrôleur du sabre lit en continu les données de l'IMU à ~50 Hz.
2. Un toucher est détecté lorsque deux conditions sont réunies simultanément :
   - **Intensité du choc** : l'accélération dépasse un seuil au-dessus de la gravité locale (calibrée automatiquement au démarrage).
   - **Brutalité (jerk)** : la variation d'accélération entre deux mesures consécutives est suffisamment brusque pour distinguer un coup net d'un swing dans le vide.
3. Dès le toucher détecté, la LED embarquée s'allume en rouge et un cooldown (0,6 s) est appliqué pour éviter les doubles détections. Le sabre envoie `"HIT"` via BLE (service Nordic UART / NUS).
4. Le daemon Python (`ble_receiver`) reçoit la notification BLE et applique la règle de **double-touche** : si deux sabres touchent dans une fenêtre de **200 ms**, la touche est annulée pour les deux. Sinon, elle est validée.
5. Le résultat est enregistré en base de données et affiché en temps réel dans l'interface arbitre et sur l'écran public de la salle.

## Architecture

```
Sabre laser (XIAO nRF52840 Sense + IMU LSM6DS3TRC)
        │
        │  BLE — Nordic UART Service (NUS)
        ▼
   ┌─────────────────────────────────────────────┐
   │              Raspberry Pi                   │
   │                                             │
   │  ┌──────────────┐   ┌────────────────────┐  │
   │  │ ble_receiver │   │   sabre_web        │  │
   │  │ (Python /    │──▶│   (PHP 8.2 +       │  │
   │  │  Bleak)      │   │    Apache)         │  │
   │  └──────┬───────┘   └────────┬───────────┘  │
   │         │                   │               │
   │         └──────┬────────────┘               │
   │                ▼                            │
   │         ┌─────────────┐                     │
   │         │  MariaDB 11 │                     │
   │         └─────────────┘                     │
   │                                             │
   │  (tout lancé par docker compose up --build) │
   └─────────────────────────────────────────────┘
        │
        │  HTTP / Cloudflare Tunnel (HTTPS public)
        ▼
   Navigateur (arbitre, spectateur, admin)
```

## Démarrage rapide

```bash
# Cloner le dépôt
git clone <url-du-repo>
cd webapp_arbitrage

# Copier et adapter les variables d'environnement
cp .env.example .env

# Lancer tout (BLE + web + base de données + tunnel)
docker compose up --build
```

L'interface web est accessible sur `http://<IP-du-Pi>:8080`.  
L'URL publique HTTPS s'affiche dans les logs du tunnel :

```bash
docker compose logs tunnel
```

> **Identifiants par défaut** : `admin` / `admin123` — à changer en production.

## Variables d'environnement (`.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `APP_PORT` | `8080` | Port HTTP exposé sur le réseau local |
| `DB_ROOT_PASSWORD` | `rootpass` | Mot de passe root MariaDB |
| `DB_NAME` | `sabre_arbitrage` | Nom de la base de données |
| `DB_USER` | `arbitrage_user` | Utilisateur applicatif MariaDB |
| `DB_PASS` | `arbitrage_pass` | Mot de passe de l'utilisateur applicatif |
| `BASE_URL` | `/` | Chemin de base de l'application |

## Services Docker

| Service | Image | Rôle |
|---|---|---|
| `sabre_db` | `mariadb:11` | Base de données (combats, joueurs, touches BLE…) |
| `sabre_web` | PHP 8.2 + Apache | Interface web (arbitrage, admin, classement, écran public) |
| `sabre_ble` | Python 3.11 + Bleak | Daemon BLE : connexion aux sabres, détection double-touche, écriture en base |
| `sabre_tunnel` | Cloudflare | Tunnel HTTPS public sans ouverture de port |

## Réinitialiser la base de données

```bash
docker compose down -v   # supprime les données
docker compose up --build
```

## Migration BLE (base existante)

Si la base de données existait avant l'intégration BLE, exécuter une fois :

```bash
docker exec -i sabre_db mysql -u root -p<DB_ROOT_PASSWORD> sabre_arbitrage < sql/migration_ble.sql
```

## Gestion des sabres BLE

1. Allumer un sabre — son adresse MAC BLE s'affiche sur le port série au démarrage (`code.py`).
2. Dans l'interface web → **Admin → Sabres** : le scan automatique du daemon détecte les appareils à portée toutes les 30 s.
3. Cliquer sur **Ajouter**, saisir un nom et associer le sabre à un joueur.
4. Le daemon se connecte automatiquement et commence à recevoir les touches.

## Matériel utilisé

- Microcontrôleur **Seeed XIAO nRF52840 Sense** (CircuitPython) par sabre laser
- Capteur IMU **LSM6DS3TRC** (accéléromètre + gyroscope)
- **Raspberry Pi** avec Bluetooth intégré (serveur Docker)

## Détection de toucher (détail technique)

Le script `code.py` embarqué sur chaque sabre :
- calibre automatiquement la gravité locale au démarrage (au lieu d'une valeur fixe 9,8 m/s²) ;
- combine un seuil d'**intensité du choc** (`SEUIL_CHOC`) et de **jerk** (`SEUIL_JERK`) pour ne détecter que les vrais impacts ;
- stabilise la cadence à ~50 Hz en compensant le temps de calcul ;
- annonce un service **Nordic UART (NUS)** via BLE et envoie `"HIT\n"` à chaque toucher détecté ;
- affiche son adresse MAC BLE sur le port série au démarrage.

## Journal de bord

[Journal de bord](https://github.com/CFPT-ARASP-PH/2026-27_I.DA-P3B_CuTo/blob/main/journalDeBord.md)
