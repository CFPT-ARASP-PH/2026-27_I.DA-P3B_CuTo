<?php
/**
 * api/ble_touches.php — Retourne les dernières touches BLE d'un combat.
 *
 * GET ?combat_id=X[&since_id=Y]
 * Réponse JSON :
 *   { "touches": [ { id, type, joueur_num, joueur_pseudo, timestamp_ms, created_at } ] }
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$combatId = (int)($_GET['combat_id'] ?? 0);
$sinceId  = (int)($_GET['since_id']  ?? 0);

if ($combatId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'combat_id requis']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        t.id,
        t.type,
        t.timestamp_ms,
        t.created_at,
        t.joueur_id,
        CASE
            WHEN c.joueur1_id = t.joueur_id THEN 1
            WHEN c.joueur2_id = t.joueur_id THEN 2
            ELSE NULL
        END AS joueur_num,
        CASE
            WHEN c.joueur1_id = t.joueur_id THEN j1.pseudo
            WHEN c.joueur2_id = t.joueur_id THEN j2.pseudo
            ELSE '?'
        END AS joueur_pseudo
     FROM touchers t
     JOIN combats c  ON c.id  = t.combat_id
     JOIN joueurs j1 ON j1.id = c.joueur1_id
     JOIN joueurs j2 ON j2.id = c.joueur2_id
     WHERE t.combat_id = ?
       AND t.id > ?
     ORDER BY t.id DESC
     LIMIT 20"
);
$stmt->execute([$combatId, $sinceId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['touches' => array_reverse($rows)]);
