<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'dashboard_layout.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\CloudStorageService;
use App\Services\TrashMetadataStore;
use App\Support\Flash;
use App\Support\Formatter;

AuthManager::requirePermission('manage_trash', 'dashboard.php');

$storage = new CloudStorageService(app_config('storage'));
$trashMetadata = new TrashMetadataStore((string) app_config('storage.trash_metadata_path'));
$currentUser = AuthManager::user();

if ($currentUser === null) {
    header('Location: login.php');
    exit;
}

$trashFiles = $trashMetadata->filterFilesForUser($storage->listTrashFiles(), $currentUser);
$messages = Flash::pull();
$csrfToken = CsrfManager::token();
$appName = (string) app_config('app.name', 'Cloudify');
$dashboardLayout = dashboard_layout_context($currentUser, $appName);
$roleLabels = $dashboardLayout['roleLabels'];
$isAdminRole = in_array($currentUser['role'], ['superadmin', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash - <?= htmlspecialchars($appName); ?></title>
    <link rel="icon" href="favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&family=IBM+Plex+Serif:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --dash-bg: #f4f6f8;
            --dash-ink: #171a21;
            --dash-muted: #667085;
            --dash-soft: #f7f9fb;
            --dash-line: #e4e8ee;
            --dash-panel: #ffffff;
            --dash-accent: #0f8f83;
            --dash-accent-dark: #0a645d;
            --dash-sidebar: #111827;
            --dash-sidebar-soft: #1f2937;
            --dash-radius: 8px;
            --dash-shadow: 0 12px 30px rgba(17, 24, 39, 0.07);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--dash-ink);
            background: var(--dash-bg);
            font-family: Inter, "Manrope", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }

        .dashboard-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 272px minmax(0, 1fr);
        }

        .dashboard-sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 20px 16px;
            color: #d6dde8;
            background: var(--dash-sidebar);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        .dashboard-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 4px 16px;
            color: #ffffff;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.09);
        }

        .dashboard-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: var(--dash-radius);
            background: var(--dash-accent);
            color: #ffffff;
            font-weight: 900;
            box-shadow: inset 0 -5px 0 rgba(0, 0, 0, 0.12);
        }

        .dashboard-brand strong,
        .dashboard-brand span {
            display: block;
        }

        .dashboard-brand strong {
            line-height: 1.2;
            font-size: 1rem;
        }

        .dashboard-brand span {
            margin-top: 2px;
            color: #9aa6b6;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .sidebar-block {
            display: grid;
            gap: 8px;
        }

        .sidebar-label {
            padding: 0 10px;
            color: #8b98aa;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .sidebar-link {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 10px;
            border-radius: var(--dash-radius);
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 800;
            transition: background 160ms ease, color 160ms ease;
        }

        .sidebar-link:hover,
        .sidebar-link.is-active {
            color: #ffffff;
            background: var(--dash-sidebar-soft);
        }

        .sidebar-icon {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 7px;
            color: #d9f8f4;
            background: rgba(15, 143, 131, 0.18);
            font-size: 0.76rem;
            font-weight: 900;
        }

        .sidebar-account {
            margin-top: auto;
            display: grid;
            gap: 12px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: var(--dash-radius);
            background: rgba(255, 255, 255, 0.04);
        }

        .sidebar-account strong,
        .sidebar-account span {
            display: block;
        }

        .sidebar-account strong {
            color: #ffffff;
            overflow-wrap: anywhere;
        }

        .sidebar-account span {
            margin-top: 2px;
            color: #9aa6b6;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .sidebar-logout {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--dash-radius);
            color: #ffffff;
            background: rgba(239, 68, 68, 0.16);
            text-decoration: none;
            font-weight: 900;
        }

        .dashboard-main {
            min-width: 0;
            padding: 24px;
        }

        .layout {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            display: grid;
            gap: 18px;
        }

        .dashboard-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
        }

        .page-kicker {
            margin: 0 0 4px;
            color: var(--dash-muted);
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .dashboard-top h1 {
            margin: 0;
            color: var(--dash-ink);
            font-size: clamp(1.55rem, 3vw, 2.35rem);
            line-height: 1.1;
            letter-spacing: 0;
        }

        .dashboard-top p {
            margin: 7px 0 0;
            max-width: 720px;
            color: var(--dash-muted);
            line-height: 1.65;
        }

        .top-card {
            min-width: 260px;
            padding: 14px;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: var(--dash-panel);
            box-shadow: var(--dash-shadow);
        }

        .top-card strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .top-card p {
            margin: 0;
            color: var(--dash-muted);
            font-size: 0.86rem;
            line-height: 1.55;
        }

        .capability-list {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .capability-chip {
            min-height: 30px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 10px;
            border: 1px solid #cde7e3;
            border-radius: 999px;
            color: var(--dash-accent-dark);
            background: #edf9f7;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .capability-chip.is-off {
            color: #7b8492;
            border-color: var(--dash-line);
            background: #f2f4f7;
        }

        .capability-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--dash-accent);
        }

        .capability-chip.is-off .capability-dot {
            background: #aab3c0;
        }

        .panel,
        .flash {
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: var(--dash-panel);
            box-shadow: var(--dash-shadow);
        }

        .panel {
            padding: 18px;
        }

        .flash {
            padding: 12px 14px;
        }

        .flash p {
            margin: 0;
            font-weight: 900;
        }

        .flash small {
            color: var(--dash-muted);
        }

        .flash.success {
            border-color: #bce6d1;
            background: #effaf4;
            color: #17613a;
        }

        .flash.error {
            border-color: #ffd6d2;
            background: #fff4f3;
            color: #b42318;
        }

        .flash.warning {
            border-color: #f5d79e;
            background: #fff8e8;
            color: #8a5a00;
        }

        .panel h2 {
            margin: 0;
            color: var(--dash-ink);
            font-size: 1.05rem;
            letter-spacing: 0;
        }

        .panel p {
            margin: 6px 0 0;
            color: var(--dash-muted);
            line-height: 1.62;
        }

        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .panel-count {
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            padding: 0 10px;
            border-radius: 999px;
            color: var(--dash-accent-dark);
            background: #edf9f7;
            font-size: 0.78rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .table-wrap {
            margin-top: 0;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            overflow: auto;
            box-shadow: none;
        }

        .repo-table {
            width: 100%;
            border-collapse: collapse;
        }

        .repo-table th {
            padding: 12px;
            color: var(--dash-muted);
            background: var(--dash-soft);
            border-bottom: 1px solid var(--dash-line);
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            text-align: left;
        }

        .repo-table td {
            padding: 12px;
            border-bottom: 1px solid var(--dash-line);
            color: #384152;
            vertical-align: middle;
        }

        .repo-table tbody tr:hover {
            background: #fbfcfd;
        }

        .repo-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .file-name {
            color: var(--dash-ink);
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .muted {
            margin-top: 4px;
            color: var(--dash-muted);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn,
        .link-action {
            border-radius: var(--dash-radius);
            font-weight: 900;
            cursor: pointer;
            transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .btn:hover,
        .link-action:hover {
            transform: translateY(-1px);
        }

        .btn-secondary {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--dash-ink);
            background: #ffffff;
            border: 1px solid var(--dash-line);
            padding: 0 13px;
            text-decoration: none;
        }

        .link-action {
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--dash-line);
            padding: 0 10px;
            font-size: 0.82rem;
            background: #ffffff;
        }

        .link-restore {
            color: #ffffff;
            border-color: var(--dash-accent);
            background: var(--dash-accent);
        }

        .link-delete {
            color: #b42318;
            border-color: #ffd6d2;
            background: #fff4f3;
        }

        .empty {
            margin-top: 12px;
            padding: 14px;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            color: var(--dash-muted);
            background: var(--dash-soft);
            line-height: 1.6;
        }

        .name-col {
            display: grid;
            gap: 4px;
            min-width: 220px;
        }

        .type-badge {
            display: inline-flex;
            width: fit-content;
            min-height: 26px;
            align-items: center;
            border: 1px solid var(--dash-line);
            border-radius: 999px;
            padding: 0 9px;
            color: #475467;
            background: #f8fafc;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        @media (max-width: 1120px) {
            .dashboard-shell {
                grid-template-columns: 232px minmax(0, 1fr);
            }
        }

        @media (max-width: 820px) {
            .dashboard-shell {
                display: block;
            }

            .dashboard-sidebar {
                position: static;
                height: auto;
                padding: 14px;
                gap: 10px;
            }

            .sidebar-block {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding-bottom: 2px;
                scrollbar-width: thin;
            }

            .sidebar-label {
                display: none;
            }

            .sidebar-link {
                flex: 0 0 auto;
                min-height: 38px;
                padding-right: 12px;
                white-space: nowrap;
            }

            .sidebar-account {
                margin-top: 0;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                padding: 10px;
            }

            .sidebar-logout {
                min-width: 96px;
            }

            .dashboard-main {
                padding: 16px 14px 28px;
            }

            .dashboard-top {
                grid-template-columns: 1fr;
            }

            .top-card {
                min-width: 0;
            }
        }

        @media (max-width: 620px) {
            .panel-head {
                display: grid;
            }

            .table-wrap {
                border: 0;
                overflow: visible;
            }

            .repo-table,
            .repo-table tbody {
                display: block;
                width: 100%;
            }

            .repo-table thead {
                display: none;
            }

            .repo-table tbody tr {
                display: block;
                margin-bottom: 10px;
                padding: 10px;
                border: 1px solid var(--dash-line);
                border-radius: var(--dash-radius);
                background: #ffffff;
            }

            .repo-table td {
                display: grid;
                grid-template-columns: minmax(86px, 108px) 1fr;
                gap: 8px;
                padding: 8px 0;
                border-bottom: 1px dashed var(--dash-line);
            }

            .repo-table td:last-child {
                border-bottom: 0;
            }

            .repo-table td::before {
                content: attr(data-label);
                color: var(--dash-muted);
                font-size: 0.72rem;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.45px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/dashboard-panel.css?v=20260524-panel6">
</head>
<body>
    <div class="dashboard-shell">
        <?php render_dashboard_sidebar($dashboardLayout, 'trash'); ?>

        <div class="dashboard-main">
            <main class="layout">
                <?php render_dashboard_top_panel($dashboardLayout); ?>

                <?php foreach ($messages as $message): ?>
                    <section class="flash <?= htmlspecialchars((string) ($message['type'] ?? '')); ?>">
                        <p><?= htmlspecialchars((string) ($message['title'] ?? 'Informasi')); ?></p>
                        <?php if (($message['description'] ?? '') !== ''): ?>
                            <small><?= htmlspecialchars((string) $message['description']); ?></small>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Daftar Trash</h2>
                            <p><?= $isAdminRole ? 'Admin dapat melihat semua file di trash.' : 'User hanya melihat file miliknya sendiri.'; ?></p>
                        </div>
                        <span class="panel-count"><?= count($trashFiles); ?> file</span>
                    </div>

                    <?php if ($trashFiles === []): ?>
                        <div class="empty">Folder trash masih kosong.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="repo-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>File Trash</th>
                                        <th>Nama Asal</th>
                                        <?php if ($isAdminRole): ?>
                                            <th>Pemilik</th>
                                        <?php endif; ?>
                                        <th>Ukuran</th>
                                        <th>Masuk Trash</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trashFiles as $index => $file): ?>
                                        <?php
                                            $fileName = (string) $file['name'];
                                            $originalName = (string) ($file['original_name'] ?? $fileName);
                                            $extension = strtoupper((string) ($file['extension'] ?: 'N/A'));
                                        ?>
                                        <tr>
                                            <td data-label="No"><?= $index + 1; ?></td>
                                            <td data-label="File Trash">
                                                <div class="name-col">
                                                    <div class="file-info">
                                                        <span class="file-name"><?= htmlspecialchars($fileName); ?></span>
                                                        <span class="type-badge"><?= htmlspecialchars($extension); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Nama Asal"><?= htmlspecialchars($originalName); ?></td>
                                            <?php if ($isAdminRole): ?>
                                                <td data-label="Pemilik"><?= htmlspecialchars((string) ($file['owner_name'] ?? $file['owner_id'] ?? 'legacy')); ?></td>
                                            <?php endif; ?>
                                            <td data-label="Ukuran"><?= Formatter::bytes((int) $file['size']); ?></td>
                                            <td data-label="Masuk Trash"><?= Formatter::datetime((int) $file['modified']); ?></td>
                                            <td data-label="Aksi">
                                                <div class="actions">
                                                    <form action="trash_restore.php" method="post" onsubmit="return confirm('Kembalikan file ini ke katalog?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="file" value="<?= htmlspecialchars($fileName); ?>">
                                                        <button class="link-action link-restore" type="submit">Restore</button>
                                                    </form>
                                                    <form action="trash_delete.php" method="post" onsubmit="return confirm('Hapus permanen file ini dari Trash? File tidak bisa dikembalikan setelah aksi ini.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="file" value="<?= htmlspecialchars($fileName); ?>">
                                                        <button class="link-action link-delete" type="submit">Hapus Permanen</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
