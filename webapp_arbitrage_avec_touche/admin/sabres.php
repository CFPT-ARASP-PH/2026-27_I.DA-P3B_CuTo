<?php
/**
 * admin/sabres.php — Gestion des sabres BLE.
 *
 * Fonctionnalités :
 *   - Liste des sabres enregistrés avec statut de connexion
 *   - Ajout manuel (adresse MAC + nom) ou depuis le cache de scan
 *   - Association sabre ↔ joueur (mapping fixe)
 *   - Activation / désactivation / suppression
 */
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

/* ── ACTIONS POST ─────────────────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        flash('error', 'Session expirée, veuillez réessayer.');
        redirect('admin/sabres.php');
    }

    // Ajouter un sabre
    if ($action === 'add') {
        $mac = strtoupper(trim($_POST['mac_address'] ?? ''));
        $nom = trim($_POST['nom'] ?? '');
        $joueurId = (int)($_POST['joueur_id'] ?? 0) ?: null;

        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            flash('error', 'Adresse MAC invalide (format attendu : AA:BB:CC:DD:EE:FF).');
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO sabres (mac_address, nom, joueur_id) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE nom = VALUES(nom), joueur_id = VALUES(joueur_id), actif = 1"
                )->execute([$mac, $nom, $joueurId]);
                flash('success', "Sabre <strong>" . htmlspecialchars($nom ?: $mac) . "</strong> enregistré.");
            } catch (PDOException $e) {
                flash('error', 'Erreur : ' . $e->getMessage());
            }
        }
        redirect('admin/sabres.php');
    }

    // Modifier un sabre
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $joueurId = (int)($_POST['joueur_id'] ?? 0) ?: null;
        $actif = isset($_POST['actif']) ? 1 : 0;
        $pdo->prepare(
            "UPDATE sabres SET nom = ?, joueur_id = ?, actif = ? WHERE id = ?"
        )->execute([$nom, $joueurId, $actif, $id]);
        flash('success', 'Sabre mis à jour.');
        redirect('admin/sabres.php');
    }

    // Supprimer un sabre
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM sabres WHERE id = ?")->execute([$id]);
        flash('success', 'Sabre supprimé.');
        redirect('admin/sabres.php');
    }
}

/* ── Chargement des données ───────────────────────────────────────────────── */

$sabres = $pdo->query(
    "SELECT s.*, j.pseudo AS joueur_pseudo,
            TIMESTAMPDIFF(SECOND, s.last_seen, NOW()) AS secondes_depuis_vu
     FROM sabres s
     LEFT JOIN joueurs j ON j.id = s.joueur_id
     ORDER BY s.nom, s.mac_address"
)->fetchAll();

// Cache de scan : appareils découverts mais non encore enregistrés
$macEnregistrees = array_column($sabres, 'mac_address');
$phNot = count($macEnregistrees)
    ? 'AND mac_address NOT IN (' . implode(',', array_fill(0, count($macEnregistrees), '?')) . ')'
    : '';
$stmtCache = $pdo->prepare(
    "SELECT * FROM ble_scan_cache WHERE last_scanned > DATE_SUB(NOW(), INTERVAL 5 MINUTE) $phNot
     ORDER BY last_scanned DESC LIMIT 30"
);
$stmtCache->execute($macEnregistrees);
$scanCache = $stmtCache->fetchAll();

// Liste des joueurs pour le select
$joueurs = $pdo->query("SELECT id, pseudo FROM joueurs ORDER BY pseudo")->fetchAll();

$pageTitle = 'Sabres BLE';
require __DIR__ . '/../includes/header.php';
?>

<?php require __DIR__ . '/_tabs.php'; ?>

<h2 style="margin-bottom:var(--space-4);"><?= icon('bluetooth') ?> Sabres BLE</h2>

<?php /* ── Sabres enregistrés ── */ ?>
<div class="card" style="margin-bottom:var(--space-5);">
    <h3 style="margin-bottom:var(--space-4);">Sabres enregistrés</h3>

    <?php if (!$sabres): ?>
        <p class="hint">Aucun sabre enregistré. Ajoutez-en un ci-dessous.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table" style="width:100%;">
        <thead>
            <tr>
                <th>Statut</th>
                <th>Nom</th>
                <th>Adresse MAC</th>
                <th>Joueur associé</th>
                <th>Actif</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sabres as $s):
            $secsVu = $s['secondes_depuis_vu'];
            $connecte = $s['last_seen'] !== null && $secsVu !== null && $secsVu < 60;
            $recemment = $s['last_seen'] !== null && $secsVu !== null && $secsVu < 300;
        ?>
            <tr>
                <td>
                    <?php if ($connecte): ?>
                        <span class="badge badge-en_cours" title="Connecté (vu il y a <?= $secsVu ?>s)">● EN LIGNE</span>
                    <?php elseif ($recemment): ?>
                        <span class="badge" style="background:var(--warning);color:#000;" title="Vu il y a <?= round($secsVu/60) ?>min">◐ RÉCENT</span>
                    <?php else: ?>
                        <span class="badge badge-annule" title="<?= $s['last_seen'] ? 'Vu le '.$s['last_seen'] : 'Jamais vu' ?>">○ HORS LIGNE</span>
                    <?php endif; ?>
                </td>
                <td><strong><?= e($s['nom'] ?: '—') ?></strong></td>
                <td><code><?= e($s['mac_address']) ?></code></td>
                <td><?= $s['joueur_pseudo'] ? e($s['joueur_pseudo']) : '<span class="hint">Non assigné</span>' ?></td>
                <td><?= $s['actif'] ? '✓' : '<span class="hint">Non</span>' ?></td>
                <td>
                    <button class="btn-small btn-secondary"
                        onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                        <?= icon('pencil') ?> Modifier
                    </button>
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Supprimer ce sabre ?')">
                        <input type="hidden" name="csrf"   value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"     value="<?= $s['id'] ?>">
                        <button type="submit" class="btn-small btn-danger"><?= icon('trash') ?> Suppr.</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php /* ── Scan BLE — appareils découverts ── */ ?>
<?php if ($scanCache): ?>
<div class="card" style="margin-bottom:var(--space-5);">
    <h3 style="margin-bottom:var(--space-2);"><?= icon('search') ?> Appareils découverts (scan récent)</h3>
    <p class="hint" style="margin-bottom:var(--space-4);">Ces appareils ont été vus lors du dernier scan BLE du daemon. Cliquez sur <em>Ajouter</em> pour les enregistrer comme sabres.</p>
    <table class="table">
        <thead>
            <tr><th>Adresse MAC</th><th>Nom BLE</th><th>Vu à</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($scanCache as $d): ?>
            <tr>
                <td><code><?= e($d['mac_address']) ?></code></td>
                <td><?= e($d['nom_ble'] ?: '—') ?></td>
                <td><?= e($d['last_scanned']) ?></td>
                <td>
                    <button class="btn-small btn-success"
                        onclick="prefillAdd('<?= e($d['mac_address']) ?>','<?= e(addslashes($d['nom_ble'] ?? '')) ?>')">
                        <?= icon('plus') ?> Ajouter
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:var(--space-5);">
    <p class="hint"><?= icon('bluetooth') ?> Le daemon BLE n'a pas encore effectué de scan récent, ou aucun appareil BLE n'est à portée. Le scan automatique s'effectue toutes les 30 secondes.</p>
</div>
<?php endif; ?>

<?php /* ── Formulaire d'ajout manuel ── */ ?>
<div class="card" id="add-card">
    <h3 style="margin-bottom:var(--space-4);"><?= icon('plus') ?> Ajouter un sabre manuellement</h3>
    <form method="post" action="sabres.php" novalidate>
        <input type="hidden" name="csrf"   value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="field">
            <label for="mac_address">Adresse MAC BLE <span style="color:var(--danger)">*</span></label>
            <input type="text" id="mac_address" name="mac_address"
                   placeholder="C4:86:96:A2:90:92" required
                   pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}"
                   style="font-family:monospace;text-transform:uppercase;">
            <small class="hint">Récupérable via le moniteur série du sabre (code.py affiche l'adresse MAC au démarrage).</small>
        </div>
        <div class="field">
            <label for="nom">Nom du sabre</label>
            <input type="text" id="nom" name="nom" placeholder="ex. Sabre-01">
        </div>
        <div class="field">
            <label for="joueur_id">Joueur associé</label>
            <select id="joueur_id" name="joueur_id">
                <option value="">— Aucun —</option>
                <?php foreach ($joueurs as $j): ?>
                    <option value="<?= $j['id'] ?>"><?= e($j['pseudo']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="hint">Le système utilisera ce mapping pour attribuer automatiquement les touches au bon joueur lors des combats.</small>
        </div>
        <button type="submit" class="btn-success"><?= icon('plus') ?> Enregistrer le sabre</button>
    </form>
</div>

<?php /* ── Modal d'édition ── */ ?>
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="width:min(480px,95vw);max-height:90vh;overflow-y:auto;margin:0;">
        <h3 style="margin-bottom:var(--space-4);"><?= icon('pencil') ?> Modifier le sabre</h3>
        <form method="post" action="sabres.php" novalidate id="edit-form">
            <input type="hidden" name="csrf"   value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id"     id="edit-id">
            <div class="field">
                <label>Adresse MAC</label>
                <code id="edit-mac-display" style="display:block;padding:var(--space-2);background:var(--bg-alt);border-radius:var(--radius-sm);"></code>
            </div>
            <div class="field">
                <label for="edit-nom">Nom</label>
                <input type="text" id="edit-nom" name="nom" placeholder="ex. Sabre-01">
            </div>
            <div class="field">
                <label for="edit-joueur">Joueur associé</label>
                <select id="edit-joueur" name="joueur_id">
                    <option value="">— Aucun —</option>
                    <?php foreach ($joueurs as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= e($j['pseudo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="display:flex;align-items:center;gap:var(--space-2);">
                <input type="checkbox" id="edit-actif" name="actif" value="1">
                <label for="edit-actif" style="margin:0;">Sabre actif (le daemon BLE tentera de s'y connecter)</label>
            </div>
            <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                <button type="submit" class="btn-success"><?= icon('check-circle') ?> Enregistrer</button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(s) {
    document.getElementById('edit-id').value = s.id;
    document.getElementById('edit-mac-display').textContent = s.mac_address;
    document.getElementById('edit-nom').value = s.nom || '';
    document.getElementById('edit-joueur').value = s.joueur_id || '';
    document.getElementById('edit-actif').checked = s.actif == 1;
    const m = document.getElementById('edit-modal');
    m.style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function prefillAdd(mac, nom) {
    document.getElementById('mac_address').value = mac;
    document.getElementById('nom').value = nom;
    document.getElementById('add-card').scrollIntoView({ behavior: 'smooth' });
    document.getElementById('mac_address').focus();
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
