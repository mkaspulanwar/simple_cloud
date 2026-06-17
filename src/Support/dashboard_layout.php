<?php
declare(strict_types=1);

use App\Security\AuthManager;

function dashboard_layout_context(array $currentUser, string $appName): array
{
    $canUpload = AuthManager::can('upload');
    $canDownload = AuthManager::can('download');
    $canDelete = AuthManager::can('delete');
    $canRename = AuthManager::can('rename');
    $canManageTrash = AuthManager::can('manage_trash');
    $canViewAudit = AuthManager::can('view_audit');
    $canManageUsers = AuthManager::can('manage_users');
    $canBackup = AuthManager::can('backup');

    $roleLabels = [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
        'user' => 'User',
        'guest' => 'Guest',
    ];

    $roleDescriptions = [
        'superadmin' => [
            'title' => 'Workspace Command Center',
            'copy' => 'Kelola struktur tim, user, admin, file, dan audit dari satu pusat kontrol Cloudify.',
        ],
        'admin' => [
            'title' => 'Library Operations',
            'copy' => 'Jaga library tetap bersih dengan kontrol rename, delete, download, dan audit untuk semua asset.',
        ],
        'user' => [
            'title' => 'Personal Workspace',
            'copy' => 'Upload, rename, download, dan hapus asset pribadi tanpa mengganggu workspace orang lain.',
        ],
        'guest' => [
            'title' => 'Read-Only Dashboard',
            'copy' => 'Pantau ringkasan workspace tanpa akses upload, preview/download, rename, atau delete file.',
        ],
    ];

    return [
        'appName' => $appName,
        'currentUser' => $currentUser,
        'canUpload' => $canUpload,
        'canDownload' => $canDownload,
        'canDelete' => $canDelete,
        'canRename' => $canRename,
        'canManageTrash' => $canManageTrash,
        'canViewAudit' => $canViewAudit,
        'canManageUsers' => $canManageUsers,
        'canBackup' => $canBackup,
        'roleLabels' => $roleLabels,
        'roleDescription' => $roleDescriptions[$currentUser['role']] ?? $roleDescriptions['user'],
        'capabilities' => [
            ['label' => 'Upload', 'allowed' => $canUpload],
            ['label' => 'Read', 'allowed' => $canDownload],
            ['label' => 'Edit', 'allowed' => $canRename],
            ['label' => 'Delete', 'allowed' => $canDelete],
        ],
    ];
}

function dashboard_menu_items(array $layoutContext): array
{
    $items = [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'OV', 'href' => 'dashboard.php?view=overview', 'internal' => true],
    ];

    $items[] = ['key' => 'library', 'label' => 'Library', 'icon' => 'LB', 'href' => 'dashboard.php?view=library', 'internal' => true];

    if ((bool) $layoutContext['canManageUsers']) {
        $items[] = ['key' => 'users', 'label' => 'Users', 'icon' => 'US', 'href' => 'dashboard.php?view=users', 'internal' => true];
    }

    if ((bool) ($layoutContext['canBackup'] ?? false)) {
        $items[] = ['key' => 'backup', 'label' => 'Backup', 'icon' => 'BK', 'href' => 'dashboard.php?view=backup', 'internal' => true];
    }

    if ((bool) $layoutContext['canManageTrash']) {
        $items[] = ['key' => 'trash', 'label' => 'Trash', 'icon' => 'TR', 'href' => 'trash.php', 'internal' => false];
    }

    if ((bool) $layoutContext['canViewAudit']) {
        $items[] = ['key' => 'audit', 'label' => 'Audit', 'icon' => 'AU', 'href' => 'dashboard.php?view=audit', 'internal' => true];
    }

    return $items;
}

function render_dashboard_sidebar(array $layoutContext, string $activeMenu): void
{
    $appName = (string) $layoutContext['appName'];
    $currentUser = $layoutContext['currentUser'];
    $roleLabels = $layoutContext['roleLabels'];
    $menuItems = dashboard_menu_items($layoutContext);
    ?>
        <aside class="dashboard-sidebar" aria-label="Navigasi dashboard">
            <a class="dashboard-brand" href="dashboard.php" aria-label="<?= htmlspecialchars($appName); ?> Dashboard">
                <span class="dashboard-mark">C</span>
                <span>
                    <strong><?= htmlspecialchars($appName); ?></strong>
                    <span>Dashboard</span>
                </span>
            </a>

            <nav class="sidebar-block">
                <span class="sidebar-label">Workspace</span>
                <?php foreach ($menuItems as $item): ?>
                    <a class="sidebar-link <?= $activeMenu === $item['key'] ? 'is-active' : ''; ?>" data-menu="<?= htmlspecialchars((string) $item['key']); ?>" href="<?= htmlspecialchars((string) $item['href']); ?>">
                        <span class="sidebar-icon"><?= htmlspecialchars((string) $item['icon']); ?></span>
                        <?= htmlspecialchars((string) $item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <nav class="sidebar-block">
                <span class="sidebar-label">Publik</span>
                <a class="sidebar-link" href="index.php">
                    <span class="sidebar-icon">HM</span>
                    Halaman Utama
                </a>
                <a class="sidebar-link" href="catalog.php">
                    <span class="sidebar-icon">KG</span>
                    Katalog
                </a>
            </nav>

            <div class="sidebar-account">
                <div>
                    <strong><?= htmlspecialchars((string) $currentUser['name']); ?></strong>
                    <span><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst((string) $currentUser['role'])); ?></span>
                </div>
                <a class="sidebar-logout" href="logout.php">Logout</a>
            </div>
        </aside>
    <?php
}

function render_dashboard_mobile_nav(array $layoutContext, string $activeMenu): void
{
    $appName = (string) $layoutContext['appName'];
    $currentUser = $layoutContext['currentUser'];
    $roleLabels = $layoutContext['roleLabels'];
    $menuItems = dashboard_menu_items($layoutContext);
    $activeLabel = 'Overview';

    foreach ($menuItems as $item) {
        if ($item['key'] === $activeMenu) {
            $activeLabel = (string) $item['label'];
            break;
        }
    }
    ?>
        <header class="dashboard-mobile-nav">
            <a class="mobile-dashboard-brand" href="dashboard.php?view=overview" aria-label="<?= htmlspecialchars($appName); ?> Dashboard">
                <span class="dashboard-mark">C</span>
                <span>
                    <strong><?= htmlspecialchars($appName); ?></strong>
                    <small><?= htmlspecialchars($activeLabel); ?></small>
                </span>
            </a>
            <details class="mobile-menu">
                <summary aria-label="Buka menu dashboard">
                    <span></span>
                    <span></span>
                    <span></span>
                </summary>
                <div class="mobile-menu-panel">
                    <div class="mobile-account">
                        <strong><?= htmlspecialchars((string) $currentUser['name']); ?></strong>
                        <span><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst((string) $currentUser['role'])); ?></span>
                    </div>
                    <nav class="mobile-menu-links" aria-label="Menu dashboard mobile">
                        <?php foreach ($menuItems as $item): ?>
                            <a class="<?= $activeMenu === $item['key'] ? 'is-active' : ''; ?>" href="<?= htmlspecialchars((string) $item['href']); ?>">
                                <span><?= htmlspecialchars((string) $item['icon']); ?></span>
                                <?= htmlspecialchars((string) $item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                        <a href="catalog.php"><span>KG</span> Katalog</a>
                    </nav>
                    <nav class="mobile-menu-actions" aria-label="Aksi dashboard mobile">
                        <a class="mobile-home-action" href="index.php"><span>HM</span> Halaman Utama</a>
                        <a href="logout.php"><span>LO</span> Logout</a>
                    </nav>
                </div>
            </details>
        </header>
    <?php
}

function render_dashboard_top_panel(array $layoutContext): void
{
    $appName = (string) $layoutContext['appName'];
    $currentUser = $layoutContext['currentUser'];
    $roleLabels = $layoutContext['roleLabels'];
    $roleDescription = $layoutContext['roleDescription'];
    $capabilities = $layoutContext['capabilities'];
    ?>
                <section class="dashboard-top">
                    <div>
                        <p class="page-kicker"><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst((string) $currentUser['role'])); ?> Workspace</p>
                        <h1>Dashboard <?= htmlspecialchars($appName); ?></h1>
                        <p>Kelola file, akses user, dan aktivitas workspace dari panel yang bersih dan ringkas.</p>
                        <div class="capability-list" aria-label="Hak akses akun">
                            <?php foreach ($capabilities as $capability): ?>
                                <span class="capability-chip <?= $capability['allowed'] ? '' : 'is-off'; ?>">
                                    <span class="capability-dot"></span>
                                    <?= htmlspecialchars((string) $capability['label']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <aside class="top-card">
                        <strong><?= htmlspecialchars((string) $roleDescription['title']); ?></strong>
                        <p><?= htmlspecialchars((string) $roleDescription['copy']); ?></p>
                    </aside>
                </section>
    <?php
}
