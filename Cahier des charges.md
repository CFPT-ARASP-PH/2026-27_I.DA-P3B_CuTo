# Cahier des charges — Projet CuTo
**Système d'arbitrage de sabres laser connectés**

---

| | |
|---|---|
| **Projet** | CuTo — Arbitrage de sabres laser |
| **Équipe** | Lucas · Tom |
| **Établissement** | CFPT — Centre de Formation Professionnelle Technique (Genève) |
| **Filière** | Informatique — 3ème année |
| **Année scolaire** | 2026-2027 |
| **Date de rédaction** | 17 septembre 2026 |
| **Version** | 1.0 |

---

## Table des matières

1. [Présentation du projet](#1-présentation-du-projet)
2. [Objectifs du projet](#2-objectifs-du-projet)
3. [Périmètre et exigences fonctionnelles](#3-périmètre-et-exigences-fonctionnelles)
4. [Architecture technique](#4-architecture-technique)
5. [Exigences techniques](#5-exigences-techniques)
6. [Contraintes et règles métier](#6-contraintes-et-règles-métier)
7. [Interfaces utilisateur](#7-interfaces-utilisateur)
8. [Planification et phases](#8-planification-et-phases)
9. [Livrables](#9-livrables)
10. [Glossaire](#10-glossaire)

---

## 1. Présentation du projet

### 1.1 Contexte

Dans le cadre de la formation en informatique au CFPT de Genève, les étudiants de 3ème année réalisent un projet annuel intégrant plusieurs disciplines : développement web, réseaux, systèmes embarqués et infrastructure. Le projet **CuTo** (Cuts & Touches) est né de la volonté de combiner l'atelier Raspberry Pi, déjà mené en cours, avec une application web d'arbitrage sportif.

Les sabres laser de fiction sont devenus un vrai sport de combat (combat de sabre laser / Sabre Fight). Dans ce sport, chaque touche doit être arbitrée avec précision. Le projet CuTo vise à automatiser cette détection grâce à des microcontrôleurs embarqués dans les sabres, supprimant ainsi l'arbitrage humain subjectif et permettant un affichage en temps réel des points.

### 1.2 Équipe

| Prénom | Rôle principal | Responsabilités |
|---|---|---|
| **Lucas** | Développeur full-stack / Infrastructure | CSS/Design, backend PHP, API REST, documentation README, architecture réseau |
| **Tom** | Développeur full-stack / Systèmes embarqués | Microcontrôleur CircuitPython, dockerisation, daemon BLE Python, interface admin, base de données |

### 1.3 Périmètre du document

Ce document décrit les exigences fonctionnelles et techniques du système CuTo. Il sert de référence pour le développement, la recette et la présentation finale du projet. Il est destiné aux enseignants superviseurs et à l'équipe de développement.

---

## 2. Objectifs du projet

### 2.1 Objectif général

Concevoir et déployer un système complet permettant d'arbitrer automatiquement des combats de sabres laser en détectant les touches physiques par voie Bluetooth Low Energy (BLE), en les enregistrant en base de données, et en les affichant en temps réel sur une interface web accessible depuis un navigateur.

### 2.2 Objectifs spécifiques

- Détecter les impacts sur un sabre laser à l'aide d'un capteur IMU embarqué.
- Transmettre chaque touche via BLE (Nordic UART Service) vers un serveur central.
- Appliquer la règle de double-touche : si deux sabres touchent dans une fenêtre de 200 ms, la touche est annulée pour les deux.
- Enregistrer toutes les touches (validées ou annulées) en base de données, horodatées à la milliseconde.
- Afficher les touches en temps réel à l'arbitre et sur l'écran public de la salle.
- Offrir une interface d'administration pour gérer les joueurs, les sabres et les combats.
- Déployer l'ensemble du système via une seule commande Docker.

### 2.3 Critères de succès

| Critère | Indicateur de mesure | Cible |
|---|---|---|
| Fiabilité BLE | Taux de touche reçue / touche réelle | > 95 % |
| Latence d'affichage | Délai HIT → affichage écran | < 500 ms |
| Double-touche | Fenêtre de détection simultanée | 200 ms |
| Disponibilité | Uptime pendant un combat | 100 % |
| Déploiement | Commandes pour lancer le système | 1 (`docker compose up`) |

---

## 3. Périmètre et exigences fonctionnelles

### 3.1 Détection des touches (côté sabre)

**EF-01 — Détection d'impact** : Le microcontrôleur doit détecter une touche en combinant deux critères simultanés :
- Intensité du choc : l'accélération totale dépasse un seuil au-dessus de la gravité locale (calibrée automatiquement au démarrage).
- Brutalité (jerk) : la variation d'accélération entre deux mesures consécutives dépasse un seuil distinct.

**EF-02 — Cooldown** : Après une touche, un délai de 600 ms est appliqué avant qu'une nouvelle touche puisse être détectée sur le même sabre.

**EF-03 — Indication visuelle** : La LED du microcontrôleur s'allume en rouge lors d'une touche détectée.

**EF-04 — Transmission BLE** : Le message `"HIT\n"` est envoyé via le service Nordic UART Service (NUS) BLE dès la détection.

**EF-05 — Calibration automatique** : Au démarrage, le sabre calibre la gravité locale en faisant la moyenne des 50 premières lectures de l'accéléromètre.

### 3.2 Réception et traitement BLE (côté serveur)

**EF-06 — Daemon BLE** : Un service Python persistant maintient une connexion BLE permanente avec chaque sabre enregistré, et se reconnecte automatiquement en cas de déconnexion (délai 10 s).

**EF-07 — Règle de double-touche** : Si deux sabres envoient "HIT" dans une fenêtre de 200 ms pour un même combat, les deux touches sont enregistrées avec le type `"double"` (annulées). Sinon, la touche est enregistrée comme `"touche"` (validée).

**EF-08 — Horodatage** : Chaque touche est horodatée en Unix milliseconde au moment de la réception sur le Raspberry Pi.

**EF-09 — Rechargement dynamique** : Le daemon recharge la liste des sabres depuis la base de données toutes les 30 secondes, sans redémarrage.

**EF-10 — Scan BLE périodique** : Le daemon scanne les appareils BLE à portée toutes les 30 secondes et met à jour un cache des appareils découverts.

### 3.3 Gestion des combats et du tournoi

**EF-11 — Création de combat** : L'arbitre peut créer un combat en sélectionnant deux joueurs et en définissant un timer.

**EF-12 — Statut de combat** : Un combat peut avoir les statuts : `en_attente`, `en_cours`, `terminé`. Les touches BLE ne sont enregistrées que pour les combats `en_cours`.

**EF-13 — Gestion des tournois** : L'interface permet de gérer un tournoi (inscription des joueurs, génération des matchs, classement, arbre de tournoi).

### 3.4 Affichage temps réel

**EF-14 — Overlay arbitre** : L'interface arbitre (`saisie.php`) affiche une section « Touches électroniques » avec le log des touches en temps réel, actualisé par polling toutes les secondes.

**EF-15 — Overlay écran public** : La page écran public (`ecran.php`) affiche un overlay plein écran pendant 2,5 secondes : « TOUCHÉ ! » (fond vert) pour une touche validée, « DOUBLE TOUCHE — Annulé » (fond orange) pour une double-touche.

**EF-16 — API temps réel** : Un endpoint `api/ble_touches.php` expose les nouvelles touches en JSON, filtré par `combat_id` et `since_id`.

### 3.5 Administration des sabres

**EF-17 — Gestion des sabres** : La page d'administration (`admin/sabres.php`) liste les sabres enregistrés avec leur statut de connexion (vert/orange/rouge selon `last_seen`).

**EF-18 — Découverte automatique** : Les appareils BLE détectés par le scan périodique s'affichent dans l'admin, avec un bouton « Ajouter » pour les enregistrer.

**EF-19 — Mapping sabre/joueur** : Chaque sabre est associé de façon fixe à un joueur dans l'administration (pas de changement de mapping dynamique en cours de combat).

---

## 4. Architecture technique

### 4.1 Vue d'ensemble

Le système est composé de trois niveaux : les sabres laser (matériel embarqué), le serveur Raspberry Pi (Docker), et les clients web (navigateurs).

```
Sabre laser (XIAO nRF52840 Sense + IMU LSM6DS3TRC)
        │
        │  BLE — Nordic UART Service (NUS)
        ▼
   ┌─────────────────────────────────────────────┐
   │              Raspberry Pi (Docker)          │
   │                                             │
   │  ┌──────────────┐   ┌────────────────────┐  │
   │  │  sabre_ble   │   │   sabre_web        │  │
   │  │  Python/Bleak│──▶│   PHP 8.2 + Apache │  │
   │  └──────┬───────┘   └────────┬───────────┘  │
   │         └──────┬─────────────┘              │
   │                ▼                            │
   │         ┌─────────────┐                     │
   │         │  sabre_db   │                     │
   │         │  MariaDB 11 │                     │
   │         └─────────────┘                     │
   │         ┌──────────────┐                    │
   │         │ sabre_tunnel │                    │
   │         │  Cloudflare  │                    │
   │         └──────────────┘                    │
   └─────────────────────────────────────────────┘
        │
        │  HTTP / Cloudflare Tunnel (HTTPS public)
        ▼
   Navigateur (arbitre, spectateur, admin)
```

### 4.2 Services Docker

| Service | Image / Base | Rôle |
|---|---|---|
| `sabre_db` | `mariadb:11` | Base de données relationnelle (combats, joueurs, sabres, touches) |
| `sabre_web` | `php:8.2-apache` | Serveur web PHP — interface d'arbitrage, admin, classement, écran public |
| `sabre_ble` | `python:3.11-slim` | Daemon BLE : connexion aux sabres, détection double-touche, écriture DB |
| `sabre_tunnel` | `cloudflare/cloudflared` | Tunnel HTTPS public sans ouverture de port routeur |

### 4.3 Schéma de base de données (tables principales)

| Table | Description |
|---|---|
| `joueurs` | Joueurs inscrits au tournoi (id, nom, prénom, pseudo, email…) |
| `combats` | Combats (id, joueur1_id, joueur2_id, statut, score, timer…) |
| `sabres` | Sabres BLE enregistrés (id, mac_address, nom, joueur_id, last_seen) |
| `touchers` | Touches enregistrées (id, combat_id, sabre_id, timestamp_ms, type) |
| `ble_scan_cache` | Appareils BLE découverts lors des scans périodiques |
| `tournois` | Tournois (id, nom, format, statut…) |

### 4.4 Flux de données — détection d'une touche

1. Le sabre détecte un impact (accélération + jerk > seuils).
2. Le microcontrôleur envoie `"HIT\n"` via BLE NUS.
3. Le daemon Python reçoit la notification BLE.
4. Le daemon vérifie s'il y a un combat actif pour ce joueur.
5. La logique de double-touche s'applique (fenêtre 200 ms avec `asyncio.Lock`).
6. La touche est insérée dans la table `touchers` avec son type et timestamp.
7. Le front-end JS interroge `api/ble_touches.php` en polling (toutes les 1-2 s).
8. L'overlay s'affiche sur l'interface arbitre ET l'écran public.

---

## 5. Exigences techniques

### 5.1 Matériel requis

| Composant | Modèle / Spec | Quantité | Rôle |
|---|---|---|---|
| Microcontrôleur sabre | Seeed XIAO nRF52840 Sense | 2 (min.) | Embarqué dans chaque sabre laser |
| Capteur IMU | LSM6DS3TRC (intégré au XIAO) | 2 (min.) | Accéléromètre + gyroscope pour détection |
| Serveur central | Raspberry Pi (4 ou 5 recommandé) | 1 | Héberge les 4 services Docker |
| Bluetooth | Adaptateur BT 4.0+ intégré | 1 | Connexion BLE avec les sabres |
| Alimentation Pi | 5V / 3A (USB-C) | 1 | — |
| Réseau local | Wi-Fi ou câble Ethernet | 1 | Accès à l'interface web |

### 5.2 Logiciels et dépendances

| Composant | Version | Usage |
|---|---|---|
| CircuitPython | 9.x | Firmware microcontrôleur sabre (XIAO nRF52840 Sense) |
| adafruit_ble | Bibliothèque | Service Nordic UART BLE côté sabre |
| Docker Engine | 24+ | Orchestration des services sur Raspberry Pi |
| Docker Compose | V2 (plugin) | Fichier `docker-compose.yml` — lancement en une commande |
| MariaDB | 11 | Base de données relationnelle |
| PHP | 8.2 | Backend web et API REST |
| Apache | 2.4 | Serveur HTTP (avec `mod_rewrite`) |
| Python | 3.11 | Daemon BLE (service `sabre_ble`) |
| Bleak | 0.22.3 | Bibliothèque BLE asyncio pour Python |
| PyMySQL | 1.1.1 | Connexion MySQL/MariaDB depuis Python |
| Cloudflared | latest | Tunnel Cloudflare HTTPS public |

### 5.3 Variables d'environnement (`.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `APP_PORT` | `8080` | Port HTTP local du serveur web |
| `DB_ROOT_PASSWORD` | `rootpass` | Mot de passe root MariaDB |
| `DB_NAME` | `sabre_arbitrage` | Nom de la base de données |
| `DB_USER` | `arbitrage_user` | Utilisateur applicatif MariaDB |
| `DB_PASS` | `arbitrage_pass` | Mot de passe utilisateur MariaDB |
| `BASE_URL` | `/` | Chemin de base de l'application web |

---

## 6. Contraintes et règles métier

### 6.1 Contraintes techniques

**CT-01 — BLE dans Docker** : Le conteneur `sabre_ble` doit être lancé avec `network_mode: host`, `privileged: true` et le montage `/var/run/dbus` pour accéder à l'adaptateur BlueZ du Raspberry Pi.

**CT-02 — Port MariaDB** : Le port 3306 de MariaDB est exposé sur `127.0.0.1:3306` (loopback uniquement) pour que le conteneur BLE en mode host puisse l'atteindre.

**CT-03 — Distance BLE** : La portée BLE effective est d'environ 10-15 mètres en ligne de vue. La zone de combat doit être dans ce rayon du Raspberry Pi.

**CT-04 — Nombre de sabres** : Le système est conçu pour 2 sabres simultanés (1 par joueur par combat). Extension possible sans modification majeure.

**CT-05 — Fréquence IMU** : L'IMU est échantillonné à ~50 Hz. La détection est impossible en dessous de 20 Hz.

### 6.2 Règles métier

**RM-01 — Touche validée** : Un seul sabre envoie HIT pendant la fenêtre de 200 ms → la touche est enregistrée avec `type = "touche"`. Le joueur adverse marque un point.

**RM-02 — Double-touche** : Les deux sabres envoient HIT dans une fenêtre de 200 ms → les deux touches sont enregistrées avec `type = "double"`. Aucun point n'est attribué.

**RM-03 — Combat actif** : Les touches ne sont enregistrées que si un combat est au statut `"en_cours"`. Les touches hors combat sont ignorées (log seulement).

**RM-04 — Mapping fixe** : L'association sabre ↔ joueur est définie dans l'administration et ne change pas en cours de combat.

**RM-05 — Cooldown sabre** : Après une touche, le sabre attend 600 ms avant de pouvoir en envoyer une nouvelle (géré côté firmware).

### 6.3 Sécurité

- L'interface d'administration et d'arbitrage est protégée par authentification (login / mot de passe).
- L'API `ble_touches.php` ne retourne que les données du combat demandé.
- Le port 3306 (MariaDB) n'est pas exposé sur le réseau public (`127.0.0.1` uniquement).
- Les variables sensibles (mots de passe) sont gérées via le fichier `.env`, exclu du dépôt Git.

---

## 7. Interfaces utilisateur

### 7.1 Pages de l'application web

| Page | Accès | Fonctionnalité principale |
|---|---|---|
| `index.php` | Public | Page d'accueil — présentation du tournoi en cours |
| `classement.php` | Public | Classement général des joueurs |
| `arbre.php` | Public | Visualisation du bracket de tournoi (style arbre) |
| `ecran.php` | Public | Écran de la salle — combats en cours, scores, overlay TOUCHÉ |
| `arbitrage/saisie.php` | Arbitre / Admin | Saisie des points, gestion du timer, log touches BLE |
| `admin/sabres.php` | Admin | Gestion des sabres BLE (scan, ajout, suppression, mapping joueur) |
| `admin/dashboard.php` | Admin | Tableau de bord — gestion globale du tournoi et des joueurs |
| `api/ble_touches.php` | Interne (JS) | Endpoint JSON — polling temps réel des nouvelles touches |

### 7.2 Interface de l'arbitre (`saisie.php`)

La page d'arbitrage affiche, outre les boutons de saisie manuelle des points :
- Un panneau « Touches électroniques » actualisé toutes les secondes.
- Chaque touche BLE apparaît dans un log coloré : vert pour "TOUCHÉ" (avec le pseudo du joueur), orange pour "DOUBLE TOUCHE — Annulé".
- Un overlay flash (2,5 s) à chaque nouvelle touche.

### 7.3 Écran public (`ecran.php`)

- Overlay plein écran déclenché par chaque nouvelle touche BLE.
- Fond vert + "TOUCHÉ !" + pseudo du joueur pour une touche validée.
- Fond orange + "DOUBLE TOUCHE" pour une touche annulée.
- Disparition automatique après 2,5 secondes.

### 7.4 Administration sabres (`admin/sabres.php`)

- Liste des sabres enregistrés avec statut de connexion (🟢 < 1 min, 🟠 < 5 min, 🔴 sinon).
- Section « Appareils BLE détectés » — appareils vus par le scan mais non enregistrés, avec bouton Ajouter.
- Formulaire d'ajout : adresse MAC, nom, joueur associé.
- Modification et suppression d'un sabre existant.

---

## 8. Planification et phases

| Phase | Période | Description | Responsable |
|---|---|---|---|
| Phase 0 — Cadrage | Août 2026 | Choix du projet, mise en place postes de travail, maquettes UI | Tom + Lucas |
| Phase 1 — Développement web | Août – Sept 2026 | Base de données, backend PHP, CSS/design, API REST, dockerisation complète | Tom + Lucas |
| Phase 2 — Intégration BLE | Sept – Oct 2026 | Daemon Python BLE, tables sabres/touchers, admin sabres, overlay temps réel | Tom |
| Phase 3 — Tests & ajustements | Oct – Nov 2026 | Tests d'intégration, calibrage seuils IMU, test double-touche, correctifs | Tom + Lucas |
| Phase 4 — Documentation | Nov – Déc 2026 | README, cahier des charges, journal de bord, présentation | Tom + Lucas |
| Phase 5 — Présentation finale | Janv 2027 | Démonstration en conditions réelles, présentation aux enseignants | Tom + Lucas |

### 8.1 Jalons clés

| Date | Jalon |
|---|---|
| 17.08.2026 | Démarrage officiel du projet — choix du sujet |
| 07.09.2026 | Dockerisation complète de l'application web (1 commande) |
| 17.09.2026 | Intégration BLE complète — daemon Python + tables DB + admin + overlay temps réel |
| Oct 2026 | Tests d'intégration sur matériel réel (sabres + Raspberry Pi) |
| Déc 2026 | Version finale livrée — documentation complète |
| Janv 2027 | Présentation et démonstration devant le jury |

---

## 9. Livrables

| Livrable | Format | Description |
|---|---|---|
| Code source complet | Git (GitHub CFPT) | Dépôt incluant tout le code : firmware CircuitPython, daemon Python, application PHP, Dockerfile, docker-compose.yml |
| Cahier des charges | `.md` / `.docx` / `.pdf` | Ce document — spécifications fonctionnelles et techniques du projet |
| Journal de bord | Markdown (GitHub) | Compte-rendu hebdomadaire des activités de Tom et Lucas |
| README.md | Markdown (GitHub) | Documentation technique : démarrage rapide, architecture, variables d'environnement, gestion des sabres BLE |
| Base de données | SQL (schema + migrations) | Fichiers `docker/init.sql` et `sql/migration_ble.sql` pour l'installation et la migration |
| Matériel fonctionnel | Hardware | Deux sabres laser équipés de XIAO nRF52840 Sense opérationnels |
| Démonstration live | Présentation orale | Démonstration du système complet en conditions réelles devant les enseignants |

---

## 10. Glossaire

| Terme | Définition |
|---|---|
| **BLE** | Bluetooth Low Energy — protocole de communication sans fil à faible consommation énergétique |
| **BlueZ** | Pile Bluetooth officielle du noyau Linux |
| **Bleak** | Bibliothèque Python cross-platform pour BLE (basée sur asyncio) |
| **NUS** | Nordic UART Service — profil GATT simulant une liaison UART sur BLE |
| **IMU** | Inertial Measurement Unit — centrale inertielle (accéléromètre + gyroscope) |
| **Jerk** | Variation d'accélération entre deux mesures consécutives (dérivée de l'accélération) |
| **Cooldown** | Délai minimum entre deux touches détectables sur un même sabre |
| **Double-touche** | Situation où deux sabres touchent simultanément dans une fenêtre de 200 ms — résultat annulé |
| **MariaDB** | Système de gestion de base de données relationnelle, fork communautaire de MySQL |
| **Docker Compose** | Outil de définition et d'orchestration de conteneurs Docker multi-services |
| **Cloudflare Tunnel** | Service permettant d'exposer un serveur local via HTTPS sans ouvrir de port dans la box |
| **XIAO nRF52840 Sense** | Microcontrôleur de Seeed Studio, avec Bluetooth 5.0 BLE et IMU LSM6DS3TRC intégré |
| **CircuitPython** | Variante de MicroPython optimisée par Adafruit pour microcontrôleurs |
| **PyMySQL** | Client MySQL pur Python — utilisé par le daemon BLE pour écrire dans MariaDB |
| **asyncio** | Bibliothèque Python de programmation asynchrone — utilisée pour gérer plusieurs sabres en parallèle |
