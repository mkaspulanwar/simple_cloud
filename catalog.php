<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Support\Formatter;

function catalog_public_preview_url(string $fileName): string
{
    return 'preview.php?file=' . rawurlencode($fileName);
}

function catalog_url(int $page, string $query, string $type, string $sort): string
{
    $params = ['page' => max(1, $page)];

    if ($query !== '') {
        $params['q'] = $query;
    }

    if ($type !== 'all') {
        $params['type'] = $type;
    }

    if ($sort !== 'newest') {
        $params['sort'] = $sort;
    }

    return 'catalog.php?' . http_build_query($params);
}

function catalog_clean_title(string $fileName): string
{
    $title = pathinfo($fileName, PATHINFO_FILENAME);
    $title = str_replace(['_', '-'], ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title) ?: $title;

    return trim($title) !== '' ? trim($title) : $fileName;
}

function catalog_icon(string $name): string
{
    $icons = [
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m7 16 3.5-4 2.5 3 2-2 2 3"/><circle cx="16" cy="9" r="1.2"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10"/><path d="m8 10 4 4 4-4"/><path d="M5 19h14"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"/><circle cx="12" cy="12" r="2.4"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'filter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.7-4 4.4-6 8-6s6.3 2 8 6"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'minus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>',
    ];

    return $icons[$name] ?? $icons['image'];
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$allFiles = $ownershipStore->attachOwners($storage->listFiles());
$imageFiles = array_values(array_filter(
    $allFiles,
    static fn (array $file): bool => in_array((string) $file['extension'], ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)
));

$query = trim((string) ($_GET['q'] ?? ''));
$availableTypes = array_values(array_unique(array_map(
    static fn (array $file): string => strtolower((string) $file['extension']),
    $imageFiles
)));
sort($availableTypes);
$type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
$type = $type === 'all' || in_array($type, $availableTypes, true) ? $type : 'all';
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'newest')));
$sort = in_array($sort, ['newest', 'oldest', 'name', 'size'], true) ? $sort : 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$filteredImages = array_values(array_filter(
    $imageFiles,
    static function (array $file) use ($query, $type): bool {
        $matchesQuery = $query === '' || str_contains(strtolower((string) $file['name']), strtolower($query));
        $matchesType = $type === 'all' || strtolower((string) $file['extension']) === $type;

        return $matchesQuery && $matchesType;
    }
));

usort($filteredImages, static function (array $a, array $b) use ($sort): int {
    return match ($sort) {
        'oldest' => (int) $a['modified'] <=> (int) $b['modified'],
        'name' => strcasecmp((string) $a['name'], (string) $b['name']),
        'size' => (int) $b['size'] <=> (int) $a['size'],
        default => (int) $b['modified'] <=> (int) $a['modified'],
    };
});

$totalImages = count($filteredImages);
$totalPages = max(1, (int) ceil($totalImages / $perPage));
$page = min($page, $totalPages);
$catalogImages = array_slice($filteredImages, ($page - 1) * $perPage, $perPage);
$totalSize = array_sum(array_map(static fn (array $file): int => (int) $file['size'], $imageFiles));
$latestModified = $imageFiles === [] ? null : max(array_map(static fn (array $file): int => (int) $file['modified'], $imageFiles));
$appName = (string) app_config('app.name', 'Cloudify');
$currentUser = AuthManager::user();
$loginTarget = $currentUser !== null && AuthManager::can('dashboard') ? 'dashboard.php' : 'login.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog - <?= htmlspecialchars($appName); ?></title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <style>
        :root {
            --ink: #14171f;
            --muted: #68717f;
            --line: #e5e9ef;
            --soft: #f7f9fb;
            --brand: #0f9f8f;
            --brand-dark: #0b6f68;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: #fbfcfd;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
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
            padding: 16px clamp(18px, 4vw, 48px);
            background: rgba(251, 252, 253, 0.9);
            border-bottom: 1px solid rgba(229, 233, 239, 0.9);
            backdrop-filter: blur(18px);
        }

        .brand,
        .nav-actions {
            display: inline-flex;
            align-items: center;
        }

        .brand {
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
            background: var(--ink);
            box-shadow: inset 0 -5px 0 rgba(15, 159, 143, 0.52);
        }

        .nav-actions {
            justify-content: flex-end;
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

        .catalog-hero {
            padding: clamp(46px, 7vw, 88px) clamp(18px, 4vw, 48px) 30px;
            background:
                linear-gradient(90deg, rgba(251, 252, 253, 0.96), rgba(251, 252, 253, 0.8) 58%, rgba(251, 252, 253, 0.96)),
                url("assets/images/hero-background.jpg") center / cover no-repeat;
        }

        .catalog-hero-inner,
        .catalog-shell,
        .footer-inner {
            width: min(100%, 1280px);
            margin: 0 auto;
        }

        .catalog-hero-inner {
            display: grid;
            justify-items: center;
            text-align: center;
        }

        .eyebrow {
            margin: 0 0 10px;
            color: var(--brand-dark);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        h1 {
            max-width: 780px;
            margin: 0 auto;
            font-size: clamp(2.25rem, 5vw, 4.2rem);
            line-height: 1;
            letter-spacing: 0;
        }

        .hero-copy {
            max-width: 680px;
            margin: 16px auto 0;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .catalog-controls {
            width: min(100%, 860px);
            margin: 26px auto 0;
        }

        .catalog-search {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 12px;
            min-height: 64px;
            padding: 0 10px 0 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 16px 34px rgba(20, 23, 31, 0.08);
        }

        .catalog-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .stat-pill {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 13px;
            border: 1px solid rgba(229, 233, 239, 0.92);
            border-radius: 999px;
            color: #3f4855;
            background: rgba(255, 255, 255, 0.86);
            font-size: 0.92rem;
            font-weight: 850;
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.06);
            backdrop-filter: blur(14px);
        }

        .stat-pill svg {
            width: 17px;
            height: 17px;
            color: var(--brand-dark);
        }

        .catalog-search > svg {
            width: 24px;
            height: 24px;
            color: var(--brand-dark);
        }

        .catalog-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            color: var(--ink);
            background: transparent;
            font: inherit;
            font-size: 1rem;
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
        }

        .catalog-filters {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .filter-field {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            border: 1px solid rgba(229, 233, 239, 0.92);
            border-radius: 999px;
            color: #3f4855;
            background: rgba(255, 255, 255, 0.9);
            font-weight: 850;
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.05);
        }

        .filter-field svg {
            width: 17px;
            height: 17px;
            color: var(--brand-dark);
        }

        .filter-field select {
            border: 0;
            outline: 0;
            color: var(--ink);
            background: transparent;
            font: inherit;
            font-weight: 850;
        }

        .reset-link {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--muted);
            background: #ffffff;
            font-weight: 850;
            text-decoration: none;
        }

        .catalog {
            padding: 42px clamp(18px, 4vw, 48px) 0;
        }

        .catalog-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .catalog-head h2 {
            margin: 0;
            font-size: clamp(1.7rem, 3vw, 2.35rem);
            line-height: 1.1;
        }

        .catalog-head p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .result-chip {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 13px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: #46505d;
            background: #ffffff;
            font-weight: 850;
            white-space: nowrap;
        }

        .asset-grid {
            column-count: 4;
            column-gap: clamp(18px, 2.4vw, 30px);
        }

        .asset-item {
            min-width: 0;
            display: inline-block;
            width: 100%;
            margin: 0 0 clamp(18px, 2.4vw, 30px);
            break-inside: avoid;
        }

        .asset-thumb {
            position: relative;
            width: 100%;
            display: block;
            overflow: hidden;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: #e9edf2;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(20, 23, 31, 0.08);
            transition: box-shadow 220ms ease, transform 220ms ease;
        }

        .asset-thumb::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(20, 23, 31, 0) 42%, rgba(20, 23, 31, 0.54));
            opacity: 0;
            transition: opacity 220ms ease;
        }

        .asset-thumb img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 220ms ease;
        }

        .asset-item:hover .asset-thumb img {
            transform: scale(1.03);
        }

        .asset-item:hover .asset-thumb {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(20, 23, 31, 0.14);
        }

        .asset-item:hover .asset-thumb::after,
        .asset-thumb:focus-visible::after {
            opacity: 1;
        }

        .asset-badge {
            position: absolute;
            z-index: 2;
            top: 10px;
            left: 10px;
            min-height: 28px;
            display: inline-flex;
            align-items: center;
            padding: 0 9px;
            border-radius: 999px;
            color: #ffffff;
            background: rgba(20, 23, 31, 0.72);
            font-size: 0.75rem;
            font-weight: 900;
            backdrop-filter: blur(10px);
        }

        .asset-info {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 8px;
            align-items: center;
            padding-top: 11px;
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
            transition: background 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .asset-action:hover,
        .asset-action:focus-visible {
            color: var(--ink);
            background: #dfe6ee;
            transform: translateY(-1px);
        }

        .asset-meta {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.88rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .asset-meta svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            color: var(--brand-dark);
        }

        .asset-uploader {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            color: #46505d;
            font-size: 0.86rem;
            font-weight: 800;
        }

        .asset-uploader svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            color: var(--brand-dark);
        }

        .asset-uploader span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-state {
            min-height: 300px;
            display: grid;
            place-items: center;
            padding: 34px;
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
            margin-top: 38px;
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
            width: min(100% - 24px, 1180px);
            max-height: min(92svh, 860px);
            padding: 0;
            overflow: hidden;
            border: 0;
            border-radius: 8px;
            background: #0f1318;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.46);
        }

        .preview-modal::backdrop {
            background: rgba(9, 11, 15, 0.78);
            backdrop-filter: blur(10px);
        }

        .preview-shell {
            position: relative;
            display: grid;
            grid-template-rows: minmax(0, 1fr) auto;
            max-height: min(92svh, 860px);
        }

        .preview-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            color: #ffffff;
            background: #ffffff;
        }

        .preview-title {
            min-width: 0;
            overflow: hidden;
            margin: 0;
            color: var(--ink);
            font-size: 1.08rem;
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
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 4;
            width: 42px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            color: #ffffff;
            background: rgba(20, 23, 31, 0.62);
            cursor: pointer;
            backdrop-filter: blur(12px);
        }

        .preview-download {
            padding: 0 14px;
            color: #ffffff;
            background: var(--brand);
        }

        .preview-tools {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 4;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            background: rgba(20, 23, 31, 0.62);
            backdrop-filter: blur(12px);
        }

        .preview-zoom-button {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            cursor: pointer;
        }

        .preview-zoom-button:hover,
        .preview-zoom-button:focus-visible {
            background: rgba(255, 255, 255, 0.22);
        }

        .preview-zoom-value {
            min-width: 52px;
            text-align: center;
            font-size: 0.84rem;
            font-weight: 900;
        }

        .preview-stage {
            min-height: min(68svh, 650px);
            display: grid;
            place-items: center;
            overflow: auto;
            padding: clamp(18px, 3vw, 36px);
            background:
                radial-gradient(circle at 20% 10%, rgba(15, 159, 143, 0.18), transparent 32%),
                #0f1318;
        }

        .preview-stage img {
            max-width: 100%;
            max-height: calc(92svh - 150px);
            display: block;
            border-radius: 8px;
            object-fit: contain;
            transform-origin: center center;
            transition: transform 140ms ease;
            cursor: grab;
            user-select: none;
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.42);
        }

        .preview-stage img.is-dragging {
            cursor: grabbing;
            transition: none;
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

        .preview-info {
            min-width: 0;
            display: grid;
            gap: 4px;
        }

        .footer {
            margin-top: 58px;
            padding: 30px clamp(18px, 4vw, 48px);
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
            .asset-grid {
                column-count: 3;
            }
        }

        @media (max-width: 760px) {
            .topbar {
                padding: 12px 14px;
            }

            .brand-name {
                display: none;
            }

            .catalog-search {
                grid-template-columns: auto 1fr;
                padding: 12px 14px;
            }

            .search-button {
                grid-column: 1 / -1;
                width: 100%;
            }

            .catalog-head {
                display: block;
            }

            .result-chip {
                margin-top: 14px;
            }

            .stat-pill {
                width: 100%;
                justify-content: center;
            }

            .catalog-filters {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-field,
            .reset-link {
                width: 100%;
                justify-content: center;
            }

            .asset-grid {
                column-count: 1;
                column-gap: 0;
            }

            .preview-stage {
                min-height: 58svh;
            }

            .preview-foot {
                align-items: stretch;
                flex-direction: column;
            }

            .preview-meta {
                white-space: normal;
            }

            .preview-download {
                width: 100%;
            }

            .footer-inner {
                grid-template-columns: 1fr;
            }

            .footer-links {
                justify-content: flex-start;
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
            <a class="nav-link" href="index.php">Home</a>
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
        <section class="catalog-hero">
            <div class="catalog-hero-inner">
                <p class="eyebrow">Katalog visual</p>
                <h1>Semua gambar dalam satu halaman khusus.</h1>
                <p class="hero-copy">Telusuri koleksi, buka preview, dan unduh aset gambar tanpa masuk ke dashboard.</p>

                <form class="catalog-controls" action="catalog.php" method="get">
                    <div class="catalog-search">
                        <?= catalog_icon('search'); ?>
                        <input type="search" name="q" value="<?= htmlspecialchars($query); ?>" placeholder="Cari nama file atau koleksi..." aria-label="Cari aset visual">
                        <button class="search-button" type="submit">Cari</button>
                    </div>
                    <div class="catalog-filters" aria-label="Filter katalog">
                        <label class="filter-field">
                            <?= catalog_icon('filter'); ?>
                            <span>Format</span>
                            <select name="type" onchange="this.form.submit()">
                                <option value="all"<?= $type === 'all' ? ' selected' : ''; ?>>Semua</option>
                                <?php foreach ($availableTypes as $availableType): ?>
                                    <option value="<?= htmlspecialchars($availableType); ?>"<?= $type === $availableType ? ' selected' : ''; ?>><?= htmlspecialchars(strtoupper($availableType)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="filter-field">
                            <?= catalog_icon('clock'); ?>
                            <span>Urutkan</span>
                            <select name="sort" onchange="this.form.submit()">
                                <option value="newest"<?= $sort === 'newest' ? ' selected' : ''; ?>>Terbaru</option>
                                <option value="oldest"<?= $sort === 'oldest' ? ' selected' : ''; ?>>Terlama</option>
                                <option value="name"<?= $sort === 'name' ? ' selected' : ''; ?>>Nama A-Z</option>
                                <option value="size"<?= $sort === 'size' ? ' selected' : ''; ?>>Ukuran besar</option>
                            </select>
                        </label>
                        <?php if ($query !== '' || $type !== 'all' || $sort !== 'newest'): ?>
                            <a class="reset-link" href="catalog.php">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="catalog-stats" aria-label="Ringkasan katalog">
                    <span class="stat-pill"><?= catalog_icon('image'); ?> <?= count($imageFiles); ?> gambar</span>
                    <span class="stat-pill"><?= catalog_icon('download'); ?> <?= Formatter::bytes((int) $totalSize); ?></span>
                    <span class="stat-pill"><?= catalog_icon('clock'); ?> <?= $latestModified === null ? 'Belum ada unggahan' : Formatter::datetime((int) $latestModified); ?></span>
                </div>
            </div>
        </section>

        <section class="catalog">
            <div class="catalog-shell">
                <div class="catalog-head">
                    <div>
                        <p class="eyebrow">Koleksi terbaru</p>
                        <h2>Katalog gambar</h2>
                        <p><?= $totalImages; ?> aset ditemukan<?= $query !== '' ? ' untuk "' . htmlspecialchars($query) . '"' : ''; ?><?= $type !== 'all' ? ' dengan format ' . htmlspecialchars(strtoupper($type)) : ''; ?>. Halaman <?= $page; ?> dari <?= $totalPages; ?>.</p>
                    </div>
                    <span class="result-chip"><?= catalog_icon('image'); ?> <?= $totalImages; ?> gambar</span>
                </div>

                <?php if ($catalogImages === []): ?>
                    <div class="empty-state">
                        <div>
                            <h3>Tidak ada aset yang cocok.</h3>
                            <p><?= $query === '' ? 'Unggah gambar dari dashboard, lalu katalog akan tampil otomatis di sini.' : 'Coba kata kunci lain atau hapus pencarian untuk melihat semua gambar.'; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="asset-grid">
                        <?php foreach ($catalogImages as $file): ?>
                            <?php
                                $fileName = (string) $file['name'];
                                $title = catalog_clean_title($fileName);
                                $extension = strtoupper((string) $file['extension']);
                                $previewUrl = catalog_public_preview_url($fileName);
                                $downloadUrl = 'download.php?file=' . rawurlencode($fileName) . '&public=1';
                                $uploader = (string) ($file['owner_name'] ?? $file['owner_id'] ?? 'Legacy upload');
                                $meta = $extension . ' | ' . Formatter::bytes((int) $file['size']) . ' | ' . Formatter::datetime((int) $file['modified']) . ' | Upload oleh ' . $uploader;
                            ?>
                            <article class="asset-item">
                                <button
                                    class="asset-thumb"
                                    type="button"
                                    data-preview-trigger
                                    data-preview-src="<?= htmlspecialchars($previewUrl); ?>"
                                    data-preview-title="<?= htmlspecialchars($title); ?>"
                                    data-preview-meta="<?= htmlspecialchars($meta); ?>"
                                    data-preview-download="<?= htmlspecialchars($downloadUrl); ?>"
                                    aria-label="Preview <?= htmlspecialchars($title); ?>"
                                >
                                    <span class="asset-badge"><?= htmlspecialchars($extension); ?></span>
                                    <img src="<?= htmlspecialchars($previewUrl); ?>" alt="<?= htmlspecialchars($title); ?>" loading="lazy">
                                </button>
                                <div class="asset-info">
                                    <button
                                        class="asset-title"
                                        type="button"
                                        data-preview-trigger
                                        data-preview-src="<?= htmlspecialchars($previewUrl); ?>"
                                        data-preview-title="<?= htmlspecialchars($title); ?>"
                                        data-preview-meta="<?= htmlspecialchars($meta); ?>"
                                        data-preview-download="<?= htmlspecialchars($downloadUrl); ?>"
                                    ><?= htmlspecialchars($title); ?></button>
                                    <button
                                        class="asset-action"
                                        type="button"
                                        data-preview-trigger
                                        data-preview-src="<?= htmlspecialchars($previewUrl); ?>"
                                        data-preview-title="<?= htmlspecialchars($title); ?>"
                                        data-preview-meta="<?= htmlspecialchars($meta); ?>"
                                        data-preview-download="<?= htmlspecialchars($downloadUrl); ?>"
                                        title="Preview"
                                        aria-label="Preview <?= htmlspecialchars($title); ?>"
                                    ><?= catalog_icon('eye'); ?></button>
                                    <a class="asset-action" href="<?= htmlspecialchars($downloadUrl); ?>" title="Download"><?= catalog_icon('download'); ?></a>
                                    <div class="asset-meta">
                                        <?= catalog_icon('clock'); ?>
                                        <span><?= htmlspecialchars($extension . ' | ' . Formatter::bytes((int) $file['size']) . ' | ' . Formatter::datetime((int) $file['modified'])); ?></span>
                                    </div>
                                    <div class="asset-uploader">
                                        <?= catalog_icon('user'); ?>
                                        <span>Upload oleh <?= htmlspecialchars($uploader); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Pagination katalog">
                            <?php if ($page > 1): ?>
                                <a class="page-link" href="<?= htmlspecialchars(catalog_url($page - 1, $query, $type, $sort)); ?>">Sebelumnya</a>
                            <?php endif; ?>

                            <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                                <?php if ($number === $page): ?>
                                    <span class="page-current" aria-current="page"><?= $number; ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?= htmlspecialchars(catalog_url($number, $query, $type, $sort)); ?>"><?= $number; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a class="page-link" href="<?= htmlspecialchars(catalog_url($page + 1, $query, $type, $sort)); ?>">Berikutnya</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <dialog class="preview-modal" id="previewModal" aria-labelledby="previewTitle">
        <div class="preview-shell">
            <button class="preview-close" type="button" data-preview-close aria-label="Tutup preview"><?= catalog_icon('x'); ?></button>
            <div class="preview-tools" aria-label="Kontrol zoom preview">
                <button class="preview-zoom-button" type="button" id="previewZoomOut" aria-label="Zoom out"><?= catalog_icon('minus'); ?></button>
                <span class="preview-zoom-value" id="previewZoomValue">100%</span>
                <button class="preview-zoom-button" type="button" id="previewZoomIn" aria-label="Zoom in"><?= catalog_icon('plus'); ?></button>
            </div>
            <div class="preview-stage">
                <img id="previewImage" src="" alt="">
            </div>
            <div class="preview-foot">
                <div class="preview-info">
                    <h3 class="preview-title" id="previewTitle">Preview gambar</h3>
                    <span class="preview-meta" id="previewMeta"></span>
                </div>
                <a class="preview-download" id="previewDownload" href="#" download><?= catalog_icon('download'); ?> Download</a>
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
                <p>Katalog khusus untuk menemukan, melihat, dan mengunduh aset gambar dari satu tempat.</p>
            </div>
            <nav class="footer-links" aria-label="Footer navigation">
                <a href="index.php">Home</a>
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
            const zoomIn = document.getElementById('previewZoomIn');
            const zoomOut = document.getElementById('previewZoomOut');
            const zoomValue = document.getElementById('previewZoomValue');
            const previewStage = document.querySelector('.preview-stage');
            const closeButtons = document.querySelectorAll('[data-preview-close]');
            const triggers = document.querySelectorAll('[data-preview-trigger]');
            let zoomLevel = 1;
            let panX = 0;
            let panY = 0;
            let isDragging = false;
            let dragStartX = 0;
            let dragStartY = 0;
            let dragOriginX = 0;
            let dragOriginY = 0;
            const minZoom = 0.1;
            const maxZoom = 10;

            if (!modal || !image || !title || !meta || !download || !zoomIn || !zoomOut || !zoomValue || !previewStage) {
                return;
            }

            function applyZoom() {
                image.style.transform = `translate(${panX}px, ${panY}px) scale(${zoomLevel})`;
                zoomValue.textContent = `${Math.round(zoomLevel * 100)}%`;
                zoomOut.disabled = zoomLevel <= minZoom;
                zoomIn.disabled = zoomLevel >= maxZoom;
            }

            function resetZoom() {
                zoomLevel = 1;
                panX = 0;
                panY = 0;
                applyZoom();
            }

            function openPreview(trigger) {
                const src = trigger.getAttribute('data-preview-src') || '';
                const previewTitle = trigger.getAttribute('data-preview-title') || 'Preview gambar';
                const previewMeta = trigger.getAttribute('data-preview-meta') || '';
                const downloadUrl = trigger.getAttribute('data-preview-download') || src;

                resetZoom();
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
                resetZoom();
            }

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', () => openPreview(trigger));
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', closePreview);
            });

            zoomIn.addEventListener('click', () => {
                const step = zoomLevel >= 3 ? 0.5 : 0.25;
                zoomLevel = Math.min(maxZoom, Math.round((zoomLevel + step) * 100) / 100);
                applyZoom();
            });

            zoomOut.addEventListener('click', () => {
                const step = zoomLevel > 3 ? 0.5 : 0.25;
                zoomLevel = Math.max(minZoom, Math.round((zoomLevel - step) * 100) / 100);
                applyZoom();
            });

            image.addEventListener('pointerdown', (event) => {
                if (zoomLevel <= 1) {
                    return;
                }

                isDragging = true;
                dragStartX = event.clientX;
                dragStartY = event.clientY;
                dragOriginX = panX;
                dragOriginY = panY;
                image.classList.add('is-dragging');
                image.setPointerCapture(event.pointerId);
            });

            image.addEventListener('pointermove', (event) => {
                if (!isDragging) {
                    return;
                }

                panX = dragOriginX + event.clientX - dragStartX;
                panY = dragOriginY + event.clientY - dragStartY;
                applyZoom();
            });

            function stopDragging(event) {
                if (!isDragging) {
                    return;
                }

                isDragging = false;
                image.classList.remove('is-dragging');
                if (typeof image.releasePointerCapture === 'function') {
                    image.releasePointerCapture(event.pointerId);
                }
            }

            image.addEventListener('pointerup', stopDragging);
            image.addEventListener('pointercancel', stopDragging);
            image.addEventListener('dragstart', (event) => event.preventDefault());

            previewStage.addEventListener('wheel', (event) => {
                if (!modal.open) {
                    return;
                }

                event.preventDefault();
                const direction = event.deltaY < 0 ? 1 : -1;
                const step = zoomLevel >= 3 ? 0.25 : 0.1;
                zoomLevel = Math.min(maxZoom, Math.max(minZoom, Math.round((zoomLevel + direction * step) * 100) / 100));
                applyZoom();
            }, { passive: false });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closePreview();
                }
            });

            applyZoom();
        })();
    </script>
</body>
</html>
