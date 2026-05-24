<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Support\Flash;

if (AuthManager::check()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
        Flash::add('error', 'Token keamanan tidak valid', 'Silakan refresh halaman login lalu coba kembali.');
        header('Location: login.php');
        exit;
    }

    $loginMode = (string) ($_POST['login_mode'] ?? 'password');

    if ($loginMode === 'guest') {
        if (AuthManager::loginConfiguredUser('guest')) {
            Flash::add('success', 'Masuk sebagai guest', 'Dashboard dibuka dalam mode lihat saja.');
            header('Location: dashboard.php');
            exit;
        }

        Flash::add('error', 'Guest tidak tersedia', 'Akun guest belum aktif di konfigurasi user.');
        header('Location: login.php');
        exit;
    }

    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (AuthManager::attempt($username, $password)) {
        Flash::add('success', 'Login berhasil', 'Welcome back to Cloudify.');
        header('Location: dashboard.php');
        exit;
    }

    Flash::add('error', 'Login gagal', 'Username atau password tidak sesuai.');
    header('Location: login.php');
    exit;
}

$messages = Flash::pull();
$csrfToken = CsrfManager::token();
$appName = (string) app_config('app.name', 'Cloudify');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($appName); ?></title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <style>
        :root {
            --ink: #14171f;
            --muted: #68717f;
            --subtle: #8b94a3;
            --line: #e6eaf0;
            --brand: #0f9f8f;
            --brand-dark: #0b6f68;
            --wash: #eef6f5;
            --soft: #f6f8fb;
            --surface: #ffffff;
            --shadow: 0 20px 45px rgba(20, 23, 31, 0.08);
            --ok-100: #edf7f1;
            --ok-700: #24633f;
            --error-100: #fbeeee;
            --error-700: #8d3232;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background:
                linear-gradient(90deg, rgba(20, 23, 31, 0.76) 0%, rgba(20, 23, 31, 0.58) 45%, rgba(20, 23, 31, 0.18) 100%),
                linear-gradient(180deg, rgba(20, 23, 31, 0.18), rgba(20, 23, 31, 0.48)),
                url("assets/images/hero-background.jpg") center / cover fixed no-repeat;
        }

        a {
            color: inherit;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .login-page {
            width: min(100%, 1216px);
            min-height: 100svh;
            display: grid;
            align-content: center;
            gap: clamp(22px, 4vw, 42px);
            margin: 0 auto;
            padding: 16px clamp(20px, 4vw, 48px);
        }

        .back-link {
            width: fit-content;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0 15px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.92rem;
            font-weight: 850;
            text-decoration: none;
            border-color: rgba(255, 255, 255, 0.22);
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.12);
            backdrop-filter: blur(14px);
            transition: background 160ms ease, color 160ms ease, transform 160ms ease, border-color 160ms ease;
        }

        .back-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.34);
            transform: translateY(-1px);
        }

        .back-link svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2.2;
        }

        .login-shell {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 440px);
            gap: clamp(28px, 5vw, 72px);
            align-items: end;
        }

        .brand-story {
            position: relative;
            min-height: auto;
            display: grid;
            align-content: end;
            padding: 0 0 clamp(10px, 4vw, 34px);
            color: #ffffff;
        }

        .brand-story::before {
            display: none;
        }

        .story-content {
            position: relative;
            z-index: 1;
            max-width: 690px;
        }

        .brand-story h1 {
            margin: 0;
            font-size: clamp(2.8rem, 6vw, 5.85rem);
            font-weight: 900;
            line-height: 0.94;
            letter-spacing: 0;
        }

        .brand-story p {
            margin: 16px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: clamp(1rem, 1.45vw, 1.12rem);
            line-height: 1.72;
            text-shadow: 0 1px 18px rgba(0, 0, 0, 0.32);
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #cffff8;
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
            text-shadow: 0 1px 18px rgba(0, 0, 0, 0.32);
        }

        .story-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 24px;
        }

        .story-pill {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 800;
            backdrop-filter: blur(14px);
        }

        .login-panel {
            padding: clamp(20px, 2.5vw, 26px);
            display: grid;
            gap: 11px;
            border: 1px solid rgba(255, 255, 255, 0.76);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 24px 60px rgba(20, 23, 31, 0.22);
            backdrop-filter: blur(18px);
        }

        .panel-kicker {
            width: fit-content;
            min-height: 28px;
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0 10px;
            color: var(--brand-dark);
            background: var(--wash);
            font-size: 0.78rem;
            font-weight: 900;
        }

        .login-panel h2 {
            margin: 7px 0 0;
            font-size: clamp(1.55rem, 2.5vw, 1.95rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0;
        }

        .login-panel > p {
            margin: 0;
            color: var(--muted);
        }

        .flash {
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 13px 15px;
        }

        .flash p {
            margin: 0;
            font-weight: 800;
        }

        .flash small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
        }

        .flash.success {
            border-color: #c7e4d1;
            background: var(--ok-100);
            color: var(--ok-700);
        }

        .flash.error {
            border-color: #e9c7c7;
            background: var(--error-100);
            color: var(--error-700);
        }

        .login-form {
            display: grid;
            gap: 10px;
            margin-top: 2px;
        }

        .guest-form {
            margin-top: -2px;
        }

        .form-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 4px 0 0;
            color: var(--subtle);
            font-size: 0.8rem;
            font-weight: 850;
        }

        .form-divider::before,
        .form-divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: var(--line);
        }

        .register-copy {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.5;
        }

        label {
            display: grid;
            gap: 7px;
            color: #3f4855;
            font-size: 0.9rem;
            font-weight: 800;
        }

        input {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 14px;
            color: var(--ink);
            font: inherit;
            outline: none;
            background: var(--soft);
            transition: background 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        input::placeholder {
            color: var(--subtle);
        }

        input:focus {
            background: #ffffff;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(15, 159, 143, 0.14);
        }

        .btn {
            border: 0;
            border-radius: 8px;
            min-height: 46px;
            padding: 0 16px;
            color: #fff;
            background: var(--brand);
            font: inherit;
            font-weight: 900;
            cursor: pointer;
            transition: background 160ms ease, transform 160ms ease;
        }

        .btn:hover {
            background: var(--brand-dark);
        }

        .btn-guest {
            width: 100%;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid var(--line);
        }

        .btn-guest:hover {
            color: var(--brand-dark);
            background: var(--wash);
            border-color: #cce8e4;
        }

        .btn-register {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
        }

        .login-footnote {
            margin: -2px 0 0;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .credential-box {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            overflow: hidden;
        }

        .credential-box summary {
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 14px;
            color: var(--muted);
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 850;
        }

        .credential-box summary::-webkit-details-marker {
            display: none;
        }

        .credential-box summary::after {
            content: "+";
            width: 24px;
            height: 24px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--wash);
            font-weight: 900;
        }

        .credential-box[open] summary::after {
            content: "-";
        }

        .credential-list {
            display: grid;
            gap: 7px;
            padding: 0 14px 12px;
        }

        .credential-row {
            display: grid;
            grid-template-columns: 98px 1fr;
            gap: 10px;
            border-top: 1px solid var(--line);
            padding-top: 8px;
            line-height: 1.48;
        }

        .credential-row strong {
            color: var(--ink);
        }

        .credential-row span {
            color: var(--muted);
            font-size: 0.88rem;
        }

        @media (max-width: 900px) {
            body {
                background:
                    linear-gradient(180deg, rgba(20, 23, 31, 0.66), rgba(20, 23, 31, 0.36)),
                    url("assets/images/hero-background.jpg") center / cover fixed no-repeat;
            }

            .login-shell {
                grid-template-columns: 1fr;
                gap: 26px;
                align-items: stretch;
            }

            .brand-story {
                padding: 12px 0 0;
            }

            .story-content {
                max-width: 760px;
            }
        }

        @media (max-width: 560px) {
            .login-page {
                align-content: start;
                padding: 16px 14px 28px;
            }

            .brand-story {
                padding: 4px 0 0;
            }

            .story-meta {
                gap: 7px;
            }

            .story-pill {
                min-height: 34px;
                font-size: 0.82rem;
            }

            .credential-row {
                grid-template-columns: 1fr;
                gap: 2px;
            }
        }
    </style>
</head>
<body>
    <main class="login-page">
        <a class="back-link" href="index.php">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Kembali ke halaman utama
        </a>

        <div class="login-shell">
            <section class="brand-story" aria-label="Tentang <?= htmlspecialchars($appName); ?>">
            <div class="story-content">
                <p class="eyebrow">Workspace <?= htmlspecialchars($appName); ?></p>
                <h1>Kelola aset visual.</h1>
                <p>Upload, atur akses, dan rapikan koleksi gambar dalam satu dashboard.</p>
                <div class="story-meta" aria-label="Fitur akses">
                    <span class="story-pill">Role-based access</span>
                    <span class="story-pill">Audit aktivitas</span>
                    <span class="story-pill">Asset pribadi</span>
                </div>
            </div>
            </section>
            <section class="login-panel">
            <div>
                <span class="panel-kicker">Akses aman</span>
                <h2>Login akses</h2>
                <p>Masuk dengan akun workspace <?= htmlspecialchars($appName); ?>.</p>
            </div>

            <?php foreach ($messages as $message): ?>
                <section class="flash <?= htmlspecialchars((string) ($message['type'] ?? '')); ?>">
                    <p><?= htmlspecialchars((string) ($message['title'] ?? 'Informasi')); ?></p>
                    <?php if (($message['description'] ?? '') !== ''): ?>
                        <small><?= htmlspecialchars((string) $message['description']); ?></small>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <form class="login-form" action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="login_mode" value="password">
                <label>
                    Username
                    <input type="text" name="username" autocomplete="username" placeholder="Masukkan username" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                </label>
                <button class="btn" type="submit">Masuk Dashboard</button>
            </form>
            <form class="guest-form" action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="login_mode" value="guest">
                <button class="btn btn-guest" type="submit">Masuk Sebagai Guest</button>
            </form>
            <div class="form-divider">atau</div>
            <a class="btn btn-register" href="register.php">Daftar Akun User</a>
            <p class="login-footnote">Hak akses mengikuti role akun. Guest hanya dapat melihat dashboard.</p>
            <details class="credential-box">
                <summary>Lihat akun demo</summary>
                <div class="credential-list">
                    <div class="credential-row">
                        <strong>Superadmin</strong>
                        <span><strong>superadmin</strong> / <strong>superadmin123</strong></span>
                    </div>
                    <div class="credential-row">
                        <strong>Admin</strong>
                        <span><strong>admin</strong> / <strong>admin123</strong></span>
                    </div>
                </div>
            </details>
            </section>
        </div>
    </main>
</body>
</html>
