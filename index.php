<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Services\CloudStorageService;
use App\Support\Flash;
use App\Support\Formatter;

function home_public_preview_url(string $fileName): string
{
    return 'preview.php?file=' . rawurlencode($fileName);
}

function home_catalog_url(int $page, string $query): string
{
    $params = ['page' => max(1, $page)];

    if ($query !== '') {
        $params['q'] = $query;
    }

    return 'catalog.php?' . http_build_query($params);
}

function home_clean_title(string $fileName): string
{
    $title = pathinfo($fileName, PATHINFO_FILENAME);
    $title = str_replace(['_', '-'], ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title) ?: $title;

    return trim($title) !== '' ? trim($title) : $fileName;
}

function home_icon(string $name): string
{
    $icons = [
        'sparkles' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.7 4.7L16.5 9 13.7 10.3 12 15l-1.7-4.7L7.5 9l2.8-1.3L12 3Z"/><path d="m18 14 .8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8L18 14Z"/></svg>',
        'presentation' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="12" rx="3"/><path d="M12 16v4"/><path d="m8 20 4-4 4 4"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m7 16 3.5-4 2.5 3 2-2 2 3"/><circle cx="16" cy="9" r="1.2"/></svg>',
        'video' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="11" height="12" rx="3"/><path d="m15 10 5-3v10l-5-3z"/></svg>',
        'doc' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h4"/><path d="M9 13h6M9 17h4"/></svg>',
        'grid' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>',
        'code' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 8-4 4 4 4"/><path d="m15 8 4 4-4 4"/><path d="m13 5-2 14"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="m8 8 4-4 4 4"/><path d="M5 16v3h14v-3"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>',
        'sort' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4v16"/><path d="m5 7 3-3 3 3"/><path d="m16 20V4"/><path d="m13 17 3 3 3-3"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10"/><path d="m8 10 4 4 4-4"/><path d="M5 19h14"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"/><circle cx="12" cy="12" r="2.4"/></svg>',
        'folder' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h6l2 2h8v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
    ];

    return $icons[$name] ?? $icons['image'];
}

$storage = new CloudStorageService(app_config('storage'));
$allFiles = $storage->listFiles();
$imageFiles = array_values(array_filter(
    $allFiles,
    static fn (array $file): bool => in_array((string) $file['extension'], ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)
));

$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$filteredImages = $query === ''
    ? $imageFiles
    : array_values(array_filter(
        $imageFiles,
        static fn (array $file): bool => str_contains(strtolower((string) $file['name']), strtolower($query))
    ));

$totalImages = count($filteredImages);
$totalPages = max(1, (int) ceil($totalImages / $perPage));
$page = min($page, $totalPages);
$catalogImages = array_slice($filteredImages, ($page - 1) * $perPage, $perPage);
$messages = Flash::pull();
$appName = (string) app_config('app.name', 'Cloudify');
$currentUser = AuthManager::user();
$loginTarget = $currentUser !== null && AuthManager::can('dashboard') ? 'dashboard.php' : 'login.php';
$categories = [
    ['label' => 'Semua', 'icon' => 'grid'],
    ['label' => 'Inspirasi', 'icon' => 'sparkles'],
    ['label' => 'Presentasi', 'icon' => 'presentation'],
    ['label' => 'Gambar', 'icon' => 'image'],
    ['label' => 'Video', 'icon' => 'video'],
    ['label' => 'Dokumen', 'icon' => 'doc'],
    ['label' => 'Kode', 'icon' => 'code'],
    ['label' => 'Unggahan', 'icon' => 'upload'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName); ?> - Katalog Visual</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <style>
        :root {
            --ink: #14171f;
            --muted: #6a7280;
            --subtle: #8b94a3;
            --line: #e6eaf0;
            --surface: #ffffff;
            --soft: #f6f8fb;
            --wash: #eef6f5;
            --brand: #0f9f8f;
            --brand-dark: #0b6f68;
            --indigo: #405de6;
            --accent: #f1a45b;
            --shadow: 0 20px 45px rgba(20, 23, 31, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: #fbfcfd;
        }

        a {
            color: inherit;
        }

        svg {
            width: 1.15em;
            height: 1.15em;
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
            background: rgba(251, 252, 253, 0.86);
            border-bottom: 1px solid rgba(230, 234, 240, 0.88);
            backdrop-filter: blur(18px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
            background: #14171f;
            box-shadow: inset 0 -5px 0 rgba(15, 159, 143, 0.52);
        }

        .brand-name {
            font-size: 1.05rem;
        }

        .nav-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link,
        .nav-button {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 15px;
            font-size: 0.92rem;
            font-weight: 800;
            text-decoration: none;
            transition: background 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .nav-link {
            color: var(--muted);
        }

        .nav-link:hover {
            color: var(--ink);
            background: #eef2f6;
        }

        .nav-button {
            color: #ffffff;
            background: var(--ink);
        }

        .nav-button:hover,
        .search-button:hover,
        .category-tile:hover,
        .asset-action:hover,
        .page-link:hover {
            transform: translateY(-1px);
        }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: calc(100svh - 75px);
            display: grid;
            align-items: center;
            padding: 86px clamp(20px, 4vw, 48px) 70px;
            color: #ffffff;
            text-align: center;
            background:
                linear-gradient(180deg, rgba(20, 23, 31, 0.58), rgba(20, 23, 31, 0.72)),
                url("assets/images/hero-background.jpg") center / cover no-repeat;
        }

        .hero-inner,
        .content-inner,
        .footer-inner {
            width: min(100%, 1280px);
            margin: 0 auto;
        }

        .hero-grid {
            display: grid;
            justify-items: center;
            text-align: center;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: var(--brand-dark);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 auto;
            max-width: 820px;
            font-size: clamp(2.45rem, 5.3vw, 4.9rem);
            line-height: 0.98;
            letter-spacing: 0;
        }

        .hero .eyebrow {
            color: #cffff8;
            text-shadow: 0 1px 18px rgba(0, 0, 0, 0.32);
        }

        .hero-copy {
            max-width: 690px;
            margin: 18px auto 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: clamp(1rem, 1.5vw, 1.14rem);
            line-height: 1.72;
            text-shadow: 0 1px 18px rgba(0, 0, 0, 0.32);
        }

        .search-panel {
            margin: 28px auto 0;
            max-width: 800px;
        }

        .search-form {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 12px;
            min-height: 66px;
            padding: 0 10px 0 20px;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .search-form > svg {
            width: 24px;
            height: 24px;
            color: var(--brand-dark);
        }

        .search-form input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            color: var(--ink);
            background: transparent;
            font: inherit;
            font-size: 1rem;
        }

        .search-form input::placeholder {
            color: #8b94a3;
        }

        .search-button {
            min-height: 46px;
            border: 0;
            border-radius: 8px;
            padding: 0 18px;
            color: #ffffff;
            background: var(--brand);
            cursor: pointer;
            font: inherit;
            font-weight: 900;
            transition: transform 160ms ease, background 160ms ease;
        }

        .search-button:hover {
            background: var(--brand-dark);
        }

        .filter-row {
            display: flex;
            justify-content: center;
            gap: 9px;
            flex-wrap: wrap;
            margin-top: 13px;
        }

        .filter-chip {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 12px;
            color: #49505c;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 760;
        }

        .filter-chip svg {
            width: 15px;
            height: 15px;
        }

        .categories {
            padding: 22px clamp(20px, 4vw, 48px) 10px;
        }

        .category-strip {
            width: min(100%, 1280px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(8, minmax(110px, 1fr));
            gap: 10px;
        }

        .category-tile {
            min-height: 96px;
            display: grid;
            align-content: center;
            justify-items: start;
            gap: 10px;
            padding: 14px;
            color: var(--ink);
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 8px;
            text-decoration: none;
            transition: transform 160ms ease, border 160ms ease, box-shadow 160ms ease;
        }

        .category-tile:hover {
            border-color: #cdd8e5;
            box-shadow: 0 12px 24px rgba(20, 23, 31, 0.06);
        }

        .category-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: var(--brand-dark);
            background: var(--wash);
        }

        .category-icon svg {
            width: 20px;
            height: 20px;
        }

        .category-tile span:last-child {
            color: #3d4450;
            font-size: 0.9rem;
            font-weight: 850;
        }

        .content {
            padding: 42px clamp(20px, 4vw, 48px) 0;
        }

        .content-inner {
            padding: 0;
        }

        .flash {
            margin: 0 0 16px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-left: 4px solid var(--brand);
            border-radius: 8px;
            background: #ffffff;
        }

        .flash p {
            margin: 0;
            font-weight: 850;
        }

        .flash small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 26px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .section-head h2 {
            margin: 0;
            font-size: clamp(1.75rem, 3vw, 2.35rem);
            line-height: 1.1;
            letter-spacing: 0;
        }

        .section-head p {
            margin: 8px 0 0;
            max-width: 660px;
            color: var(--muted);
            line-height: 1.62;
        }

        .catalog-tools {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tool-pill,
        .tool-icon {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #434a55;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-weight: 800;
            text-decoration: none;
        }

        .tool-pill {
            padding: 0 13px;
        }

        .catalog-cta {
            color: #ffffff;
            background: var(--ink);
            border-color: var(--ink);
        }

        .tool-icon {
            width: 42px;
        }

        .asset-grid {
            column-count: 3;
            column-gap: 22px;
        }

        .asset-card {
            min-width: 0;
            overflow: hidden;
            display: inline-block;
            width: 100%;
            margin: 0 0 22px;
            break-inside: avoid;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.05);
            transition: transform 180ms ease, box-shadow 180ms ease, border 180ms ease;
        }

        .asset-card:hover {
            transform: translateY(-2px);
            border-color: #cfd8e3;
            box-shadow: 0 18px 34px rgba(20, 23, 31, 0.09);
        }

        .asset-thumb {
            position: relative;
            display: block;
            width: 100%;
            overflow: hidden;
            border: 0;
            border-bottom: 1px solid var(--line);
            border-radius: 0;
            background: #edf0f4;
            cursor: pointer;
            padding: 0;
        }

        .asset-thumb img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 220ms ease;
        }

        .asset-card:hover .asset-thumb img {
            transform: scale(1.025);
        }

        .asset-thumb::after {
            content: "Preview";
            position: absolute;
            right: 10px;
            bottom: 10px;
            min-height: 30px;
            display: inline-flex;
            align-items: center;
            padding: 0 10px;
            border-radius: 999px;
            color: #ffffff;
            background: rgba(20, 23, 31, 0.72);
            font-size: 0.78rem;
            font-weight: 900;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 180ms ease, transform 180ms ease;
            backdrop-filter: blur(10px);
        }

        .asset-card:hover .asset-thumb::after,
        .asset-thumb:focus-visible::after {
            opacity: 1;
            transform: translateY(0);
        }

        .asset-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            color: #ffffff;
            background: rgba(20, 23, 31, 0.72);
            font-size: 0.75rem;
            font-weight: 900;
            backdrop-filter: blur(10px);
        }

        .asset-body {
            display: grid;
            gap: 8px;
            padding: 14px;
        }

        .asset-title-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 7px;
        }

        .asset-title {
            min-width: 0;
            overflow: hidden;
            padding: 0;
            border: 0;
            color: var(--ink);
            background: transparent;
            cursor: pointer;
            font: inherit;
            font-weight: 900;
            font-size: 1rem;
            line-height: 1.35;
            text-decoration: none;
            text-align: left;
            text-overflow: ellipsis;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .asset-title:hover,
        .asset-title:focus-visible {
            color: var(--brand-dark);
        }

        .asset-action {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            color: #46505d;
            background: #eef2f6;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
            transition: transform 160ms ease, background 160ms ease;
        }

        .asset-action:hover {
            background: #e3e8ef;
        }

        .asset-action svg {
            width: 17px;
            height: 17px;
        }

        .asset-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.45;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .asset-meta svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            color: var(--brand-dark);
        }

        .empty-state {
            min-height: 280px;
            display: grid;
            place-items: center;
            padding: 28px;
            text-align: center;
            border: 1px dashed #cfd6df;
            border-radius: 8px;
            background: #ffffff;
        }

        .empty-state h3 {
            margin: 0;
            font-size: 1.35rem;
        }

        .empty-state p {
            max-width: 520px;
            margin: 9px auto 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 34px;
        }

        .home-catalog-more {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .page-link,
        .page-current {
            min-width: 42px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0 13px;
            font-weight: 900;
            text-decoration: none;
            border: 1px solid var(--line);
            background: #ffffff;
            transition: transform 160ms ease, background 160ms ease;
        }

        .page-link {
            color: var(--muted);
        }

        .page-current {
            color: #ffffff;
            border-color: var(--brand);
            background: var(--brand);
        }

        .preview-modal {
            width: min(100% - 28px, 1040px);
            max-height: min(86svh, 760px);
            padding: 0;
            overflow: hidden;
            border: 0;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.36);
        }

        .preview-modal::backdrop {
            background: rgba(20, 23, 31, 0.72);
            backdrop-filter: blur(8px);
        }

        .preview-shell {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            max-height: min(86svh, 760px);
        }

        .preview-head,
        .preview-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
        }

        .preview-foot {
            border-top: 1px solid var(--line);
            border-bottom: 0;
        }

        .preview-title {
            min-width: 0;
            overflow: hidden;
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .preview-close,
        .preview-download {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            font: inherit;
            font-weight: 900;
            text-decoration: none;
        }

        .preview-close {
            width: 40px;
            border: 1px solid var(--line);
            color: var(--ink);
            background: #ffffff;
            cursor: pointer;
        }

        .preview-download {
            padding: 0 14px;
            color: #ffffff;
            background: var(--brand);
        }

        .preview-stage {
            min-height: 280px;
            display: grid;
            place-items: center;
            overflow: auto;
            padding: clamp(14px, 2vw, 22px);
            background: #101318;
        }

        .preview-stage img {
            max-width: 100%;
            max-height: calc(86svh - 150px);
            display: block;
            border-radius: 8px;
            object-fit: contain;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.28);
        }

        .preview-meta {
            min-width: 0;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .footer {
            margin-top: 58px;
            padding: 30px clamp(20px, 4vw, 48px);
            color: #dfe5ed;
            background: var(--ink);
        }

        .footer-inner {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 24px;
            align-items: center;
        }

        .footer .brand {
            color: #ffffff;
        }

        .footer .brand-mark {
            color: var(--ink);
            background: #ffffff;
        }

        .footer p {
            max-width: 580px;
            margin: 10px 0 0;
            color: #aeb8c4;
            line-height: 1.62;
        }

        .footer-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .footer-links a {
            padding: 9px 11px;
            border-radius: 8px;
            color: #dfe5ed;
            text-decoration: none;
            font-weight: 800;
        }

        .footer-links a:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 1040px) {
            .footer-inner {
                grid-template-columns: 1fr;
            }

            .asset-grid {
                column-count: 2;
            }

            .category-strip {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .section-head {
                display: block;
            }

            .catalog-tools {
                justify-content: flex-start;
                margin-top: 14px;
            }

            .footer-links {
                justify-content: flex-start;
            }
        }

        @media (max-width: 640px) {
            .topbar {
                padding: 12px 14px;
            }

            .nav-link {
                display: none;
            }

            .brand-name {
                display: none;
            }

            .hero,
            .categories,
            .content {
                padding-left: 14px;
                padding-right: 14px;
            }

            .hero {
                min-height: calc(100svh - 63px);
                padding-top: 48px;
                padding-bottom: 44px;
            }

            .hero-grid > *,
            .search-panel,
            .search-form,
            .filter-row {
                min-width: 0;
                max-width: 100%;
                width: 100%;
            }

            .search-form {
                grid-template-columns: auto 1fr;
                padding: 12px 14px;
            }

            .search-button {
                grid-column: 1 / -1;
                width: 100%;
            }

            .filter-row {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 2px;
            }

            .filter-chip {
                flex: 0 0 auto;
            }

            .category-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .asset-grid {
                column-count: 1;
                column-gap: 0;
            }

            .content-inner {
                padding: 16px;
            }

            .preview-foot {
                align-items: stretch;
                flex-direction: column;
            }

            .preview-download {
                width: 100%;
            }

            .footer {
                padding: 28px 14px;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="<?= htmlspecialchars($appName); ?>">
            <span class="brand-mark">C</span>
            <span class="brand-name"><?= htmlspecialchars($appName); ?></span>
        </a>
        <nav class="nav-actions" aria-label="Navigasi utama">
            <a class="nav-link" href="catalog.php">Katalog</a>
            <?php if ($currentUser !== null && AuthManager::can('dashboard')): ?>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-button" href="logout.php">Keluar</a>
            <?php elseif ($currentUser !== null): ?>
                <a class="nav-button" href="logout.php">Keluar</a>
            <?php else: ?>
                <a class="nav-button" href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-inner hero-grid">
                <div>
                    <p class="eyebrow">Katalog visual Cloudify</p>
                    <h1>Temukan aset visual lebih cepat.</h1>
                    <p class="hero-copy">Cari, lihat, dan unduh koleksi gambar dari satu halaman yang ringan.</p>

                    <div class="search-panel">
                        <form class="search-form" action="catalog.php" method="get">
                            <?= home_icon('search'); ?>
                            <input type="search" name="q" value="<?= htmlspecialchars($query); ?>" placeholder="Cari nama file, koleksi, atau aset..." aria-label="Cari aset visual">
                            <button class="search-button" type="submit">Cari</button>
                        </form>
                        <div class="filter-row" aria-label="Filter katalog">
                            <span class="filter-chip"><?= home_icon('folder'); ?> Semua koleksi</span>
                            <span class="filter-chip"><?= home_icon('image'); ?> Gambar</span>
                            <span class="filter-chip"><?= home_icon('clock'); ?> Terbaru</span>
                            <span class="filter-chip"><?= home_icon('sparkles'); ?> Kurasi</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section class="categories" aria-label="Kategori aset">
            <div class="category-strip">
                <?php foreach ($categories as $category): ?>
                    <a class="category-tile" href="catalog.php">
                        <span class="category-icon"><?= home_icon((string) $category['icon']); ?></span>
                        <span><?= htmlspecialchars($category['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="content" id="catalog">
            <div class="content-inner">
                <?php foreach ($messages as $message): ?>
                    <section class="flash <?= htmlspecialchars((string) ($message['type'] ?? '')); ?>">
                        <p><?= htmlspecialchars((string) ($message['title'] ?? 'Informasi')); ?></p>
                        <?php if (($message['description'] ?? '') !== ''): ?>
                            <small><?= htmlspecialchars((string) $message['description']); ?></small>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <div class="section-head">
                    <div>
                        <p class="eyebrow">Koleksi terbaru</p>
                        <h2>Cuplikan katalog</h2>
                        <p>Preview aset terbaru dari <?= $totalImages; ?> gambar tersedia. Buka halaman katalog untuk melihat semuanya.</p>
                    </div>
                    <div class="catalog-tools" aria-label="Kontrol katalog">
                        <a class="tool-pill catalog-cta" href="catalog.php">Lihat katalog lengkap</a>
                    </div>
                </div>

                <?php if ($imageFiles === []): ?>
                    <div class="empty-state">
                        <div>
                            <h3>Belum ada aset gambar.</h3>
                            <p>Unggah gambar dari dashboard, lalu cuplikan katalog akan tampil otomatis di halaman utama.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="asset-grid">
                        <?php foreach (array_slice($imageFiles, 0, 9) as $file): ?>
                            <?php
                                $fileName = (string) $file['name'];
                                $title = home_clean_title($fileName);
                                $extension = strtoupper((string) $file['extension']);
                            ?>
                            <article class="asset-card">
                                <button
                                    class="asset-thumb"
                                    type="button"
                                    data-preview-trigger
                                    data-preview-src="<?= htmlspecialchars(home_public_preview_url($fileName)); ?>"
                                    data-preview-title="<?= htmlspecialchars($title); ?>"
                                    data-preview-meta="<?= htmlspecialchars($extension . ' | ' . Formatter::bytes((int) $file['size']) . ' | ' . Formatter::datetime((int) $file['modified'])); ?>"
                                    data-preview-download="download.php?file=<?= rawurlencode($fileName); ?>&public=1"
                                    aria-label="Preview <?= htmlspecialchars($title); ?>"
                                >
                                    <span class="asset-badge"><?= htmlspecialchars($extension); ?></span>
                                    <img src="<?= htmlspecialchars(home_public_preview_url($fileName)); ?>" alt="<?= htmlspecialchars($title); ?>" loading="lazy">
                                </button>
                                <div class="asset-body">
                                    <div class="asset-title-row">
                                        <button
                                            class="asset-title"
                                            type="button"
                                            data-preview-trigger
                                            data-preview-src="<?= htmlspecialchars(home_public_preview_url($fileName)); ?>"
                                            data-preview-title="<?= htmlspecialchars($title); ?>"
                                            data-preview-meta="<?= htmlspecialchars($extension . ' | ' . Formatter::bytes((int) $file['size']) . ' | ' . Formatter::datetime((int) $file['modified'])); ?>"
                                            data-preview-download="download.php?file=<?= rawurlencode($fileName); ?>&public=1"
                                        ><?= htmlspecialchars($title); ?></button>
                                        <button
                                            class="asset-action"
                                            type="button"
                                            data-preview-trigger
                                            data-preview-src="<?= htmlspecialchars(home_public_preview_url($fileName)); ?>"
                                            data-preview-title="<?= htmlspecialchars($title); ?>"
                                            data-preview-meta="<?= htmlspecialchars($extension . ' | ' . Formatter::bytes((int) $file['size']) . ' | ' . Formatter::datetime((int) $file['modified'])); ?>"
                                            data-preview-download="download.php?file=<?= rawurlencode($fileName); ?>&public=1"
                                            title="Preview"
                                            aria-label="Preview <?= htmlspecialchars($title); ?>"
                                        ><?= home_icon('eye'); ?></button>
                                        <a class="asset-action" href="download.php?file=<?= rawurlencode($fileName); ?>&public=1" title="Download"><?= home_icon('download'); ?></a>
                                    </div>
                                    <div class="asset-meta">
                                        <?= home_icon('clock'); ?>
                                        <span><?= Formatter::bytes((int) $file['size']); ?> &bull; <?= Formatter::datetime((int) $file['modified']); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="home-catalog-more">
                        <a class="page-link" href="catalog.php">Lihat semua gambar</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <dialog class="preview-modal" id="previewModal" aria-labelledby="previewTitle">
        <div class="preview-shell">
            <div class="preview-head">
                <h3 class="preview-title" id="previewTitle">Preview gambar</h3>
                <button class="preview-close" type="button" data-preview-close aria-label="Tutup preview"><?= home_icon('x'); ?></button>
            </div>
            <div class="preview-stage">
                <img id="previewImage" src="" alt="">
            </div>
            <div class="preview-foot">
                <span class="preview-meta" id="previewMeta"></span>
                <a class="preview-download" id="previewDownload" href="#" download><?= home_icon('download'); ?> Download</a>
            </div>
        </div>
    </dialog>

    <footer class="footer">
        <div class="footer-inner">
            <div>
                <a class="brand" href="index.php">
                    <span class="brand-mark">C</span>
                    <span class="brand-name"><?= htmlspecialchars($appName); ?></span>
                </a>
                <p>Katalog visual minimalis untuk menemukan, melihat, dan mengunduh aset gambar dari satu tempat.</p>
            </div>
            <nav class="footer-links" aria-label="Footer navigation">
                <a href="catalog.php">Katalog</a>
                <a href="<?= htmlspecialchars($loginTarget); ?>"><?= $currentUser === null ? 'Login' : 'Workspace'; ?></a>
            </nav>
        </div>
    </footer>
    <script>
        (() => {
            const modal = document.getElementById('previewModal');
            const image = document.getElementById('previewImage');
            const title = document.getElementById('previewTitle');
            const meta = document.getElementById('previewMeta');
            const download = document.getElementById('previewDownload');
            const closeButtons = document.querySelectorAll('[data-preview-close]');
            const triggers = document.querySelectorAll('[data-preview-trigger]');

            if (!modal || !image || !title || !meta || !download) {
                return;
            }

            function openPreview(trigger) {
                const src = trigger.getAttribute('data-preview-src') || '';
                const previewTitle = trigger.getAttribute('data-preview-title') || 'Preview gambar';
                const previewMeta = trigger.getAttribute('data-preview-meta') || '';
                const downloadUrl = trigger.getAttribute('data-preview-download') || src;

                image.src = src;
                image.alt = previewTitle;
                title.textContent = previewTitle;
                meta.textContent = previewMeta;
                download.href = downloadUrl;

                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                    return;
                }

                modal.setAttribute('open', '');
            }

            function closePreview() {
                if (typeof modal.close === 'function') {
                    modal.close();
                } else {
                    modal.removeAttribute('open');
                }
                image.removeAttribute('src');
                image.alt = '';
            }

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', () => openPreview(trigger));
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', closePreview);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closePreview();
                }
            });
        })();
    </script>
</body>
</html>
