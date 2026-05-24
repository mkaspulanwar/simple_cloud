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

function render_dashboard_sidebar(array $layoutContext, string $activeMenu): void
{
    $appName = (string) $layoutContext['appName'];
    $currentUser = $layoutContext['currentUser'];
    $roleLabels = $layoutContext['roleLabels'];
    $canUpload = (bool) $layoutContext['canUpload'];
    $canManageTrash = (bool) $layoutContext['canManageTrash'];
    $canViewAudit = (bool) $layoutContext['canViewAudit'];
    $canManageUsers = (bool) $layoutContext['canManageUsers'];
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
                <a class="sidebar-link <?= $activeMenu === 'overview' ? 'is-active' : ''; ?>" data-menu="overview" href="dashboard.php">
                    <span class="sidebar-icon">OV</span>
                    Overview
                </a>
                <?php if ($canUpload): ?>
                    <a class="sidebar-link <?= $activeMenu === 'upload' ? 'is-active' : ''; ?>" data-menu="upload" href="dashboard.php#upload-panel">
                        <span class="sidebar-icon">UP</span>
                        Upload
                    </a>
                <?php endif; ?>
                <a class="sidebar-link <?= $activeMenu === 'library' ? 'is-active' : ''; ?>" data-menu="library" href="dashboard.php#library-panel">
                    <span class="sidebar-icon">LB</span>
                    Library
                </a>
                <?php if ($canManageUsers): ?>
                    <a class="sidebar-link <?= $activeMenu === 'users' ? 'is-active' : ''; ?>" data-menu="users" href="dashboard.php#users-panel">
                        <span class="sidebar-icon">US</span>
                        Users
                    </a>
                <?php endif; ?>
                <?php if ($canManageTrash): ?>
                    <a class="sidebar-link <?= $activeMenu === 'trash' ? 'is-active' : ''; ?>" data-menu="trash" href="trash.php">
                        <span class="sidebar-icon">TR</span>
                        Trash
                    </a>
                <?php endif; ?>
                <?php if ($canViewAudit): ?>
                    <a class="sidebar-link <?= $activeMenu === 'audit' ? 'is-active' : ''; ?>" data-menu="audit" href="dashboard.php#audit-panel">
                        <span class="sidebar-icon">AU</span>
                        Audit
                    </a>
                <?php endif; ?>
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
