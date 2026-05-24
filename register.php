<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\UserStore;
use App\Support\Flash;

if (AuthManager::check()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
        Flash::add('error', 'Token keamanan tidak valid', 'Silakan refresh halaman registrasi lalu coba kembali.');
        header('Location: register.php');
        exit;
    }

    $username = (string) ($_POST['username'] ?? '');
    $name = (string) ($_POST['name'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($password !== $passwordConfirmation) {
        Flash::add('error', 'Registrasi gagal', 'Konfirmasi password tidak sama.');
        header('Location: register.php');
        exit;
    }

    $userStore = new UserStore((string) app_config('auth.users_path'));
    $result = $userStore->registerUser($username, $name, $password);

    if ($result['success']) {
        AuthManager::attempt($username, $password);
        Flash::add('success', 'Registrasi berhasil', 'Akun user Anda sudah aktif dan siap digunakan.');
        header('Location: dashboard.php');
        exit;
    }

    Flash::add('error', 'Registrasi gagal', $result['message']);
    header('Location: register.php');
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
    <title>Registrasi - <?= htmlspecialchars($appName); ?></title>
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

        .register-page {
            width: min(100%, 1120px);
            min-height: 100svh;
            display: grid;
            align-content: center;
            gap: 28px;
            margin: 0 auto;
            padding: 16px clamp(20px, 4vw, 48px);
        }

        .back-link {
            width: fit-content;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: 0 15px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.92rem;
            font-weight: 850;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.12);
            backdrop-filter: blur(14px);
            transition: background 160ms ease, transform 160ms ease, border-color 160ms ease;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.34);
            transform: translateY(-1px);
        }

        .register-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 440px);
            gap: clamp(28px, 5vw, 72px);
            align-items: end;
        }

        .brand-story {
            color: #ffffff;
            padding-bottom: clamp(10px, 4vw, 34px);
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #cffff8;
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .brand-story h1 {
            margin: 0;
            font-size: clamp(2.7rem, 5.7vw, 5.4rem);
            font-weight: 900;
            line-height: 0.96;
            letter-spacing: 0;
        }

        .brand-story p {
            max-width: 640px;
            margin: 16px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: clamp(1rem, 1.45vw, 1.12rem);
            line-height: 1.72;
        }

        .register-panel {
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

        .register-panel h2 {
            margin: 7px 0 0;
            font-size: clamp(1.55rem, 2.5vw, 1.95rem);
            font-weight: 900;
            line-height: 1.08;
        }

        .register-panel p {
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

        .register-form {
            display: grid;
            gap: 10px;
            margin-top: 2px;
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
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            padding: 0 16px;
            color: #ffffff;
            background: var(--brand);
            font: inherit;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            transition: background 160ms ease, transform 160ms ease;
        }

        .btn:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            color: var(--ink);
            background: #ffffff;
            border: 1px solid var(--line);
        }

        .btn-secondary:hover {
            color: var(--brand-dark);
            background: var(--wash);
            border-color: #cce8e4;
        }

        .login-footnote {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        @media (max-width: 900px) {
            .register-shell {
                grid-template-columns: 1fr;
                gap: 26px;
            }
        }

        @media (max-width: 560px) {
            .register-page {
                align-content: start;
                padding: 16px 14px 28px;
            }
        }
    </style>
</head>
<body>
    <main class="register-page">
        <a class="back-link" href="login.php">Kembali ke login</a>

        <div class="register-shell">
            <section class="brand-story" aria-label="Registrasi <?= htmlspecialchars($appName); ?>">
                <p class="eyebrow">Akun user <?= htmlspecialchars($appName); ?></p>
                <h1>Daftar dan mulai upload.</h1>
                <p>Akun baru otomatis mendapat role user. Anda bisa mengunggah file dan mengelola aset milik akun sendiri.</p>
            </section>

            <section class="register-panel">
                <div>
                    <span class="panel-kicker">Registrasi user</span>
                    <h2>Buat akun</h2>
                    <p>Isi data akun untuk masuk ke workspace.</p>
                </div>

                <?php foreach ($messages as $message): ?>
                    <section class="flash <?= htmlspecialchars((string) ($message['type'] ?? '')); ?>">
                        <p><?= htmlspecialchars((string) ($message['title'] ?? 'Informasi')); ?></p>
                        <?php if (($message['description'] ?? '') !== ''): ?>
                            <small><?= htmlspecialchars((string) $message['description']); ?></small>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <form class="register-form" action="register.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <label>
                        Nama
                        <input type="text" name="name" autocomplete="name" placeholder="Nama lengkap" required>
                    </label>
                    <label>
                        Username
                        <input type="text" name="username" autocomplete="username" minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]{3,40}" placeholder="contoh: anwar.user" required>
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" autocomplete="new-password" minlength="6" placeholder="Minimal 6 karakter" required>
                    </label>
                    <label>
                        Konfirmasi Password
                        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="6" placeholder="Ulangi password" required>
                    </label>
                    <button class="btn" type="submit">Daftar Akun User</button>
                    <a class="btn btn-secondary" href="login.php">Sudah Punya Akun</a>
                </form>

                <p class="login-footnote">Superadmin dan admin tetap dibuat dari database awal atau dashboard admin.</p>
            </section>
        </div>
    </main>
</body>
</html>
