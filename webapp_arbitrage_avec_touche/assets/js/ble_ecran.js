/**
 * ble_ecran.js — Overlay TOUCHÉ pour la page écran public (ecran.php).
 * Attend window.BLE_COMBATS_EN_COURS et window.BASE_URL définis avant ce script.
 */
(function () {
    'use strict';

    const pollMs  = (window.SABRE_CONFIG && window.SABRE_CONFIG.pollMs) || 2000;
    const combats = window.BLE_COMBATS_EN_COURS || [];
    const overlay = document.getElementById('ble-overlay');
    const label   = document.getElementById('ble-overlay-label');
    const who     = document.getElementById('ble-overlay-who');
    const base    = window.BASE_URL || '/';

    if (!overlay || !combats.length) return;

    const lastIds = {};
    combats.forEach(c => { lastIds[c.id] = 0; });

    function showOverlay(type, joueurPseudo) {
        overlay.className = '';
        overlay.classList.add('visible', 'type-' + type);
        if (type === 'touche') {
            label.textContent = 'TOUCHÉ !';
            who.textContent   = joueurPseudo || '';
        } else {
            label.textContent = 'DOUBLE TOUCHE';
            who.textContent   = 'Annulé';
        }
        clearTimeout(overlay._timer);
        overlay._timer = setTimeout(() => {
            overlay.classList.remove('visible');
            setTimeout(() => { overlay.className = ''; }, 300);
        }, 2500);
    }

    async function poll() {
        for (const c of combats) {
            try {
                const r = await fetch(base + 'api/ble_touches.php?combat_id=' +
                                      c.id + '&since_id=' + (lastIds[c.id] || 0));
                const data = await r.json();
                (data.touches || []).forEach(t => {
                    lastIds[c.id] = Math.max(lastIds[c.id] || 0, t.id);
                    showOverlay(t.type, t.joueur_pseudo);
                });
            } catch (_) { /* silencieux */ }
        }
    }

    setInterval(poll, pollMs);
    poll();
})();
