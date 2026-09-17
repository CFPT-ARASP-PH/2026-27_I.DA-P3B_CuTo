<?php
/**
 * arbre.php — Arbre de tournoi (bracket à élimination directe)
 * Bootstrap supprimé — design system v3 natif (@layer)
 */
require_once __DIR__ . '/config/config.php';

purgerCombatsExpires($pdo);

$rondes      = getArbreCombats($pdo);
$roundsList  = array_values($rondes);
$tourNumbers = array_keys($rondes);
$roundCount  = count($roundsList);

foreach ($roundsList as $t => $matches) {
    usort($matches, fn($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));
    $roundsList[$t] = array_values($matches);
}

$matchH     = 100;
$gap0       = 32;
$roundWidth = 240;
$roundGap   = 64;
$unit       = $matchH + $gap0;

$n0           = $roundCount > 0 ? max(1, count($roundsList[0])) : 0;
$masterHeight = $unit * $n0;
$columnHeight = $masterHeight;
for ($t = 0; $t < $roundCount; $t++) {
    $slot   = $unit * (2 ** $t);
    $needed = $slot * max(1, count($roundsList[$t]));
    $columnHeight = max($columnHeight, $needed);
}

function slotH(int $t, int $unit): float { return $unit * (2 ** $t); }
function centerY(int $t, int $p, int $unit): float { return $p * slotH($t, $unit) + slotH($t, $unit) / 2; }

$pageTitle = 'Arbre du tournoi';
/* $extraHead retiré — Bootstrap supprimé totalement */
require __DIR__ . '/includes/header.php';
?>

<style>
/* ── arbre.php — styles locaux, zéro Bootstrap ── */

.bk-page-title {
    font-family: var(--font-display);
    font-size: clamp(1.5rem, 1.2rem + 1.2vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text);
    margin: var(--space-2) 0 var(--space-5);
    padding-bottom: var(--space-3);
    border-bottom: 1px solid var(--border);
    line-height: 1.2;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}
.bk-page-title .icon { color: var(--accent); }

.bk-alert {
    display: flex;
    gap: var(--space-3);
    align-items: flex-start;
    background: rgba(255,193,7,.08);
    border: 1px solid rgba(255,193,7,.3);
    color: var(--text-muted);
    border-radius: var(--radius-md);
    padding: var(--space-3) var(--space-4);
    margin-bottom: var(--space-4);
    font-size: var(--fs-sm);
}
.bk-alert-icon { font-size: 1.1rem; flex: none; }
.bk-alert strong { color: #ffc107; }
.bk-alert ul {
    margin: var(--space-1) 0 0;
    padding-left: var(--space-4);
    color: var(--text-faint);
    list-style: disc;
}
.bk-alert small { color: var(--text-faint); }

.bk-scroll-hint {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--fs-sm);
    color: var(--text-faint);
    margin-bottom: var(--space-3);
}

.bk-empty {
    text-align: center;
    padding: var(--space-10, 4rem) var(--space-4);
}
.bk-empty-icon { font-size: 3rem; opacity: .3; margin-bottom: var(--space-3); }
.bk-empty p   { color: var(--text-muted); margin: 0 0 var(--space-1); }
.bk-empty small { color: var(--text-faint); }
.bk-empty strong { color: var(--text-muted); }

.bk-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    padding-block: var(--space-3) var(--space-8, 3rem);
    margin-inline: calc(-1 * var(--content-pad-inline, 1.25rem));
    padding-inline: var(--content-pad-inline, 1.25rem);
}
.bk-wrap:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: -2px;
    border-radius: var(--radius-sm);
}

.bk-inner { display: flex; align-items: flex-start; gap: <?= $roundGap ?>px; position: relative; }
.bk-col   { display: flex; flex-direction: column; flex: none; width: <?= $roundWidth ?>px; }
.bk-col-head { text-align: center; margin-bottom: var(--space-3); }
.bk-body  { position: relative; }

.bk-card {
    position: absolute; left: 0; right: 0;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: rgba(255,255,255,.04);
    border: 1.5px solid rgba(255,255,255,.12);
    transition: border-color var(--dur-fast, .15s) var(--ease-out, ease),
                box-shadow  var(--dur-fast, .15s) var(--ease-out, ease),
                transform   var(--dur-fast, .15s) var(--ease-out, ease);
    text-decoration: none;
    display: flex;
    flex-direction: column;
}
.bk-card:hover {
    border-color: rgba(255,255,255,.32);
    box-shadow: 0 6px 24px rgba(0,0,0,.45);
    transform: translateY(-1px);
}
.bk-card.s-live    { border-left: 3px solid var(--accent); }
.bk-card.s-done    { border-left: 3px solid rgba(108,117,125,.7); }
.bk-card.s-waiting { border-left: 3px solid rgba(255,255,255,.18); }

.bk-status {
    padding: 3px 10px;
    font-size: .6rem; font-weight: 700;
    letter-spacing: .07em; text-transform: uppercase;
    background: rgba(0,0,0,.25);
    border-bottom: 1px solid rgba(255,255,255,.07);
    display: flex; align-items: center; gap: 5px;
}
.bk-status.live { color: var(--accent); }
.bk-status.done { color: var(--text-faint); }
.bk-status.wait { color: rgba(255,255,255,.35); }

.bk-row {
    flex: 1;
    display: flex; align-items: center; justify-content: space-between;
    gap: 6px; padding: 0 10px;
    color: var(--text-faint); font-size: .82rem; font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,.05);
    font-family: var(--font-ui);
    min-height: 0;
}
.bk-row:last-child { border-bottom: none; }
.bk-row.winner { color: var(--text); background: rgba(74,240,200,.08); }
.bk-row.winner .bk-name::before { content: "▶ "; font-size: .55rem; opacity: .7; vertical-align: middle; }
.bk-name  { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
.bk-score {
    font-family: var(--font-display); font-size: .75rem; font-weight: 700; flex: none;
    letter-spacing: .02em; color: rgba(255,255,255,.6);
}
.winner .bk-score { color: var(--accent); }

.bk-conn {
    position: absolute; left: 100%;
    border-right: 2px solid rgba(255,255,255,.15);
    border-top:   2px solid rgba(255,255,255,.15);
    border-bottom:2px solid rgba(255,255,255,.15);
    border-top-right-radius: 5px;
    border-bottom-right-radius: 5px;
    width: <?= $roundGap / 2 ?>px;
    pointer-events: none;
}
.bk-stub {
    position: absolute; height: 2px;
    background: rgba(255,255,255,.15);
    width: <?= $roundGap / 2 ?>px;
    transform: translateY(-1px);
    pointer-events: none;
}

.bk-champion {
    position: absolute; left: 0; right: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    background: linear-gradient(135deg, rgba(255,215,0,.15), rgba(255,165,0,.08));
    border: 2px solid rgba(255,215,0,.5);
    border-radius: var(--radius-md);
    box-shadow: 0 0 24px rgba(255,215,0,.2);
    text-align: center; padding: 0 12px;
}
.champ-icon { font-size: 1.6rem; line-height: 1; }
.champ-name {
    font-family: var(--font-display); font-size: .78rem; font-weight: 800;
    color: gold; letter-spacing: .04em; text-transform: uppercase;
}
.champ-tbd { font-size: .75rem; color: var(--text-faint); }

.round-badge {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: .62rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    background: rgba(255,255,255,.07); color: var(--text-faint);
    border: 1px solid rgba(255,255,255,.1); white-space: nowrap;
}
.round-badge.final {
    background: rgba(255,215,0,.12); color: gold;
    border-color: rgba(255,215,0,.3);
}

@keyframes livePulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: .4; }
}
.text-live { color: var(--accent); animation: livePulse 1.8s ease-in-out infinite; }
</style>

<h1 class="bk-page-title"><?= icon('bracket') ?> Arbre du tournoi</h1>

<?php if ($roundCount === 0): ?>

    <div class="bk-empty">
        <div class="bk-empty-icon">🎯</div>
        <p>Aucun combat n'est encore placé dans l'arbre.</p>
        <small>
            Un administrateur peut renseigner un « tour » et une « position » dans
            <strong>Admin → Combats</strong>.
        </small>
    </div>

<?php else:
    $finalOk    = count($roundsList[$roundCount - 1]) === 1;
    $totalWidth = $roundCount * $roundWidth + max(0, $roundCount - 1) * $roundGap + ($finalOk ? $roundWidth + $roundGap : 0);

    $incoherences = [];
    for ($t = 1; $t < $roundCount; $t++) {
        $attendu = (int)ceil(count($roundsList[$t - 1]) / 2);
        $reel    = count($roundsList[$t]);
        if ($reel !== $attendu) {
            $incoherences[] = "Tour {$tourNumbers[$t]} : {$reel} combat(s) au lieu de {$attendu} attendu(s).";
        }
    }
?>

<?php if ($incoherences): ?>
    <div class="bk-alert" role="alert">
        <span class="bk-alert-icon" aria-hidden="true">⚠️</span>
        <div>
            <strong>Arbre incohérent</strong> — les connecteurs peuvent être mal alignés :
            <ul>
                <?php foreach ($incoherences as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
            </ul>
            <small>Vérifie les champs « Tour » et « Position » dans Admin → Combats.</small>
        </div>
    </div>
<?php endif; ?>

<?php if ($roundCount > 1): ?>
    <p class="bk-scroll-hint">
        <?= icon('swap') ?> Fais défiler horizontalement pour voir tous les tours.
    </p>
<?php endif; ?>

<div class="bk-wrap" role="region" aria-label="Arbre du tournoi (défilement horizontal)" tabindex="0">
<div class="bk-inner" style="width:<?= $totalWidth ?>px; min-height:<?= $columnHeight + 48 ?>px;">

<?php for ($t = 0; $t < $roundCount; $t++): ?>
    <div class="bk-col">

        <div class="bk-col-head">
            <span class="round-badge">Tour <?= (int)$tourNumbers[$t] ?></span>
        </div>

        <div class="bk-body" style="height:<?= $columnHeight ?>px;">

            <?php foreach ($roundsList[$t] as $p => $combat):
                $y    = centerY($t, $p, $unit) - $matchH / 2;
                $j1W  = $combat['vainqueur_id'] && $combat['vainqueur_id'] == $combat['joueur1_id'];
                $j2W  = $combat['vainqueur_id'] && $combat['vainqueur_id'] == $combat['joueur2_id'];
                $st   = $combat['statut'];
                $cls  = $st === 'en_cours' ? 's-live' : ($st === 'termine' ? 's-done' : 's-waiting');
                $statusCls = $st === 'en_cours' ? 'live' : ($st === 'termine' ? 'done' : 'wait');
                $statusLabel = match($st) {
                    'en_cours' => '<span class="text-live">' . icon('broadcast') . '</span> En cours',
                    'termine'  => icon('check-circle') . ' Terminé',
                    default    => icon('history') . ' À venir',
                };
            ?>
                <a href="combat.php?id=<?= (int)$combat['id'] ?>"
                   class="bk-card <?= $cls ?>"
                   style="top:<?= round($y) ?>px; height:<?= $matchH ?>px;"
                   <?= $st === 'en_cours' ? 'data-combat-id="' . (int)$combat['id'] . '"' : '' ?>>

                    <div class="bk-status <?= $statusCls ?>"><?= $statusLabel ?></div>

                    <div class="bk-row <?= $j1W ? 'winner' : '' ?>">
                        <span class="bk-name"><?= e($combat['joueur1_pseudo']) ?></span>
                        <span class="bk-score js-score1">
                            <?= $st === 'a_venir' ? '–' : number_format((float)$combat['score_joueur1'], 1) ?>
                        </span>
                    </div>

                    <div class="bk-row <?= $j2W ? 'winner' : '' ?>">
                        <span class="bk-name"><?= e($combat['joueur2_pseudo']) ?></span>
                        <span class="bk-score js-score2">
                            <?= $st === 'a_venir' ? '–' : number_format((float)$combat['score_joueur2'], 1) ?>
                        </span>
                    </div>

                    <?php if ($st === 'en_cours'): ?>
                        <span class="visually-hidden js-live-status" aria-live="polite"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <?php if ($t + 1 < $roundCount): ?>
                <?php foreach ($roundsList[$t + 1] as $q => $nc):
                    $topY = centerY($t, 2 * $q,     $unit);
                    $botY = centerY($t, 2 * $q + 1, $unit);
                    if (!isset($roundsList[$t][2 * $q + 1])) $botY = $topY;
                    $connH = max(2, $botY - $topY);
                ?>
                    <span class="bk-conn"
                          style="top:<?= round($topY) ?>px; height:<?= round($connH) ?>px;"
                          aria-hidden="true"></span>
                    <span class="bk-stub"
                          style="top:<?= round(centerY($t + 1, $q, $unit)) ?>px; left:<?= $roundWidth ?>px;"
                          aria-hidden="true"></span>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
<?php endfor; ?>

<?php if ($finalOk):
    $final    = $roundsList[$roundCount - 1][0];
    $champY   = centerY($roundCount - 1, 0, $unit);
    $champName = null;
    if ($final['statut'] === 'termine' && $final['vainqueur_id']) {
        $champName = $final['vainqueur_id'] == $final['joueur1_id']
            ? $final['joueur1_pseudo'] : $final['joueur2_pseudo'];
    }
?>
    <div class="bk-col">
        <div class="bk-col-head">
            <span class="round-badge final">🏆 Champion</span>
        </div>
        <div class="bk-body" style="height:<?= $columnHeight ?>px;">
            <span class="bk-stub"
                  style="top:<?= round($champY) ?>px; left:-<?= $roundGap / 2 ?>px;"
                  aria-hidden="true"></span>
            <div class="bk-champion" style="top:<?= round($champY - $matchH / 2) ?>px; height:<?= $matchH ?>px;">
                <?php if ($champName): ?>
                    <span class="champ-icon">🏆</span>
                    <span class="champ-name"><?= e($champName) ?></span>
                <?php else: ?>
                    <span class="champ-icon" style="opacity:.35;">❓</span>
                    <span class="champ-tbd">À déterminer</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
</div>

<?php endif; ?>

<script src="<?= BASE_URL ?>assets/js/live.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
