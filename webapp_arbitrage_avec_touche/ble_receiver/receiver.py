#!/usr/bin/env python3
"""
receiver.py — Daemon BLE pour sabres laser.

Rôle :
  - Se connecte simultanément à tous les sabres actifs enregistrés en base.
  - Détecte les touches (message "HIT" sur UART Nordic NUS).
  - Applique la règle double-touche (fenêtre 200 ms) :
      • Un seul sabre touche → type='touche'
      • Deux sabres touchent dans la fenêtre → type='double' pour les deux
  - Écrit le résultat dans la table `touchers` de MariaDB.
  - Scanne périodiquement les appareils BLE alentour et met à jour
    `ble_scan_cache` (visible dans Admin → Sabres).
  - Reconnecte automatiquement un sabre déconnecté.

Variables d'environnement (toutes optionnelles) :
  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
"""

import asyncio
import os
import time
import logging
from datetime import datetime

import pymysql
import pymysql.cursors
from bleak import BleakClient, BleakScanner

# ── Configuration ──────────────────────────────────────────────────────────────
UART_TX_CHAR_UUID   = "6e400003-b5a3-f393-e0a9-e50e24dcca9e"
DOUBLE_TOUCH_MS     = 200          # fenêtre double-touche en millisecondes
RETRY_DELAY_S       = 10           # attente avant reconnexion (secondes)
RELOAD_SABRES_S     = 30           # rechargement liste sabres depuis DB
SCAN_INTERVAL_S     = 30           # scan BLE périodique (mise à jour cache)
SCAN_DURATION_S     = 8.0          # durée d'un scan BLE

DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_NAME = os.getenv("DB_NAME", "sabre_arbitrage")
DB_USER = os.getenv("DB_USER", "arbitrage_user")
DB_PASS = os.getenv("DB_PASS", "arbitrage_pass")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
log = logging.getLogger("sabre_ble")

# ── Connexion DB ───────────────────────────────────────────────────────────────

def db_connect():
    return pymysql.connect(
        host=DB_HOST, port=DB_PORT,
        user=DB_USER, password=DB_PASS,
        database=DB_NAME,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
        connect_timeout=10,
    )


def db_retry(fn, retries=5, delay=3):
    """Appelle fn() avec auto-retry si la DB n'est pas encore prête."""
    for attempt in range(retries):
        try:
            return fn()
        except Exception as e:
            if attempt < retries - 1:
                log.warning("DB indisponible (%s), nouvel essai dans %ds…", e, delay)
                time.sleep(delay)
            else:
                raise


# ── Helpers DB ─────────────────────────────────────────────────────────────────

def load_sabres():
    """Retourne la liste des sabres actifs {id, mac_address, nom, joueur_id}."""
    conn = db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, mac_address, nom, joueur_id FROM sabres WHERE actif = 1"
            )
            return cur.fetchall()
    finally:
        conn.close()


def find_active_combat(joueur_id):
    """Retourne le combat 'en_cours' auquel participe ce joueur, ou None."""
    if joueur_id is None:
        return None
    conn = db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """SELECT id, joueur1_id, joueur2_id
                   FROM combats
                   WHERE statut = 'en_cours'
                     AND (joueur1_id = %s OR joueur2_id = %s)
                   LIMIT 1""",
                (joueur_id, joueur_id),
            )
            return cur.fetchone()
    finally:
        conn.close()


def insert_toucher(combat_id, sabre_id, joueur_id, timestamp_ms, type_):
    conn = db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """INSERT INTO touchers
                   (combat_id, sabre_id, joueur_id, timestamp_ms, type)
                   VALUES (%s, %s, %s, %s, %s)""",
                (combat_id, sabre_id, joueur_id, timestamp_ms, type_),
            )
    finally:
        conn.close()


def update_sabre_last_seen(mac):
    conn = db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE sabres SET last_seen = NOW() WHERE mac_address = %s",
                (mac,),
            )
    finally:
        conn.close()


def update_scan_cache(devices):
    """Insère ou met à jour les appareils BLE découverts dans ble_scan_cache."""
    if not devices:
        return
    conn = db_connect()
    try:
        with conn.cursor() as cur:
            for d in devices:
                cur.execute(
                    """INSERT INTO ble_scan_cache (mac_address, nom_ble, last_scanned)
                       VALUES (%s, %s, NOW())
                       ON DUPLICATE KEY UPDATE
                           nom_ble      = VALUES(nom_ble),
                           last_scanned = NOW()""",
                    (d.address, d.name or ""),
                )
    finally:
        conn.close()


# ── Gestion des double-touches ─────────────────────────────────────────────────
# pending_hits : { combat_id -> {'ts_ms': int, 'sabre_id': int, 'joueur_id': int} }
pending_hits: dict = {}
pending_lock = asyncio.Lock()


async def process_hit(sabre: dict):
    """
    Appelé lors d'un HIT reçu d'un sabre.
    Cherche le combat en cours, applique la logique double-touche.
    """
    sabre_id   = sabre["id"]
    joueur_id  = sabre["joueur_id"]
    nom        = sabre["nom"] or sabre["mac_address"]

    combat = find_active_combat(joueur_id)
    if combat is None:
        log.info("HIT de %s ignoré : aucun combat en cours pour ce joueur", nom)
        return

    combat_id  = combat["id"]
    ts_ms      = int(time.time() * 1000)

    async with pending_lock:
        existing = pending_hits.get(combat_id)

        if existing and (ts_ms - existing["ts_ms"]) <= DOUBLE_TOUCH_MS:
            # ── Double touche ──────────────────────────────────────────
            log.info(
                "DOUBLE TOUCHE combat %d : %s + sabre %d",
                combat_id, nom, existing["sabre_id"],
            )
            # Annule le pending et enregistre les deux comme 'double'
            del pending_hits[combat_id]
            insert_toucher(combat_id, existing["sabre_id"], existing["joueur_id"],
                           existing["ts_ms"], "double")
            insert_toucher(combat_id, sabre_id, joueur_id, ts_ms, "double")
        else:
            # ── Nouvelle touche en attente ─────────────────────────────
            pending_hits[combat_id] = {
                "ts_ms":    ts_ms,
                "sabre_id": sabre_id,
                "joueur_id": joueur_id,
            }
            # Planifie la validation après la fenêtre
            asyncio.get_event_loop().call_later(
                DOUBLE_TOUCH_MS / 1000 + 0.01,
                lambda cid=combat_id, sid=sabre_id, jid=joueur_id, ts=ts_ms:
                    asyncio.ensure_future(validate_hit(cid, sid, jid, ts)),
            )


async def validate_hit(combat_id, sabre_id, joueur_id, ts_ms):
    """Valide une touche si elle n'a pas été annulée par une double-touche."""
    async with pending_lock:
        p = pending_hits.get(combat_id)
        if p and p["ts_ms"] == ts_ms and p["sabre_id"] == sabre_id:
            del pending_hits[combat_id]
            log.info("TOUCHÉ combat %d sabre %d joueur %s", combat_id, sabre_id, joueur_id)
            insert_toucher(combat_id, sabre_id, joueur_id, ts_ms, "touche")


# ── Connexion BLE à un sabre ───────────────────────────────────────────────────

async def connect_sabre(sabre: dict):
    """Tâche asyncio : maintient la connexion BLE avec un sabre et traite ses HIT."""
    mac = sabre["mac_address"]
    nom = sabre["nom"] or mac

    def on_notification(sender, data: bytearray):
        msg = data.decode("utf-8", errors="ignore").strip()
        if msg == "HIT":
            log.info("HIT reçu de %s", nom)
            asyncio.ensure_future(process_hit(sabre))

    while True:
        try:
            log.info("Recherche de %s (%s)…", nom, mac)
            appareil = await BleakScanner.find_device_by_address(mac, timeout=15.0)
            if appareil is None:
                log.warning("%s introuvable, nouvel essai dans %ds", nom, RETRY_DELAY_S)
                await asyncio.sleep(RETRY_DELAY_S)
                continue

            log.info("Connexion à %s…", nom)
            async with BleakClient(appareil, timeout=20.0) as client:
                log.info("✓ Connecté à %s", nom)
                update_sabre_last_seen(mac)
                await client.start_notify(UART_TX_CHAR_UUID, on_notification)

                # Maintient la connexion vivante
                while client.is_connected:
                    await asyncio.sleep(1)
                    update_sabre_last_seen(mac)

                log.warning("%s déconnecté", nom)

        except Exception as e:
            log.error("Erreur connexion %s : %s", nom, e)

        log.info("Reconnexion de %s dans %ds…", nom, RETRY_DELAY_S)
        await asyncio.sleep(RETRY_DELAY_S)


# ── Scan périodique ────────────────────────────────────────────────────────────

async def periodic_scan():
    """Scanne l'environnement BLE et met à jour ble_scan_cache toutes les N secondes."""
    while True:
        await asyncio.sleep(SCAN_INTERVAL_S)
        try:
            log.info("Scan BLE en cours (%.0fs)…", SCAN_DURATION_S)
            devices = await BleakScanner.discover(timeout=SCAN_DURATION_S)
            log.info("%d appareils BLE trouvés", len(devices))
            update_scan_cache(devices)
        except Exception as e:
            log.error("Erreur scan BLE : %s", e)


# ── Rechargement dynamique des sabres ─────────────────────────────────────────

async def sabre_manager(running_tasks: dict):
    """
    Recharge la liste des sabres depuis la DB toutes les N secondes.
    Lance une tâche asyncio pour chaque nouveau sabre, annule celles
    dont le sabre a été désactivé.
    """
    while True:
        await asyncio.sleep(RELOAD_SABRES_S)
        try:
            sabres = load_sabres()
            ids_actifs = {s["id"] for s in sabres}

            # Lance les nouveaux sabres
            for s in sabres:
                if s["id"] not in running_tasks:
                    log.info("Nouveau sabre détecté en DB : %s", s["nom"] or s["mac_address"])
                    task = asyncio.create_task(connect_sabre(s))
                    running_tasks[s["id"]] = task

            # Annule les sabres supprimés / désactivés
            for sid in list(running_tasks):
                if sid not in ids_actifs:
                    log.info("Sabre %d désactivé, arrêt de la tâche", sid)
                    running_tasks[sid].cancel()
                    del running_tasks[sid]

        except Exception as e:
            log.error("Erreur rechargement sabres : %s", e)


# ── Point d'entrée ─────────────────────────────────────────────────────────────

async def main():
    log.info("=== Daemon BLE Sabre Laser démarrage ===")
    log.info("DB : %s@%s:%d/%s", DB_USER, DB_HOST, DB_PORT, DB_NAME)

    # Attend que la DB soit prête
    log.info("Attente de la base de données…")
    sabres = db_retry(load_sabres)
    log.info("%d sabre(s) actif(s) chargé(s)", len(sabres))

    running_tasks: dict = {}

    # Démarre une tâche de connexion par sabre
    for s in sabres:
        task = asyncio.create_task(connect_sabre(s))
        running_tasks[s["id"]] = task

    # Tâches de fond
    asyncio.create_task(periodic_scan())
    asyncio.create_task(sabre_manager(running_tasks))

    # Boucle infinie
    while True:
        await asyncio.sleep(3600)


if __name__ == "__main__":
    asyncio.run(main())
