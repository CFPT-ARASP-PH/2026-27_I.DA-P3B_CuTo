### Journal de bord

# 27.08 :

## Brainstorming :
- Réflexion sur l'implémentation générale du projet CuTo : comment faire communiquer les sabres lasers avec l'interface web.
- Discussion sur la détection des collisions (toucher) : faut-il se baser uniquement sur l'intensité du choc, ou aussi sur la brutalité du mouvement (jerk) pour ne pas confondre un vrai coup avec un simple swing rapide.
- Réflexion sur l'interprétation de la couleur du sabre selon l'état (idle / toucher détecté), en vue d'un futur retour visuel sur le sabre et/ou sur l'interface web.
- Définition de l'architecture générale du système : sabre laser → Bluetooth → Raspberry Pi → serveur → interface web.

## Lucas :
- Écriture d'un script Python de test (`code.py`) pour la détection de toucher sur le sabre laser, exécuté sur un microcontrôleur **Seeed XIAO nRF52840 Sense** (CircuitPython) équipé d'un capteur IMU **LSM6DS3TRC** (accéléromètre + gyroscope).
- Implémentation de la lecture continue de l'IMU (accélération et gyroscope) à une cadence stabilisée d'environ 50 Hz.
- Mise en place d'une **calibration automatique de la gravité locale** au démarrage (moyenne sur 100 échantillons, ~2 secondes), afin de compenser le biais propre à chaque capteur plutôt que d'utiliser une valeur fixe de 9.8 m/s².
- Implémentation de la logique de détection de toucher combinant deux critères :
  - l'**intensité du choc** (écart entre l'accélération mesurée et la gravité locale, seuil fixé à 35 m/s²) ;
  - la **brutalité du mouvement / jerk** (variation d'accélération entre deux échantillons consécutifs, seuil fixé à 18 m/s²) ;
  - un toucher n'est validé que si les deux seuils sont dépassés simultanément, ce qui permet de distinguer un vrai impact d'un simple swing rapide dans le vide.
- Ajout d'un système de **cooldown** (0,6 seconde) après un toucher détecté, pour éviter les doubles comptages.
- Gestion de la LED RGB embarquée du microcontrôleur : allumage en bleu pendant la calibration, en rouge pendant 0,3 seconde lors d'un toucher détecté, clignotement rouge continu en cas d'erreur matérielle (capteur non détecté au démarrage).
- Mise en place de l'envoi en continu, sur le port série, d'une trame de données au format :
  ```
  D,ax,ay,az,gx,gy,gz,hit
  ```
  avec `ax,ay,az` les accélérations, `gx,gy,gz` les vitesses angulaires, et `hit` valant `1` si un toucher est actif, `0` sinon.
- Tests effectués en local (branchement du sabre en USB, lecture de la sortie série) pour valider la fiabilité de la détection.

## Tom :
- Création d'une interface web de test permettant d'afficher les données reçues, en particulier l'état du toucher (`hit`).
- Objectif de cette première version : valider qu'une page web est capable de recevoir/afficher une donnée simple (toucher détecté ou non) avant d'intégrer la vraie chaîne de communication (Bluetooth + Raspberry Pi).
- Réflexion sur la structure de la future interface (affichage en temps réel, historique des touchers).

---

# 03.09 :

## Tom :
- Mise en place complète de la configuration du **Raspberry Pi**, en vue d'y héberger le serveur et l'interface web du projet.
- **Installation de base** : flash de Raspberry Pi OS (64-bit) via Raspberry Pi Imager, activation du SSH et du Wi-Fi, création d'un utilisateur/mot de passe personnalisé (suppression des identifiants par défaut `pi`/`raspberry` pour la sécurité), mise à jour complète du système (`apt update && apt full-upgrade`).
- **Configuration Bluetooth** :
  - installation des paquets nécessaires (`bluetooth`, `bluez`, `bluez-tools`) et activation du service au démarrage ;
  - vérification de l'adaptateur Bluetooth du Raspberry (`bluetoothctl list` / `show`) ;
  - mise en place de la procédure d'appairage (pairing) avec un sabre laser via `bluetoothctl` (`scan on`, `pair`, `trust`, `connect`), à répéter pour chaque sabre.
- **Sécurisation / chiffrement de la liaison Bluetooth** :
  - configuration de `/etc/bluetooth/main.conf` pour forcer un pairing sécurisé (BLE Secure Connections) ;
  - remplacement de l'agent par défaut (`agent on`, mode "Just Works" peu sécurisé) par un agent `KeyboardOnly` demandant un code PIN à la connexion ;
  - mise en place d'une liste blanche (whitelist) d'appareils de confiance via `trust`/`remove`, pour n'autoriser que les sabres laser officiels à se connecter ;
  - ajout d'un **chiffrement applicatif** des données en complément du chiffrement natif Bluetooth : utilisation d'une clé partagée symétrique (bibliothèque Python `cryptography`, `Fernet`) pour chiffrer chaque trame côté sabre avant envoi, et la déchiffrer côté Raspberry à la réception ;
  - définition de bonnes pratiques générales : nom Bluetooth du Raspberry non identifiable, désactivation du mode `Discoverable` une fois les sabres appairés, mises à jour régulières du système, isolation du Raspberry sur un réseau Wi-Fi dédié si possible.
- **Mise en place du serveur et de l'interface web (stack Apache + PHP + MariaDB)** :
  - installation d'Apache, PHP (avec l'extension `php-mysqli`) et MariaDB ;
  - sécurisation de l'installation MariaDB (`mysql_secure_installation`) : mot de passe root, suppression des utilisateurs anonymes et de la base de test, désactivation de l'accès root distant ;
  - création de la base de données `cuto` et d'un **utilisateur applicatif dédié** (`cuto_app`) avec des droits limités (`SELECT`, `INSERT`, `UPDATE` uniquement), afin de ne jamais utiliser le compte root dans le code applicatif ;
  - conception du schéma de base de données : table `sabres` (id, nom, adresse MAC) et table `touchers` (id, sabre concerné, date/heure, intensité du choc) ;
  - définition de l'arborescence du projet : dossier web (`/var/www/html/cuto/` avec `index.php`, `config.php`, `assets/`) séparé du dossier de réception Bluetooth (`~/cuto-bluetooth/` avec le script Python `recepteur.py` et la clé de chiffrement) ;
  - écriture du fichier `config.php` de connexion à MariaDB via `mysqli`, avec consigne de ne jamais versionner ce fichier sur Git (identifiants sensibles) ;
  - mise en place du script Python `recepteur.py`, chargé de recevoir les trames Bluetooth chiffrées envoyées par les sabres, de les déchiffrer, puis d'insérer un enregistrement dans la table `touchers` de MariaDB lorsqu'un toucher est détecté (`hit == 1`) ;
  - test de lancement manuel : interface web accessible sur `http://ip_du_raspberry/cuto/`, script de réception lancé en parallèle dans son environnement virtuel Python.
- **Démarrage automatique** : création d'un service `systemd` (`cuto-bluetooth.service`) pour que le script Python de réception Bluetooth démarre automatiquement au boot du Raspberry (Apache et MariaDB étant déjà configurés pour démarrer seuls via `systemctl enable`).
- Rédaction d'un récapitulatif des points de sécurité mis en place (pairing Bluetooth sécurisé, whitelist d'appareils, chiffrement applicatif, stockage protégé de la clé, utilisateur MariaDB à droits limités, fichier `config.php` non versionné, accès réseau limité).

## Lucas 
- Absen
