<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = attemptLogin($pdo, $username, $password);
        if ($result['success']) {
            flash('success', 'Bienvenue ' . $username . ' !');
            redirect('index.php');
        } else {
            $error = $result['error'];
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — Sabre Laser Arbitrage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;800&family=Rajdhani:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* ── Page de connexion : layout immersif indépendant ── */
        body {
            display: grid;
            place-items: center;
            min-height: 100dvh;
            padding: var(--space-5) var(--content-pad-inline);
            padding-bottom: max(var(--space-5), env(safe-area-inset-bottom, 0px));
        }

        /* Fond avec grille et glow cyan */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(ellipse 900px 600px at 50% -10%, rgba(74,240,200,0.055), transparent 55%),
                radial-gradient(ellipse 600px 400px at 85% 110%, rgba(74,240,200,0.025), transparent 50%),
                linear-gradient(180deg, #000 0%, var(--bg) 30%, var(--bg-deep) 100%);
        }
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image:
                linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.022) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 70% at 50% 0%, black 40%, transparent 80%);
        }

        /* Wrapper central */
        .login-wrap {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
            animation: pageFadeUp 0.4s var(--ease-out) both;
        }

        /* Header branding */
        .login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-3);
            text-align: center;
        }

        .login-blade-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: var(--space-1);
        }
        .login-blade {
            width: 52px;
            height: 3px;
            border-radius: 3px;
            background: linear-gradient(90deg, rgba(74,240,200,0.1), var(--accent), var(--accent), rgba(74,240,200,0.1));
            box-shadow:
                0 0 12px rgba(74,240,200,0.7),
                0 0 36px rgba(74,240,200,0.3),
                0 0 80px rgba(74,240,200,0.1);
            position: relative;
        }
        .login-blade::after {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.6);
            border-radius: 3px;
            filter: blur(1px);
        }

        .login-title {
            font-family: var(--font-display);
            font-size: clamp(1rem, 0.85rem + 0.8vw, 1.25rem);
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1;
            margin: 0;
        }

        .login-subtitle {
            font-family: var(--font-ui);
            font-size: var(--fs-sm);
            font-weight: 600;
            color: var(--text-faint);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0;
        }

        /* Carte formulaire */
        .login-card {
            background: rgba(255,255,255,0.028);
            backdrop-filter: blur(20px) saturate(130%);
            -webkit-backdrop-filter: blur(20px) saturate(130%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow:
                0 1px 0 rgba(255,255,255,0.05) inset,
                0 24px 64px -16px rgba(0,0,0,0.9),
                0 0 0 1px rgba(74,240,200,0.04);
        }

        .login-card form {
            max-width: none;
        }

        .login-submit {
            width: 100%;
            min-height: 3rem;
            margin-top: var(--space-2);
            background: var(--accent);
            color: var(--text-on-accent);
            font-size: var(--fs-md);
            box-shadow: 0 0 20px rgba(74,240,200,0.25);
        }
        .login-submit:hover {
            background: #6ef7d6;
            box-shadow: 0 0 30px rgba(74,240,200,0.42), 0 4px 18px rgba(0,0,0,0.3);
            transform: translateY(-2px);
        }

        .login-register {
            text-align: center;
            font-family: var(--font-ui);
            font-size: var(--fs-sm);
            color: var(--text-faint);
            margin: 0;
        }
        .login-register a {
            color: var(--accent);
            font-weight: 700;
        }
        .login-register a:hover { color: #6ef7d6; }

        /* Footer discret */
        .login-footer {
            text-align: center;
            font-family: var(--font-ui);
            font-size: 0.68rem;
            color: var(--text-faint);
            opacity: 0.4;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body>

<?php /* Sprite SVG — copié depuis header.php pour avoir les icônes sans le header complet */ ?>
<?php if (function_exists('renderSvgSprite')) renderSvgSprite(); ?>

<main class="login-wrap" id="main-content">

    <!-- Branding -->
    <header class="login-brand" role="banner">
        <div class="login-blade-wrap" aria-hidden="true">
            <div class="login-blade"></div>
        </div>
        <h1 class="login-title">Sabre Laser Arbitrage</h1>
        <p class="login-subtitle">Espace de gestion des tournois</p>
    </header>

    <!-- Formulaire -->
    <div class="login-card">
        <?php if ($error): ?>
            <div class="flash flash-error" role="alert">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-alert"></use></svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

            <div class="field">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required autofocus
                       autocomplete="username" inputmode="text"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="votre_pseudo">
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="input-with-btn">
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="password-toggle-btn"
                            data-target="password"
                            aria-label="Afficher le mot de passe">
                        <svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-eye"></use></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-submit">
                <svg class="icon" aria-hidden="true" focusable="false"><use href="#icon-login"></use></svg>
                Se connecter
            </button>
        </form>

        <p class="login-register" style="margin-top:var(--space-4);">
            Pas encore de compte ? <a href="register.php">Créer un compte</a>
        </p>
    </div>

    <p class="login-footer">© <?= date('Y') ?> Sabre Laser Arbitrage</p>

</main>

<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
