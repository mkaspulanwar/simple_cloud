<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Services\UserStore;
use App\Support\Flash;
use App\Support\Formatter;

AuthManager::requirePermission('dashboard', 'index.php');

/**
 * @param string $extension
 */
function preview_category(string $extension): string
{
    $ext = strtolower($extension);

    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return 'image';
    }

    if ($ext === 'pdf') {
        return 'pdf';
    }

    if (in_array($ext, ['doc', 'docx'], true)) {
        return 'document';
    }

    if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
        return 'spreadsheet';
    }

    if (in_array($ext, ['ppt', 'pptx'], true)) {
        return 'presentation';
    }

    if (in_array($ext, ['zip', 'rar'], true)) {
        return 'archive';
    }

    if ($ext === 'txt') {
        return 'text';
    }

    return 'file';
}

function preview_label(string $category): string
{
    return match ($category) {
        'image' => 'Image Preview',
        'pdf' => 'PDF Document',
        'document' => 'Office Document',
        'spreadsheet' => 'Spreadsheet',
        'presentation' => 'Presentation',
        'archive' => 'Archive Package',
        'text' => 'Text File',
        default => 'File Asset',
    };
}

function file_public_url(string $fileName): string
{
    return 'download.php?inline=1&file=' . rawurlencode($fileName);
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$currentUser = AuthManager::user();

if ($currentUser === null) {
    header('Location: login.php');
    exit;
}

if (!AuthManager::can('dashboard')) {
    Flash::add('error', 'Akses terbatas', 'Akun ini tidak memiliki akses dashboard.');
    header('Location: index.php');
    exit;
}

$files = $ownershipStore->filterFilesForUser($storage->listFiles(), $currentUser);
$stats = $storage->getStatsForFiles($files);
$messages = Flash::pull();
$eventLimit = (int) app_config('audit.max_preview_events', 8);
$recentEvents = $auditLogger->recent(max($eventLimit * 8, $eventLimit));
$events = array_slice(array_values(array_filter(
    $recentEvents,
    static function (array $event) use ($currentUser): bool {
        if (in_array($currentUser['role'], ['superadmin', 'admin'], true)) {
            return true;
        }

        $eventUser = $event['user']['id'] ?? $event['context']['user_id'] ?? null;

        return $eventUser === $currentUser['id'];
    }
)), 0, $eventLimit);
$csrfToken = CsrfManager::token();
$allowedExtensions = app_config('storage.allowed_extensions', []);
$acceptAttribute = implode(',', array_map(static fn (string $ext): string => '.' . $ext, $allowedExtensions));
$appName = (string) app_config('app.name', 'Cloudify');
$hostAddress = gethostbyname(gethostname());
$lanUrl = preg_replace('/:\/\/[^\/]+/', '://' . $hostAddress, app_url());
$canUpload = AuthManager::can('upload');
$canDownload = AuthManager::can('download');
$canDelete = AuthManager::can('delete');
$canRename = AuthManager::can('rename');
$canViewAudit = AuthManager::can('view_audit');
$canManageUsers = AuthManager::can('manage_users');
$userStore = new UserStore((string) app_config('auth.users_path'));
$dashboardUsers = $canManageUsers ? $userStore->list() : [];
$roleCounts = $userStore->countsByRole();
$manageableRoles = $userStore->roles();
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
$roleDescription = $roleDescriptions[$currentUser['role']] ?? $roleDescriptions['user'];
$roleLabels = [
    'superadmin' => 'Superadmin',
    'admin' => 'Admin',
    'user' => 'User',
    'guest' => 'Guest',
];
$capabilities = [
    ['label' => 'Upload', 'allowed' => $canUpload],
    ['label' => 'Read', 'allowed' => $canDownload],
    ['label' => 'Edit', 'allowed' => $canRename],
    ['label' => 'Delete', 'allowed' => $canDelete],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName); ?></title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Serif:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink-900: #19212d;
            --ink-700: #3e4d5f;
            --ink-500: #6e7b8b;
            --line: #d7dee6;
            --brand-900: #123555;
            --brand-700: #1f5f8f;
            --brand-100: #eaf2f9;
            --ok-100: #edf7f1;
            --ok-700: #24633f;
            --error-100: #fbeeee;
            --error-700: #8d3232;
            --danger: #b94040;
            --surface: #ffffff;
            --surface-soft: #f6f8fb;
            --radius-lg: 18px;
            --radius-md: 12px;
            --shadow-soft: 0 18px 42px rgba(12, 33, 61, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--ink-900);
            background:
                radial-gradient(1300px 500px at -10% -8%, #e7edf5 0%, transparent 52%),
                radial-gradient(950px 420px at 103% 2%, #dce8f3 0%, transparent 50%),
                linear-gradient(180deg, #f4f7fb 0%, #eef2f7 100%);
            min-height: 100vh;
            padding: 24px 14px 40px;
        }

        .layout {
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            gap: 14px;
            animation: intro 0.45s ease;
        }

        @keyframes intro {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero {
            background: linear-gradient(135deg, #123555 0%, #17466f 42%, #2a729f 100%);
            border-radius: var(--radius-lg);
            color: #ecf5ff;
            padding: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -26px;
            bottom: -70px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            display: block;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 18px;
            align-items: center;
        }

        .brand-title {
            margin: 0;
            font-family: "IBM Plex Serif", serif;
            font-size: clamp(1.45rem, 3.5vw, 2.1rem);
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .brand-copy {
            margin: 10px 0 0;
            max-width: 780px;
            color: rgba(237, 246, 255, 0.92);
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 14px;
            backdrop-filter: blur(1.5px);
        }

        .hero-badge p {
            margin: 0;
            font-size: 0.9rem;
            color: rgba(238, 247, 255, 0.92);
        }

        .hero-badge strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1rem;
            color: #ffffff;
        }

        .account-bar {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .account-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            padding: 7px 10px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 0.86rem;
            font-weight: 800;
        }

        .role-badge {
            border-radius: 999px;
            padding: 3px 8px;
            background: rgba(255, 255, 255, 0.18);
            color: rgba(237, 247, 255, 0.9);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.4px;
        }

        .logout-link {
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 9px;
            padding: 7px 10px;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.1);
        }

        .stats {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
        }

        .stat-label {
            margin: 0;
            font-size: 0.77rem;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: var(--ink-500);
            font-weight: 700;
        }

        .stat-value {
            margin: 4px 0 0;
            font-size: 1.22rem;
            font-weight: 800;
        }

        .stat small {
            color: var(--ink-500);
        }

        .flash {
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            padding: 10px 12px;
            background: var(--surface);
        }

        .flash p {
            margin: 0;
            font-weight: 700;
        }

        .flash small {
            color: var(--ink-700);
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

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 16px;
            box-shadow: 0 8px 24px rgba(18, 44, 75, 0.04);
        }

        .panel h2 {
            margin: 0;
            font-size: 1.08rem;
            font-weight: 800;
        }

        .panel p {
            margin: 4px 0 0;
            color: var(--ink-700);
        }

        .upload-form {
            margin-top: 12px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .upload-form input[type="file"] {
            width: 100%;
            border: 1px dashed #bac8d7;
            background: #f9fbfd;
            border-radius: 10px;
            padding: 11px;
        }

        .user-admin-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 14px;
            align-items: start;
        }

        .user-form {
            display: grid;
            gap: 10px;
            padding: 12px;
            border: 1px solid #d9e1ea;
            border-radius: 12px;
            background: #f8fbfd;
        }

        .field {
            display: grid;
            gap: 6px;
            color: var(--ink-700);
            font-size: 0.86rem;
            font-weight: 800;
        }

        .field input,
        .field select {
            width: 100%;
            border: 1px solid #bdc9d6;
            border-radius: 10px;
            padding: 10px 11px;
            font: inherit;
            color: var(--ink-900);
            background: #ffffff;
        }

        .check-field {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            color: var(--ink-700);
            font-weight: 800;
        }

        .check-field input {
            width: 16px;
            height: 16px;
        }

        .user-list {
            display: grid;
            gap: 10px;
        }

        .user-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 12px;
            border: 1px solid #d9e1ea;
            border-radius: 12px;
            background: #ffffff;
        }

        .user-row strong,
        .user-row span {
            display: block;
        }

        .user-row span {
            color: var(--ink-500);
            font-size: 0.86rem;
            font-weight: 700;
        }

        .user-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .role-pill {
            border: 1px solid #c9d7e5;
            border-radius: 999px;
            padding: 4px 9px;
            color: #174f7a;
            background: #eef6fb;
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .btn-secondary {
            color: #294f6c;
            background: #e8f1f8;
            padding: 10px 13px;
        }

        .btn-danger {
            color: #ffffff;
            background: #b94040;
            padding: 10px 13px;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #174f7a 0%, #1c6a9e 100%);
            padding: 11px 16px;
        }

        .repo-toolbar {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .repo-tools {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search {
            border: 1px solid #bec8d3;
            border-radius: 10px;
            min-width: 260px;
            padding: 9px 11px;
            font-size: 0.92rem;
            outline: none;
            background: #fbfcfe;
        }

        .search:focus {
            border-color: #7ea2bf;
            box-shadow: 0 0 0 3px rgba(36, 97, 142, 0.14);
        }

        .view-switch {
            display: inline-flex;
            border: 1px solid #bfc9d3;
            border-radius: 10px;
            overflow: hidden;
            background: #f1f5f9;
        }

        .view-btn {
            border: 0;
            background: transparent;
            color: var(--ink-700);
            font-size: 0.87rem;
            padding: 9px 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .view-btn.active {
            color: #133f63;
            background: #dceaf5;
        }

        .result-text {
            margin-left: 4px;
            font-size: 0.86rem;
            color: var(--ink-500);
            font-weight: 600;
        }

        .table-wrap {
            margin-top: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #e8edf2;
            text-align: left;
            vertical-align: middle;
        }

        th {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: var(--ink-500);
        }

        .name-col {
            display: flex;
            gap: 9px;
            align-items: center;
        }

        .name-main {
            min-width: 0;
            flex: 1;
        }

        .mini-preview {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid #d9e1ea;
            overflow: hidden;
            flex-shrink: 0;
            background: #f4f7fb;
        }

        .mini-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 3px;
            display: block;
        }

        .previewable-image {
            cursor: zoom-in;
        }

        .mini-preview span {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 0.66rem;
            font-weight: 800;
            color: #345b78;
            background: #ebf3f9;
        }

        .file-name {
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .rename-icon-btn {
            width: 26px;
            height: 26px;
            border: 1px solid #c8d2dd;
            background: #f6f9fc;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            cursor: pointer;
            color: #4b6075;
            flex-shrink: 0;
        }

        .rename-icon-btn:hover {
            background: #e9f1f8;
            color: #294f6c;
        }

        .rename-icon-btn svg {
            width: 14px;
            height: 14px;
        }

        .type-badge {
            display: inline-block;
            border: 1px solid #cad5df;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.72rem;
            font-weight: 800;
            color: #365a73;
            background: #eff5fa;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .actions form,
        .card-actions form {
            margin: 0;
        }

        .link-action {
            border: 0;
            padding: 0;
            font-size: 0.86rem;
            font-weight: 800;
            background: transparent;
            cursor: pointer;
            text-decoration: none;
        }

        .link-download {
            color: #1f5f8f;
        }

        .link-rename {
            color: #5a6a7a;
        }

        .link-delete {
            color: var(--danger);
        }

        .grid-view {
            margin-top: 12px;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .file-card {
            border: 1px solid #d7e0ea;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 14px rgba(18, 44, 75, 0.05);
            display: flex;
            flex-direction: column;
            min-height: 290px;
        }

        .preview {
            height: 150px;
            background: #f2f6fa;
            border-bottom: 1px solid #dde5ee;
            position: relative;
            overflow: hidden;
            padding: 8px;
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 10px;
            background: #fff;
        }

        .preview-fallback {
            width: 100%;
            height: 100%;
            display: grid;
            place-content: center;
            padding: 13px;
            color: #2a4f6a;
            background: linear-gradient(135deg, #edf3f8 0%, #e6eef5 100%);
        }

        .preview-fallback-inner {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            border: 1px solid rgba(44, 79, 104, 0.14);
            background: rgba(255, 255, 255, 0.52);
            display: grid;
            place-content: center;
            text-align: center;
            gap: 3px;
        }

        .preview-fallback .ext {
            font-size: 0.89rem;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        .preview-fallback small {
            font-size: 0.71rem;
            color: #4f667a;
            font-weight: 600;
        }

        .preview-pdf {
            background: linear-gradient(135deg, #f9e6e6 0%, #f4dedd 100%);
            color: #7f3030;
        }

        .preview-document {
            background: linear-gradient(135deg, #e9eef7 0%, #dce6f6 100%);
            color: #2f4c7a;
        }

        .preview-spreadsheet {
            background: linear-gradient(135deg, #e7f4ed 0%, #d6eee2 100%);
            color: #2f6a4b;
        }

        .preview-presentation {
            background: linear-gradient(135deg, #f8efe4 0%, #f1e4d5 100%);
            color: #7a5530;
        }

        .preview-archive {
            background: linear-gradient(135deg, #efe9f5 0%, #e8deef 100%);
            color: #5c4571;
        }

        .preview-text {
            background: linear-gradient(135deg, #edf1f5 0%, #e3e8ef 100%);
            color: #3f4f66;
        }

        .card-body {
            padding: 11px 12px 12px;
            display: grid;
            gap: 7px;
        }

        .card-name {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .card-meta {
            margin: 0;
            color: var(--ink-700);
            font-size: 0.83rem;
        }

        .card-actions {
            margin-top: 2px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .icon-inline-btn {
            border: 1px solid #c8d2dd;
            background: #f6f9fc;
            color: #4b6075;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            cursor: pointer;
        }

        .icon-inline-btn:hover {
            background: #e9f1f8;
            color: #294f6c;
        }

        .icon-inline-btn svg {
            width: 14px;
            height: 14px;
        }

        .audit-list {
            margin: 12px 0 0;
            list-style: none;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .audit-item {
            border: 1px solid #dce4ed;
            border-radius: 11px;
            background: var(--surface-soft);
            padding: 10px 12px;
            font-size: 0.89rem;
        }

        .audit-item strong {
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #3e5164;
            letter-spacing: 0.5px;
        }

        .audit-meta {
            margin-top: 3px;
            color: var(--ink-500);
            font-size: 0.8rem;
        }

        .empty {
            margin-top: 10px;
            border: 1px dashed #bcc8d5;
            background: #f7fafc;
            border-radius: 12px;
            color: var(--ink-500);
            text-align: center;
            padding: 16px;
        }

        dialog {
            border: 1px solid #c8d3de;
            border-radius: 14px;
            padding: 0;
            width: min(92vw, 470px);
            box-shadow: 0 28px 80px rgba(14, 34, 58, 0.25);
        }

        dialog::backdrop {
            background: rgba(16, 34, 54, 0.36);
        }

        .dialog-head {
            padding: 14px 16px;
            border-bottom: 1px solid #dce5ef;
            background: #f7fafd;
        }

        .dialog-head h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
        }

        .dialog-body {
            padding: 14px 16px 16px;
            display: grid;
            gap: 10px;
        }

        .dialog-meta {
            font-size: 0.9rem;
            color: var(--ink-700);
        }

        .dialog-input {
            width: 100%;
            border: 1px solid #bdc9d6;
            border-radius: 10px;
            padding: 10px 11px;
            font-size: 0.95rem;
            outline: none;
        }

        .dialog-input:focus {
            border-color: #7ea2bf;
            box-shadow: 0 0 0 3px rgba(36, 97, 142, 0.14);
        }

        .dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-secondary {
            background: #eef3f7;
            color: #374a5f;
            border: 1px solid #c2d0dc;
            padding: 9px 13px;
        }

        .image-lightbox {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background: rgba(12, 24, 38, 0.74);
        }

        .image-lightbox-frame {
            position: relative;
            width: min(96vw, 940px);
            max-height: 92vh;
            border-radius: 14px;
            padding: 12px 12px 8px;
            background: #0e1722;
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 24px 60px rgba(3, 10, 18, 0.48);
            display: grid;
            gap: 8px;
        }

        .image-lightbox-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            color: #d8e4ee;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .image-lightbox-close {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: #f3f8fd;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
        }

        .image-lightbox-view {
            border-radius: 11px;
            background: #0a121b;
            padding: 8px;
            max-height: calc(92vh - 86px);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .image-lightbox-view img {
            width: 100%;
            height: 100%;
            max-height: calc(92vh - 110px);
            object-fit: contain;
            display: block;
            border-radius: 8px;
            background: #ffffff;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 980px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .file-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .user-admin-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 14px 9px 28px;
            }

            .hero,
            .panel {
                padding: 13px;
            }

            .upload-form {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .search {
                min-width: 0;
                width: 100%;
            }

            .repo-toolbar {
                align-items: stretch;
            }

            .repo-tools {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .view-switch {
                width: 100%;
            }

            .view-btn {
                flex: 1;
            }

            .result-text {
                margin-left: 0;
            }

            .file-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap {
                overflow: visible;
            }

            .repo-table {
                border-collapse: separate;
            }

            .repo-table thead {
                display: none;
            }

            .repo-table,
            .repo-table tbody {
                display: block;
                width: 100%;
            }

            .repo-table tbody tr {
                display: block;
                background: #f9fbfd;
                border: 1px solid #d8e3ee;
                border-radius: 12px;
                padding: 10px;
                margin-bottom: 10px;
            }

            .repo-table tbody tr:last-child {
                margin-bottom: 0;
            }

            .repo-table td {
                display: grid;
                grid-template-columns: minmax(82px, 106px) 1fr;
                gap: 8px;
                align-items: start;
                border-bottom: 1px dashed #dce5ef;
                padding: 8px 0;
            }

            .repo-table td:last-child {
                border-bottom: 0;
                padding-bottom: 0;
            }

            .repo-table td::before {
                content: attr(data-label);
                font-size: 0.72rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                color: var(--ink-500);
                line-height: 1.35;
                padding-top: 2px;
            }

            .repo-table td[data-label="Nama File"] {
                align-items: center;
            }

            .repo-table td[data-label="Nama File"] .name-col {
                gap: 8px;
                align-items: flex-start;
            }

            .repo-table td[data-label="Nama File"] .mini-preview {
                width: 32px;
                height: 32px;
            }

            .repo-table td[data-label="Aksi"] .actions {
                gap: 10px;
            }

            .repo-table td[data-label="Aksi"] .link-action {
                font-size: 0.83rem;
            }

            .image-lightbox {
                padding: 8px;
            }

            .image-lightbox-frame {
                width: 100%;
                max-height: 94vh;
                padding: 10px 10px 8px;
            }
        }

        /* Modern dashboard layer, aligned with the public catalog and login screens. */
        :root {
            --ink: #14171f;
            --muted: #6a7280;
            --subtle: #8b94a3;
            --line: #e6eaf0;
            --brand: #0f9f8f;
            --brand-dark: #0b6f68;
            --wash: #eef6f5;
            --soft: #f6f8fb;
            --surface: #ffffff;
            --shadow: 0 20px 45px rgba(20, 23, 31, 0.08);
        }

        body {
            padding: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: #fbfcfd;
        }

        svg {
            width: 1.12em;
            height: 1.12em;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2.1;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px clamp(20px, 4vw, 48px);
            background: rgba(251, 252, 253, 0.88);
            border-bottom: 1px solid rgba(230, 234, 240, 0.9);
            backdrop-filter: blur(18px);
        }

        .brand,
        .top-actions,
        .nav-actions {
            display: inline-flex;
            align-items: center;
        }

        .brand {
            gap: 10px;
            color: var(--ink);
            font-weight: 900;
            text-decoration: none;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: #ffffff;
            background: var(--ink);
            box-shadow: inset 0 -5px 0 rgba(15, 159, 143, 0.52);
        }

        .brand-name {
            font-size: 1.05rem;
        }

        .top-actions {
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link,
        .logout-link {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 15px;
            color: var(--muted);
            background: transparent;
            font-size: 0.92rem;
            font-weight: 800;
            text-decoration: none;
            transition: background 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .nav-link:hover,
        .logout-link:hover {
            color: var(--ink);
            background: #eef2f6;
            transform: translateY(-1px);
        }

        .layout {
            width: min(100%, 1180px);
            padding: clamp(20px, 3vw, 34px) clamp(16px, 4vw, 48px) 44px;
            gap: 18px;
        }

        .hero,
        .panel,
        .stat,
        .flash {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .hero {
            padding: clamp(22px, 4vw, 36px);
            color: var(--ink);
            background:
                linear-gradient(180deg, rgba(20, 23, 31, 0.16), rgba(20, 23, 31, 0.74)),
                url("assets/images/hero-background.jpg") center / cover no-repeat;
            min-height: 330px;
            display: grid;
            align-items: end;
        }

        .hero::after {
            display: none;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            grid-template-columns: minmax(0, 1.25fr) minmax(260px, 0.75fr);
            align-items: end;
        }

        .brand-title {
            margin: 0;
            color: #ffffff;
            font-family: inherit;
            font-size: clamp(2.2rem, 5vw, 4.35rem);
            font-weight: 900;
            line-height: 0.98;
            letter-spacing: 0;
        }

        .brand-copy {
            max-width: 690px;
            color: rgba(255, 255, 255, 0.86);
            font-size: clamp(1rem, 1.4vw, 1.12rem);
            line-height: 1.72;
        }

        .hero-badge {
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(16px);
        }

        .hero-badge strong,
        .hero-badge p {
            color: #ffffff;
        }

        .account-bar,
        .capability-list,
        .actions,
        .card-actions,
        .repo-tools,
        .user-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .account-chip,
        .capability-chip,
        .role-badge,
        .role-pill,
        .type-badge,
        .story-pill {
            border-radius: 999px;
        }

        .account-chip {
            min-height: 38px;
            padding: 0 12px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.24);
            backdrop-filter: blur(14px);
        }

        .role-badge {
            color: var(--brand-dark);
            background: #dff7f3;
        }

        .capability-list {
            margin-top: 18px;
        }

        .capability-chip {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 11px;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.84rem;
            font-weight: 850;
        }

        .capability-chip.is-off {
            color: rgba(255, 255, 255, 0.72);
            background: rgba(20, 23, 31, 0.28);
        }

        .capability-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #65e5d5;
        }

        .capability-chip.is-off .capability-dot {
            background: #d2d8df;
        }

        .stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .stat {
            padding: 18px;
        }

        .stat-label,
        .panel > p,
        .repo-toolbar p,
        .card-meta,
        .audit-meta,
        .empty,
        small {
            color: var(--muted);
        }

        .stat-value {
            color: var(--ink);
            font-size: clamp(1.45rem, 2.4vw, 2rem);
            letter-spacing: 0;
        }

        .panel {
            padding: clamp(18px, 2.5vw, 26px);
        }

        .panel h2 {
            margin-top: 0;
            color: var(--ink);
            font-size: 1.18rem;
            letter-spacing: 0;
        }

        input,
        select,
        .search,
        .dialog-input {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            color: var(--ink);
            outline: none;
        }

        input:focus,
        select:focus,
        .search:focus,
        .dialog-input:focus {
            background: #ffffff;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(15, 159, 143, 0.14);
        }

        .btn,
        .link-action,
        .view-btn,
        .rename-icon-btn,
        .icon-inline-btn {
            border-radius: 8px;
            font-weight: 900;
            transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .btn-primary,
        .link-download {
            color: #ffffff;
            background: var(--brand);
            border-color: var(--brand);
        }

        .btn-primary:hover,
        .link-download:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        .btn-secondary,
        .view-switch,
        .view-btn,
        .link-action,
        .rename-icon-btn,
        .icon-inline-btn {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--ink);
        }

        .view-btn.active {
            color: #ffffff;
            background: var(--ink);
            border-color: var(--ink);
        }

        .btn-danger,
        .link-delete {
            color: #9b2c2c;
            border-color: #f0d0d0;
            background: #fff5f5;
        }

        .repo-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
        }

        .table-wrap {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        .repo-table th {
            color: var(--muted);
            background: var(--soft);
        }

        .repo-table td {
            border-color: var(--line);
        }

        .mini-preview,
        .preview,
        .preview-fallback {
            border-radius: 8px;
            background: var(--soft);
        }

        .file-card,
        .user-row,
        .audit-item,
        .empty {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .file-card:hover,
        .user-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(20, 23, 31, 0.08);
        }

        .upload-form,
        .user-form {
            border-radius: 8px;
            background: var(--soft);
        }

        .access-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 14px;
            border: 1px solid #d8efeb;
            border-radius: 8px;
            color: var(--brand-dark);
            background: var(--wash);
            font-weight: 800;
            line-height: 1.55;
        }

        @media (max-width: 900px) {
            .hero-grid,
            .repo-toolbar {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .topbar {
                padding: 12px 14px;
            }

            .brand-name {
                display: none;
            }

            .layout {
                padding: 16px 14px 32px;
            }

            .hero {
                min-height: 360px;
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }

        /* Dedicated dashboard shell. */
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

        body {
            min-height: 100vh;
            padding: 0;
            color: var(--dash-ink);
            background: var(--dash-bg);
            font-family: Inter, "Manrope", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }

        .topbar {
            display: none;
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
            padding: 0;
            gap: 18px;
        }

        .dashboard-top {
            order: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            margin-bottom: 18px;
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

        .stats {
            order: 2;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .flash {
            order: 3;
        }

        #upload-panel {
            order: 4;
        }

        #library-panel {
            order: 5;
        }

        #users-panel {
            order: 6;
        }

        #audit-panel {
            order: 7;
        }

        .stat,
        .panel,
        .flash {
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: var(--dash-panel);
            box-shadow: var(--dash-shadow);
        }

        .stat {
            min-height: 124px;
            display: grid;
            align-content: space-between;
            padding: 16px;
        }

        .stat-label {
            margin: 0;
            color: var(--dash-muted);
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.55px;
        }

        .stat-value {
            margin: 8px 0 0;
            color: var(--dash-ink);
            font-size: clamp(1.35rem, 2.5vw, 1.9rem);
            line-height: 1.15;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .stat small {
            display: block;
            margin-top: 10px;
            color: var(--dash-muted);
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(320px, 0.82fr) minmax(0, 1.18fr);
            gap: 18px;
            align-items: start;
        }

        .workspace-stack {
            display: grid;
            gap: 18px;
        }

        .panel {
            padding: 18px;
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

        .upload-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 14px;
        }

        .upload-form input[type="file"],
        .field input,
        .field select,
        .search,
        .dialog-input {
            width: 100%;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            color: var(--dash-ink);
            background: var(--dash-soft);
            font: inherit;
            outline: none;
        }

        .upload-form input[type="file"] {
            padding: 12px;
            border-style: dashed;
            background: #fbfcfd;
        }

        .field input,
        .field select,
        .search,
        .dialog-input {
            padding: 10px 11px;
        }

        .upload-form input[type="file"]:focus,
        .field input:focus,
        .field select:focus,
        .search:focus,
        .dialog-input:focus {
            border-color: var(--dash-accent);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(15, 143, 131, 0.14);
        }

        .btn,
        .link-action,
        .view-btn,
        .rename-icon-btn,
        .icon-inline-btn {
            border-radius: var(--dash-radius);
            font-weight: 900;
            cursor: pointer;
            transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .btn:hover,
        .link-action:hover,
        .rename-icon-btn:hover,
        .icon-inline-btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            min-height: 42px;
            color: #ffffff;
            background: var(--dash-accent);
            border: 1px solid var(--dash-accent);
            padding: 0 15px;
        }

        .btn-primary:hover {
            background: var(--dash-accent-dark);
            border-color: var(--dash-accent-dark);
        }

        .btn-secondary {
            min-height: 38px;
            color: var(--dash-ink);
            background: #ffffff;
            border: 1px solid var(--dash-line);
            padding: 0 13px;
        }

        .btn-danger {
            min-height: 38px;
            color: #b42318;
            background: #fff4f3;
            border: 1px solid #ffd6d2;
            padding: 0 13px;
        }

        .repo-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            margin: 0 0 14px;
        }

        .repo-tools {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-wrap: wrap;
        }

        .search {
            min-width: 250px;
            background: #ffffff;
        }

        .view-switch {
            display: inline-flex;
            padding: 3px;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: var(--dash-soft);
        }

        .view-btn {
            min-height: 32px;
            border: 0;
            padding: 0 11px;
            color: var(--dash-muted);
            background: transparent;
        }

        .view-btn.active {
            color: #ffffff;
            background: var(--dash-sidebar);
        }

        .result-text {
            color: var(--dash-muted);
            font-size: 0.84rem;
            font-weight: 800;
        }

        .table-wrap {
            margin-top: 0;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            overflow: auto;
            box-shadow: none;
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
        }

        .repo-table td {
            padding: 12px;
            border-bottom: 1px solid var(--dash-line);
            color: #384152;
        }

        .repo-table tbody tr:hover {
            background: #fbfcfd;
        }

        .repo-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .name-col {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 220px;
        }

        .mini-preview {
            width: 38px;
            height: 38px;
            border-radius: var(--dash-radius);
            border: 1px solid var(--dash-line);
            background: var(--dash-soft);
            overflow: hidden;
            flex-shrink: 0;
        }

        .mini-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            padding: 0;
        }

        .mini-preview span {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: var(--dash-accent-dark);
            background: #edf9f7;
            font-size: 0.66rem;
            font-weight: 900;
        }

        .file-name,
        .card-name {
            color: var(--dash-ink);
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .type-badge,
        .role-pill {
            display: inline-flex;
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

        .actions,
        .card-actions,
        .user-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .link-action {
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--dash-line);
            padding: 0 10px;
            text-decoration: none;
            font-size: 0.82rem;
            background: #ffffff;
        }

        .link-download {
            color: #ffffff;
            border-color: var(--dash-accent);
            background: var(--dash-accent);
        }

        .link-delete {
            color: #b42318;
            border-color: #ffd6d2;
            background: #fff4f3;
        }

        .rename-icon-btn,
        .icon-inline-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--dash-line);
            color: #475467;
            background: #ffffff;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .file-card,
        .user-row,
        .audit-item,
        .empty {
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: #ffffff;
        }

        .file-card {
            overflow: hidden;
            min-height: 286px;
            box-shadow: none;
        }

        .preview {
            height: 148px;
            padding: 8px;
            border-bottom: 1px solid var(--dash-line);
            background: var(--dash-soft);
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--dash-radius);
            background: #ffffff;
        }

        .preview-fallback,
        .preview-fallback-inner {
            border-radius: var(--dash-radius);
        }

        .card-body {
            padding: 13px;
            display: grid;
            gap: 8px;
        }

        .card-meta {
            margin: 0;
            color: var(--dash-muted);
            font-size: 0.82rem;
        }

        .user-admin-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: minmax(260px, 0.8fr) minmax(0, 1.2fr);
            gap: 14px;
            align-items: start;
        }

        .user-form {
            display: grid;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--dash-line);
            border-radius: var(--dash-radius);
            background: var(--dash-soft);
        }

        .field {
            display: grid;
            gap: 6px;
            color: #475467;
            font-size: 0.84rem;
            font-weight: 900;
        }

        .check-field {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475467;
            font-weight: 900;
        }

        .user-list,
        .audit-list {
            display: grid;
            gap: 10px;
        }

        .user-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            padding: 12px;
        }

        .user-row strong,
        .user-row span {
            display: block;
        }

        .user-row span {
            color: var(--dash-muted);
            font-size: 0.84rem;
            font-weight: 700;
        }

        .audit-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .audit-item {
            padding: 12px;
            color: #384152;
        }

        .audit-item strong {
            color: var(--dash-ink);
            font-size: 0.76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .audit-meta {
            margin-top: 5px;
            color: var(--dash-muted);
            font-size: 0.8rem;
        }

        .access-note,
        .empty {
            margin-top: 12px;
            padding: 14px;
            color: var(--dash-muted);
            background: var(--dash-soft);
            line-height: 1.6;
        }

        .access-note {
            display: flex;
            gap: 10px;
            border: 1px solid #cde7e3;
            color: var(--dash-accent-dark);
            background: #edf9f7;
            font-weight: 800;
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

        @media (max-width: 1120px) {
            .dashboard-shell {
                grid-template-columns: 232px minmax(0, 1fr);
            }

            .stats,
            .file-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .workspace-grid {
                grid-template-columns: 1fr;
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

            .dashboard-top,
            .repo-toolbar,
            .user-admin-grid {
                grid-template-columns: 1fr;
            }

            .top-card {
                min-width: 0;
            }

            .repo-tools {
                justify-content: stretch;
            }

            .search {
                min-width: 0;
                flex: 1 1 100%;
            }
        }

        @media (max-width: 620px) {
            .stats,
            .file-grid {
                grid-template-columns: 1fr;
            }

            .panel-head {
                display: grid;
            }

            .view-switch {
                width: 100%;
            }

            .view-btn {
                flex: 1;
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

            .name-col {
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-shell">
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
                <a class="sidebar-link is-active" href="dashboard.php">
                    <span class="sidebar-icon">OV</span>
                    Overview
                </a>
                <?php if ($canUpload): ?>
                    <a class="sidebar-link" href="#upload-panel">
                        <span class="sidebar-icon">UP</span>
                        Upload
                    </a>
                <?php endif; ?>
                <a class="sidebar-link" href="#library-panel">
                    <span class="sidebar-icon">LB</span>
                    Library
                </a>
                <?php if ($canManageUsers): ?>
                    <a class="sidebar-link" href="#users-panel">
                        <span class="sidebar-icon">US</span>
                        Users
                    </a>
                <?php endif; ?>
                <?php if ($canViewAudit): ?>
                    <a class="sidebar-link" href="#audit-panel">
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
                    <strong><?= htmlspecialchars($currentUser['name']); ?></strong>
                    <span><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst($currentUser['role'])); ?></span>
                </div>
                <a class="sidebar-logout" href="logout.php">Logout</a>
            </div>
        </aside>

        <div class="dashboard-main">
            <main class="layout">
                <section class="dashboard-top">
                    <div>
                        <p class="page-kicker"><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst($currentUser['role'])); ?> Workspace</p>
                        <h1>Dashboard <?= htmlspecialchars($appName); ?></h1>
                        <p>Kelola file, akses user, dan aktivitas workspace dari panel yang bersih dan ringkas.</p>
                        <div class="capability-list" aria-label="Hak akses akun">
                            <?php foreach ($capabilities as $capability): ?>
                                <span class="capability-chip <?= $capability['allowed'] ? '' : 'is-off'; ?>">
                                    <span class="capability-dot"></span>
                                    <?= htmlspecialchars($capability['label']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <aside class="top-card">
                        <strong><?= htmlspecialchars($roleDescription['title']); ?></strong>
                        <p><?= htmlspecialchars($roleDescription['copy']); ?></p>
                    </aside>
                </section>

                <section class="stats">
                    <article class="stat">
                        <p class="stat-label">Total File</p>
                        <p class="stat-value"><?= (int) $stats['total_files']; ?></p>
                        <small>Asset aktif dalam library</small>
                    </article>
                    <article class="stat">
                        <p class="stat-label">Storage Terpakai</p>
                        <p class="stat-value"><?= Formatter::bytes((int) $stats['total_size']); ?></p>
                        <small><?= (float) $stats['usage_percent']; ?>% dari kapasitas</small>
                    </article>
                    <article class="stat">
                        <p class="stat-label">Level Akses</p>
                        <p class="stat-value"><?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst($currentUser['role'])); ?></p>
                        <small><?= $canUpload ? 'Dapat upload file' : 'Mode tanpa upload'; ?></small>
                    </article>
                    <article class="stat">
                        <p class="stat-label">Akses LAN</p>
                        <p class="stat-value"><?= htmlspecialchars($hostAddress); ?></p>
                        <small><?= htmlspecialchars((string) $lanUrl); ?></small>
                    </article>
                </section>

        <?php foreach ($messages as $message): ?>
            <section class="flash <?= htmlspecialchars((string) ($message['type'] ?? '')); ?>">
                <p><?= htmlspecialchars((string) ($message['title'] ?? 'Informasi')); ?></p>
                <?php if (($message['description'] ?? '') !== ''): ?>
                    <small><?= htmlspecialchars((string) $message['description']); ?></small>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($canUpload): ?>
            <section class="panel" id="upload-panel">
                <div class="panel-head">
                    <div>
                        <h2>Upload Management</h2>
                        <p>Tambahkan satu atau banyak gambar ke library Cloudify. Format didukung: <?= htmlspecialchars(implode(', ', $allowedExtensions)); ?>. Maksimum <?= Formatter::bytes($storage->maxFileSize()); ?> per gambar.</p>
                    </div>
                    <span class="panel-count"><?= Formatter::bytes($storage->maxFileSize()); ?> max</span>
                </div>
                <form class="upload-form" action="upload.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <input type="file" name="fileToUpload[]" accept="<?= htmlspecialchars($acceptAttribute); ?>" multiple required>
                    <button class="btn btn-primary" type="submit">Upload Gambar</button>
                </form>
            </section>
        <?php else: ?>
            <section class="panel" id="upload-panel">
                <div class="panel-head">
                    <div>
                        <h2>Upload Management</h2>
                        <p>Status akses upload untuk akun saat ini.</p>
                    </div>
                    <span class="panel-count">Limited</span>
                </div>
                <div class="access-note">
                    <span aria-hidden="true">i</span>
                    <span>Role <?= htmlspecialchars($roleLabels[$currentUser['role']] ?? ucfirst($currentUser['role'])); ?> berjalan dalam mode terbatas. Akun ini tidak dapat upload, read/download, edit/rename, atau delete file.</span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canManageUsers): ?>
            <section class="panel" id="users-panel">
                <div class="panel-head">
                    <div>
                        <h2>User & Admin Management</h2>
                        <p>Superadmin dapat membuat admin, user, atau guest, mengganti role, reset password, menonaktifkan akun, dan menjaga struktur workspace tetap terkendali.</p>
                    </div>
                    <span class="panel-count"><?= count($dashboardUsers); ?> akun</span>
                </div>
                <div class="user-admin-grid">
                    <form class="user-form" action="manage_user.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="save">
                        <label class="field">
                            Username
                            <input type="text" name="user_id" pattern="[A-Za-z0-9._-]{3,40}" maxlength="40" placeholder="contoh: admin.divisi" required>
                        </label>
                        <label class="field">
                            Nama
                            <input type="text" name="name" maxlength="80" placeholder="Nama lengkap" required>
                        </label>
                        <label class="field">
                            Role
                            <select name="role" required>
                                <?php foreach ($manageableRoles as $role): ?>
                                    <option value="<?= htmlspecialchars($role); ?>"><?= htmlspecialchars(ucfirst($role)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            Password
                            <input type="password" name="password" minlength="6" placeholder="Minimal 6 karakter" required>
                        </label>
                        <label class="check-field">
                            <input type="checkbox" name="active" checked>
                            Aktif
                        </label>
                        <button class="btn btn-primary" type="submit">Tambah User</button>
                    </form>

                    <div class="user-list">
                        <?php foreach ($dashboardUsers as $dashboardUser): ?>
                            <?php
                                $dashboardUserId = (string) ($dashboardUser['id'] ?? '');
                                $isCurrentDashboardUser = $dashboardUserId === $currentUser['id'];
                            ?>
                            <article class="user-row">
                                <form class="user-form" action="manage_user.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($dashboardUserId); ?>">
                                    <strong><?= htmlspecialchars((string) ($dashboardUser['name'] ?? $dashboardUserId)); ?></strong>
                                    <span><?= htmlspecialchars($dashboardUserId); ?> - <?= htmlspecialchars(($dashboardUser['active'] ?? false) ? 'aktif' : 'nonaktif'); ?></span>
                                    <label class="field">
                                        Nama
                                        <input type="text" name="name" maxlength="80" value="<?= htmlspecialchars((string) ($dashboardUser['name'] ?? '')); ?>" required>
                                    </label>
                                    <label class="field">
                                        Role
                                        <select name="role" <?= $isCurrentDashboardUser ? 'disabled' : ''; ?> required>
                                            <?php foreach ($manageableRoles as $role): ?>
                                                <option value="<?= htmlspecialchars($role); ?>" <?= (($dashboardUser['role'] ?? '') === $role) ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($role)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($isCurrentDashboardUser): ?>
                                            <input type="hidden" name="role" value="<?= htmlspecialchars((string) ($dashboardUser['role'] ?? 'superadmin')); ?>">
                                        <?php endif; ?>
                                    </label>
                                    <label class="field">
                                        Password baru
                                        <input type="password" name="password" minlength="6" placeholder="Kosongkan bila tidak diubah">
                                    </label>
                                    <label class="check-field">
                                        <input type="checkbox" name="active" <?= (($dashboardUser['active'] ?? false) || $isCurrentDashboardUser) ? 'checked' : ''; ?> <?= $isCurrentDashboardUser ? 'disabled' : ''; ?>>
                                        Aktif
                                    </label>
                                    <?php if ($isCurrentDashboardUser): ?>
                                        <input type="hidden" name="active" value="1">
                                    <?php endif; ?>
                                    <div class="user-actions">
                                        <span class="role-pill"><?= htmlspecialchars((string) ($dashboardUser['role'] ?? 'user')); ?></span>
                                        <button class="btn btn-secondary" type="submit">Simpan</button>
                                    </div>
                                </form>
                                <div class="user-actions">
                                    <?php if (!$isCurrentDashboardUser): ?>
                                        <form action="manage_user.php" method="post" onsubmit="return confirm('Hapus user ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($dashboardUserId); ?>">
                                            <button class="btn btn-danger" type="submit">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel" id="library-panel">
            <div class="panel-head">
                <div>
                    <h2>Cloudify Library</h2>
                    <p>Library mendukung mode list dan grid agar asset mudah dicari, dipreview, dan dikelola sesuai role.</p>
                </div>
                <span class="panel-count"><?= count($files); ?> file</span>
            </div>
            <div class="repo-toolbar">
                <p>Cari file berdasarkan nama atau ubah tampilan sesuai kebutuhan kerja.</p>
                <div class="repo-tools">
                    <input id="fileSearch" class="search" type="search" placeholder="Cari nama file...">
                    <div class="view-switch" role="group" aria-label="Switch view">
                        <button type="button" class="view-btn active" data-view-target="list">List</button>
                        <button type="button" class="view-btn" data-view-target="grid">Grid</button>
                    </div>
                    <span id="resultText" class="result-text"></span>
                </div>
            </div>

            <?php if ($files === []): ?>
                <div class="empty">Belum ada file tersimpan di server.</div>
            <?php else: ?>
                <div id="listView" class="table-wrap">
                    <table class="repo-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama File</th>
                                <th>Tipe</th>
                                <th>Ukuran</th>
                                <?php if (in_array($currentUser['role'], ['superadmin', 'admin'], true)): ?>
                                    <th>Pemilik</th>
                                <?php endif; ?>
                                <th>Terakhir Ubah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $index => $file): ?>
                                <?php
                                    $fileName = (string) $file['name'];
                                    $extension = (string) ($file['extension'] === '' ? 'N/A' : strtoupper((string) $file['extension']));
                                    $category = preview_category((string) $file['extension']);
                                ?>
                                <tr data-file-item data-view="list" data-name="<?= htmlspecialchars(strtolower($fileName)); ?>">
                                    <td data-label="No"><?= $index + 1; ?></td>
                                    <td data-label="Nama File">
                                        <div class="name-col">
                                            <div class="mini-preview">
                                                <?php if ($category === 'image'): ?>
                                                    <img class="previewable-image" src="<?= htmlspecialchars(file_public_url($fileName)); ?>" alt="<?= htmlspecialchars($fileName); ?>" data-preview-src="<?= htmlspecialchars(file_public_url($fileName)); ?>" data-preview-alt="<?= htmlspecialchars($fileName); ?>" tabindex="0" role="button" aria-label="Preview gambar <?= htmlspecialchars($fileName); ?>">
                                                <?php else: ?>
                                                    <span><?= htmlspecialchars($extension); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="name-main">
                                                <span class="file-name"><?= htmlspecialchars($fileName); ?></span>
                                            </div>
                                            <?php if ($canRename): ?>
                                                <button type="button" class="rename-icon-btn" title="Rename file" aria-label="Rename file" data-open-rename data-file="<?= htmlspecialchars($fileName, ENT_QUOTES); ?>">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 20h9"/>
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                                    </svg>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Tipe"><span class="type-badge"><?= htmlspecialchars($extension); ?></span></td>
                                    <td data-label="Ukuran"><?= Formatter::bytes((int) $file['size']); ?></td>
                                    <?php if (in_array($currentUser['role'], ['superadmin', 'admin'], true)): ?>
                                        <td data-label="Pemilik"><?= htmlspecialchars((string) ($file['owner_name'] ?? $file['owner_id'] ?? 'legacy')); ?></td>
                                    <?php endif; ?>
                                    <td data-label="Terakhir Ubah"><?= Formatter::datetime((int) $file['modified']); ?></td>
                                    <td data-label="Aksi">
                                        <div class="actions">
                                            <?php if ($canDownload): ?>
                                                <a class="link-action link-download" href="download.php?file=<?= urlencode($fileName); ?>">Download</a>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                                <form action="delete.php" method="post" onsubmit="return confirm('Hapus file ini dari server?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="file" value="<?= htmlspecialchars($fileName); ?>">
                                                    <button class="link-action link-delete" type="submit">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="gridView" class="grid-view" hidden>
                    <div class="file-grid">
                        <?php foreach ($files as $file): ?>
                            <?php
                                $fileName = (string) $file['name'];
                                $fileExtRaw = (string) $file['extension'];
                                $extension = $fileExtRaw === '' ? 'N/A' : strtoupper($fileExtRaw);
                                $category = preview_category($fileExtRaw);
                            ?>
                            <article class="file-card" data-file-item data-view="grid" data-name="<?= htmlspecialchars(strtolower($fileName)); ?>">
                                <div class="preview">
                                    <?php if ($category === 'image'): ?>
                                        <img class="previewable-image" src="<?= htmlspecialchars(file_public_url($fileName)); ?>" alt="<?= htmlspecialchars($fileName); ?>" data-preview-src="<?= htmlspecialchars(file_public_url($fileName)); ?>" data-preview-alt="<?= htmlspecialchars($fileName); ?>" tabindex="0" role="button" aria-label="Preview gambar <?= htmlspecialchars($fileName); ?>">
                                    <?php else: ?>
                                        <div class="preview-fallback preview-<?= htmlspecialchars($category); ?>">
                                            <div class="preview-fallback-inner">
                                                <span class="ext"><?= htmlspecialchars($extension); ?></span>
                                                <small><?= htmlspecialchars(preview_label($category)); ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h3 class="card-name"><?= htmlspecialchars($fileName); ?></h3>
                                    <p class="card-meta"><?= Formatter::bytes((int) $file['size']); ?> | <?= Formatter::datetime((int) $file['modified']); ?></p>
                                    <p class="card-meta">Type: <?= htmlspecialchars($extension); ?></p>
                                    <?php if (in_array($currentUser['role'], ['superadmin', 'admin'], true)): ?>
                                        <p class="card-meta">Owner: <?= htmlspecialchars((string) ($file['owner_name'] ?? $file['owner_id'] ?? 'legacy')); ?></p>
                                    <?php endif; ?>
                                    <div class="card-actions">
                                        <?php if ($canDownload): ?>
                                            <a class="link-action link-download" href="download.php?file=<?= urlencode($fileName); ?>">Download</a>
                                        <?php endif; ?>
                                        <?php if ($canRename): ?>
                                            <button type="button" class="icon-inline-btn" title="Rename file" aria-label="Rename file" data-open-rename data-file="<?= htmlspecialchars($fileName, ENT_QUOTES); ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M12 20h9"/>
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <form action="delete.php" method="post" onsubmit="return confirm('Hapus file ini dari server?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="file" value="<?= htmlspecialchars($fileName); ?>">
                                                <button class="link-action link-delete" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($canViewAudit): ?>
            <section class="panel" id="audit-panel">
                <div class="panel-head">
                    <div>
                        <h2>Audit Trail</h2>
                        <p>Riwayat aktivitas upload, download, rename, dan delete untuk menjaga workspace transparan.</p>
                    </div>
                    <span class="panel-count"><?= count($events); ?> event</span>
                </div>
                <?php if ($events === []): ?>
                    <div class="empty">Belum ada audit event.</div>
                <?php else: ?>
                    <ul class="audit-list">
                        <?php foreach ($events as $event): ?>
                            <li class="audit-item">
                                <strong><?= htmlspecialchars((string) ($event['action'] ?? 'unknown')); ?></strong>
                                <span> - <?= htmlspecialchars((string) ($event['status'] ?? 'unknown')); ?></span>
                                <div class="audit-meta">
                                    <?= htmlspecialchars((string) ($event['timestamp'] ?? '-')); ?> |
                                    User: <?= htmlspecialchars((string) ($event['user']['name'] ?? $event['context']['user_id'] ?? '-')); ?> |
                                    IP: <?= htmlspecialchars((string) ($event['ip'] ?? '-')); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($canRename): ?>
        <dialog id="renameDialog">
            <div class="dialog-head">
                <h3>Rename File</h3>
            </div>
            <form method="post" action="rename.php">
                <div class="dialog-body">
                    <p class="dialog-meta">File saat ini: <strong id="renameCurrentLabel">-</strong></p>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="current_file" id="renameCurrentFile">
                    <input type="text" class="dialog-input" name="new_file" id="renameNewFile" maxlength="180" required>
                    <div class="dialog-actions">
                        <button class="btn btn-secondary" type="button" id="renameCancelBtn">Batal</button>
                        <button class="btn btn-primary" type="submit">Simpan Nama</button>
                    </div>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <div id="imageLightbox" class="image-lightbox" hidden>
        <div class="image-lightbox-frame">
            <div class="image-lightbox-toolbar">
                <span id="imageLightboxTitle">Preview Gambar</span>
                <button type="button" class="image-lightbox-close" id="imageLightboxClose" aria-label="Tutup preview">&times;</button>
            </div>
            <div class="image-lightbox-view">
                <img id="imageLightboxImg" src="" alt="">
            </div>
        </div>
    </div>

    <script>
        (function () {
            const searchInput = document.getElementById('fileSearch');
            const resultText = document.getElementById('resultText');
            const viewButtons = document.querySelectorAll('[data-view-target]');
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');
            const storageKey = 'ags_repo_view_mode';
            let activeView = 'list';

            function getActiveItems() {
                const selector = '[data-file-item][data-view="' + activeView + '"]';
                return Array.from(document.querySelectorAll(selector));
            }

            function applyFilter() {
                const keyword = String(searchInput ? searchInput.value : '').toLowerCase().trim();
                const allItems = document.querySelectorAll('[data-file-item]');
                allItems.forEach(function (item) {
                    const name = String(item.getAttribute('data-name') || '');
                    item.hidden = !name.includes(keyword);
                });
                updateResultText();
            }

            function updateResultText() {
                if (!resultText) {
                    return;
                }

                const visibleCount = getActiveItems().filter(function (item) {
                    return !item.hidden;
                }).length;
                resultText.textContent = visibleCount + ' file terlihat';
            }

            function setView(mode) {
                activeView = mode === 'grid' ? 'grid' : 'list';

                if (listView) {
                    listView.hidden = activeView !== 'list';
                }

                if (gridView) {
                    gridView.hidden = activeView !== 'grid';
                }

                viewButtons.forEach(function (button) {
                    const isActive = button.getAttribute('data-view-target') === activeView;
                    button.classList.toggle('active', isActive);
                });

                updateResultText();
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            viewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const mode = String(button.getAttribute('data-view-target') || 'list');
                    setView(mode);
                    try {
                        localStorage.setItem(storageKey, mode);
                    } catch (error) {
                        console.warn(error);
                    }
                });
            });

            let initialMode = 'list';
            try {
                const savedMode = localStorage.getItem(storageKey);
                if (savedMode === 'grid') {
                    initialMode = 'grid';
                }
                if (savedMode !== 'grid' && savedMode !== 'list' && window.matchMedia('(max-width: 700px)').matches) {
                    initialMode = 'grid';
                }
            } catch (error) {
                console.warn(error);
            }

            setView(initialMode);
            applyFilter();

            const renameDialog = document.getElementById('renameDialog');
            const renameCurrentLabel = document.getElementById('renameCurrentLabel');
            const renameCurrentFile = document.getElementById('renameCurrentFile');
            const renameNewFile = document.getElementById('renameNewFile');
            const renameCancelBtn = document.getElementById('renameCancelBtn');
            const renameButtons = document.querySelectorAll('[data-open-rename]');

            function openRenameDialog(fileName) {
                if (!renameDialog || !renameCurrentLabel || !renameCurrentFile || !renameNewFile) {
                    return;
                }

                renameCurrentLabel.textContent = fileName;
                renameCurrentFile.value = fileName;
                renameNewFile.value = fileName;
                renameNewFile.focus();
                renameNewFile.select();

                if (typeof renameDialog.showModal === 'function') {
                    renameDialog.showModal();
                    return;
                }

                const promptedName = window.prompt('Masukkan nama baru file:', fileName);
                if (!promptedName || promptedName.trim() === '') {
                    return;
                }
                renameNewFile.value = promptedName.trim();
                renameCurrentFile.form.submit();
            }

            renameButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const fileName = String(button.getAttribute('data-file') || '');
                    if (fileName !== '') {
                        openRenameDialog(fileName);
                    }
                });
            });

            if (renameCancelBtn && renameDialog) {
                renameCancelBtn.addEventListener('click', function () {
                    renameDialog.close();
                });
            }

            const imageLightbox = document.getElementById('imageLightbox');
            const imageLightboxImg = document.getElementById('imageLightboxImg');
            const imageLightboxTitle = document.getElementById('imageLightboxTitle');
            const imageLightboxClose = document.getElementById('imageLightboxClose');
            const previewableImages = document.querySelectorAll('.previewable-image');

            function openImageLightbox(src, alt) {
                if (!imageLightbox || !imageLightboxImg || !src) {
                    return;
                }

                imageLightboxImg.src = src;
                imageLightboxImg.alt = alt || 'Preview gambar';
                if (imageLightboxTitle) {
                    imageLightboxTitle.textContent = alt || 'Preview Gambar';
                }
                imageLightbox.hidden = false;
                document.body.style.overflow = 'hidden';
            }

            function closeImageLightbox() {
                if (!imageLightbox || !imageLightboxImg) {
                    return;
                }

                imageLightbox.hidden = true;
                imageLightboxImg.src = '';
                imageLightboxImg.alt = '';
                document.body.style.overflow = '';
            }

            previewableImages.forEach(function (image) {
                image.addEventListener('click', function () {
                    const src = String(image.getAttribute('data-preview-src') || image.getAttribute('src') || '');
                    const alt = String(image.getAttribute('data-preview-alt') || image.getAttribute('alt') || '');
                    openImageLightbox(src, alt);
                });

                image.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        const src = String(image.getAttribute('data-preview-src') || image.getAttribute('src') || '');
                        const alt = String(image.getAttribute('data-preview-alt') || image.getAttribute('alt') || '');
                        openImageLightbox(src, alt);
                    }
                });
            });

            if (imageLightboxClose) {
                imageLightboxClose.addEventListener('click', closeImageLightbox);
            }

            if (imageLightbox) {
                imageLightbox.addEventListener('click', function (event) {
                    if (event.target === imageLightbox) {
                        closeImageLightbox();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && imageLightbox && !imageLightbox.hidden) {
                    closeImageLightbox();
                }
            });
        })();
    </script>
</body>
</html>
