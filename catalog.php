<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
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

$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$imageFiles = $ownershipStore->listPublicImageFiles();

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
$heroSlides = array_slice($imageFiles, 0, 5);
if ($heroSlides === []) {
    $heroSlides = [[
        'name' => 'Koleksi visual pilihan Cloudify',
        'url' => 'assets/images/hero-background.jpg',
        'title' => 'Koleksi Visual Pilihan Cloudify',
    ]];
} else {
    $heroSlides = array_map(
        static fn (array $file): array => [
            'name' => (string) $file['name'],
            'url' => catalog_public_preview_url((string) $file['name']),
            'title' => ucwords(catalog_clean_title((string) $file['name'])),
        ],
        $heroSlides
    );
}
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
    <link rel="icon" href="favicon.png" type="image/png">
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
            padding: 12px clamp(18px, 4vw, 48px);
            background: rgba(251, 252, 253, 0.84);
            border-bottom: 1px solid rgba(229, 233, 239, 0.86);
            backdrop-filter: blur(20px);
        }

        .topbar-inner {
            width: min(100%, 1280px);
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 0 auto;
        }

        .brand {
            min-width: max-content;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            font-weight: 900;
            text-decoration: none;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #ffffff;
            background: linear-gradient(145deg, #14171f, #27303d);
            box-shadow: inset 0 -5px 0 rgba(15, 159, 143, 0.56), 0 12px 26px rgba(20, 23, 31, 0.18);
        }

        .brand-name {
            font-size: 1.08rem;
        }

        .nav-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            padding: 5px;
            border: 1px solid rgba(229, 234, 240, 0.95);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.76);
            box-shadow: 0 16px 40px rgba(20, 23, 31, 0.08);
        }

        .nav-link,
        .nav-button {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 16px;
            font-size: 0.9rem;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
            transition: background 160ms ease, color 160ms ease, transform 160ms ease, box-shadow 160ms ease;
        }

        .nav-link {
            color: #4d5664;
        }

        .nav-link:hover {
            color: var(--ink);
            background: #eef3f7;
        }

        .nav-button {
            color: #ffffff;
            background: var(--ink);
            box-shadow: 0 10px 24px rgba(20, 23, 31, 0.18);
        }

        .catalog-hero {
            overflow: hidden;
            padding: 0 0 30px;
            background: #f6f5f1;
        }

        .catalog-shell,
        .footer-inner {
            width: min(100%, 1280px);
            margin: 0 auto;
        }

        .catalog-hero-inner {
            position: relative;
            display: grid;
            justify-items: center;
            width: 100%;
            text-align: center;
        }

        .catalog-spotlight {
            position: relative;
            width: 100%;
            min-height: clamp(460px, calc(100svh - 160px), 660px);
            display: grid;
            align-items: end;
            overflow: hidden;
            margin-top: 0;
            border-radius: 0;
            color: #ffffff;
            background: #101318;
            box-shadow: none;
        }

        .catalog-spotlight::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(180deg, rgba(16, 19, 24, 0.46) 0%, rgba(16, 19, 24, 0.1) 34%, rgba(16, 19, 24, 0.78) 100%),
                linear-gradient(90deg, rgba(16, 19, 24, 0.3), transparent 28%, transparent 72%, rgba(16, 19, 24, 0.3));
            pointer-events: none;
        }

        .spotlight-slide {
            position: absolute;
            inset: 0;
            z-index: 0;
            background-position: center;
            background-size: cover;
            opacity: 0;
            transform: scale(1.035);
            transition: opacity 700ms ease, transform 3400ms ease;
        }

        .spotlight-slide.is-active {
            opacity: 1;
            transform: scale(1);
        }

        .catalog-spotlight-content {
            position: relative;
            z-index: 2;
            display: grid;
            justify-items: center;
            gap: 8px;
            width: min(100%, 1180px);
            margin: 0 auto;
            padding: clamp(118px, 13vw, 170px) clamp(28px, 5vw, 72px) clamp(68px, 8vw, 98px);
            text-shadow: 0 2px 18px rgba(0, 0, 0, 0.38);
        }

        .spotlight-kicker {
            margin: 0;
            font-size: clamp(1rem, 1.6vw, 1.25rem);
            font-weight: 760;
        }

        .spotlight-title {
            max-width: 780px;
            margin: 0;
            font-size: clamp(2.15rem, 5vw, 4.35rem);
            line-height: 0.98;
            letter-spacing: 0;
        }

        .spotlight-source {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-weight: 900;
        }

        .spotlight-source-mark {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            color: #ffffff;
            background: #d71f2b;
            text-shadow: none;
        }

        .spotlight-dots {
            position: absolute;
            right: 0;
            bottom: 30px;
            left: 0;
            z-index: 3;
            display: flex;
            justify-content: center;
            gap: 8px;
            pointer-events: none;
        }

        .spotlight-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.48);
        }

        .spotlight-dot.is-active {
            background: #ffffff;
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
            position: relative;
            z-index: 2;
            width: min(100%, 1020px);
            margin: 0 auto;
        }

        .catalog-hero-inner > .catalog-controls:first-child {
            position: absolute;
            top: 28px;
            right: 50%;
            left: auto;
            width: min(100% - 48px, 960px);
            margin: 0;
            transform: translateX(50%);
        }

        .catalog-refine {
            width: min(100%, 680px);
            margin-top: -24px;
        }

        .catalog-search {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 14px;
            min-height: 70px;
            padding: 0 12px 0 22px;
            border: 1px solid rgba(229, 233, 239, 0.9);
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 18px 42px rgba(20, 23, 31, 0.12);
        }

        .catalog-search > svg {
            width: 25px;
            height: 25px;
            color: #242832;
        }

        .catalog-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            color: var(--ink);
            background: transparent;
            font: inherit;
            font-size: 1.08rem;
        }

        .catalog-search input::placeholder {
            color: #737b87;
        }

        .search-button {
            min-height: 46px;
            border: 0;
            border-radius: 16px;
            padding: 0 20px;
            color: #ffffff;
            background: var(--ink);
            cursor: pointer;
            font: inherit;
            font-weight: 900;
        }

        .catalog-filters {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 0;
            padding: 8px;
            border: 1px solid rgba(229, 233, 239, 0.9);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 18px 40px rgba(20, 23, 31, 0.12);
            backdrop-filter: blur(16px);
        }

        .filter-field {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            border: 0;
            border-radius: 16px;
            color: #3f4855;
            background: #f5f7f9;
            font-weight: 850;
            box-shadow: none;
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
            border: 0;
            border-radius: 16px;
            color: var(--muted);
            background: #f5f7f9;
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
            position: relative;
            overflow: hidden;
            margin-top: 70px;
            padding: 56px clamp(18px, 4vw, 48px) 24px;
            color: #edf3f7;
            background:
                radial-gradient(circle at 12% 8%, rgba(15, 159, 143, 0.2), transparent 32%),
                linear-gradient(145deg, #101318, #18202b 56%, #101318);
        }

        .footer-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(260px, 1.35fr) repeat(3, minmax(150px, 0.55fr));
            gap: clamp(24px, 4vw, 58px);
            align-items: start;
        }

        .footer .brand {
            color: #ffffff;
        }

        .footer .brand-mark {
            color: var(--ink);
            background: #ffffff;
            box-shadow: inset 0 -5px 0 rgba(15, 159, 143, 0.45);
        }

        .footer p {
            max-width: 470px;
            margin: 14px 0 0;
            color: #aeb8c4;
            line-height: 1.7;
        }

        .footer-title {
            margin: 4px 0 14px;
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .footer-links,
        .footer-social {
            display: grid;
            gap: 9px;
        }

        .footer-links a,
        .footer-social a {
            width: fit-content;
            color: #c5ced8;
            text-decoration: none;
            font-weight: 780;
            line-height: 1.45;
            transition: color 160ms ease, transform 160ms ease;
        }

        .footer-links a:hover,
        .footer-social a:hover {
            color: #ffffff;
            transform: translateX(2px);
        }

        .footer-social a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .social-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--brand);
            box-shadow: 0 0 0 5px rgba(15, 159, 143, 0.12);
        }

        .footer-bottom {
            position: relative;
            z-index: 1;
            width: min(100%, 1280px);
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin: 48px auto 0;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #8f9aaa;
            font-size: 0.9rem;
            font-weight: 760;
        }

        .footer-wordmark {
            position: relative;
            z-index: 0;
            width: min(100%, 1280px);
            margin: 18px auto -38px;
            color: rgba(255, 255, 255, 0.045);
            font-size: clamp(3.4rem, 13vw, 11rem);
            font-weight: 950;
            line-height: 0.8;
            pointer-events: none;
        }

        @media (max-width: 1040px) {
            .footer-inner {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .asset-grid {
                column-count: 3;
            }
        }

        @media (max-width: 760px) {
            .topbar {
                padding: 10px 14px;
            }

            .topbar-inner {
                min-height: 48px;
                align-items: center;
                flex-direction: row;
                gap: 12px;
            }

            .nav-actions {
                width: auto;
                max-width: calc(100vw - 82px);
                margin-left: auto;
                justify-content: flex-end;
                overflow-x: auto;
                border-radius: 999px;
                scrollbar-width: none;
            }

            .nav-actions::-webkit-scrollbar {
                display: none;
            }

            .nav-link,
            .nav-button {
                min-height: 38px;
                padding: 0 13px;
                font-size: 0.84rem;
            }

            .brand-name {
                display: none;
            }

            .catalog-hero {
                overflow-x: clip;
                padding: 0 0 24px;
            }

            .catalog-controls {
                width: min(100% - 28px, 520px);
            }

            .catalog-hero-inner > .catalog-controls:first-child {
                top: 16px;
                right: 14px;
                left: 14px;
                width: auto;
                transform: none;
            }

            .catalog-search {
                grid-template-columns: auto 1fr;
                gap: 10px;
                min-height: 62px;
                padding: 0 18px;
                border-radius: 18px;
            }

            .catalog-search > svg {
                width: 22px;
                height: 22px;
            }

            .catalog-search input {
                font-size: 1.04rem;
            }

            .search-button {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                padding: 0;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
            }

            .catalog-spotlight {
                min-height: 0;
                aspect-ratio: 0.82;
                width: 100%;
                max-width: 100%;
                margin-top: 0;
                margin-right: 0;
                margin-left: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .catalog-spotlight::after {
                background: linear-gradient(180deg, rgba(16, 19, 24, 0.34) 0%, rgba(16, 19, 24, 0.08) 33%, rgba(16, 19, 24, 0.78) 100%);
            }

            .catalog-spotlight-content {
                gap: 7px;
                padding: 112px 22px 48px;
            }

            .spotlight-kicker {
                font-size: 1rem;
            }

            .spotlight-title {
                max-width: 340px;
                font-size: clamp(1.8rem, 9vw, 2.45rem);
                line-height: 1.03;
            }

            .spotlight-source {
                margin-top: 8px;
                font-size: 0.9rem;
            }

            .spotlight-source-mark {
                width: 27px;
                height: 27px;
            }

            .spotlight-dots {
                bottom: 18px;
            }

            .catalog-refine {
                width: min(100% - 28px, 520px);
                margin-top: -22px;
            }

            .catalog-head {
                display: block;
            }

            .catalog-filters {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-top: 0;
                padding: 7px;
                border-radius: 18px;
            }

            .filter-field,
            .reset-link {
                width: 100%;
                justify-content: center;
                min-height: 42px;
                padding: 0 10px;
                font-size: 0.86rem;
                border-radius: 13px;
            }

            .reset-link {
                grid-column: 1 / -1;
            }

            .catalog {
                padding-top: 30px;
            }

            .asset-grid {
                column-count: 2;
                column-gap: 10px;
            }

            .asset-item {
                margin-bottom: 14px;
            }

            .asset-thumb {
                border-radius: 12px;
                box-shadow: none;
            }

            .asset-badge,
            .asset-meta,
            .asset-uploader {
                display: none;
            }

            .asset-info {
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 6px;
                padding-top: 7px;
            }

            .asset-title {
                font-size: 0.82rem;
                font-weight: 850;
                line-height: 1.25;
                white-space: normal;
            }

            .asset-action {
                width: 28px;
                height: 28px;
                color: var(--ink);
                background: transparent;
            }

            .asset-info .asset-action:last-of-type {
                display: none;
            }

            .asset-item:hover .asset-thumb {
                transform: none;
                box-shadow: none;
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

            .footer-bottom {
                align-items: flex-start;
                flex-direction: column;
                margin-top: 32px;
            }

            .footer-wordmark {
                margin-bottom: -22px;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
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
        </div>
    </header>

    <main>
        <section class="catalog-hero">
            <div class="catalog-hero-inner">
                <form class="catalog-controls" action="catalog.php" method="get">
                    <div class="catalog-search">
                        <?= catalog_icon('search'); ?>
                        <input type="search" name="q" value="<?= htmlspecialchars($query); ?>" placeholder="Search" aria-label="Cari aset visual">
                        <button class="search-button" type="submit">Cari</button>
                    </div>
                </form>

                <div class="catalog-spotlight" aria-label="Sorotan katalog">
                    <?php foreach ($heroSlides as $index => $slide): ?>
                        <div
                            class="spotlight-slide<?= $index === 0 ? ' is-active' : ''; ?>"
                            style="background-image: url('<?= htmlspecialchars((string) $slide['url']); ?>');"
                            data-slide-title="<?= htmlspecialchars((string) $slide['title']); ?>"
                            aria-hidden="true"
                        ></div>
                    <?php endforeach; ?>
                    <div class="catalog-spotlight-content">
                        <p class="spotlight-kicker">Cloudify ideas</p>
                        <h1 class="spotlight-title" id="spotlightTitle"><?= htmlspecialchars((string) $heroSlides[0]['title']); ?></h1>
                        <span class="spotlight-source"><span class="spotlight-source-mark">C</span> Inspiration board</span>
                    </div>
                    <div class="spotlight-dots" aria-hidden="true">
                        <?php foreach ($heroSlides as $index => $_slide): ?>
                            <span class="spotlight-dot<?= $index === 0 ? ' is-active' : ''; ?>" data-slide-dot></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form class="catalog-controls catalog-refine" action="catalog.php" method="get">
                    <?php if ($query !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($query); ?>">
                    <?php endif; ?>
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
            <section>
                <a class="brand" href="index.php">
                    <span class="brand-mark">C</span>
                    <span class="brand-name"><?= htmlspecialchars($appName); ?></span>
                </a>
                <p>Cloudify membantu kamu menemukan, mengkurasi, dan membagikan inspirasi visual dari satu library yang ringan dan cepat.</p>
            </section>
            <nav class="footer-links" aria-label="Navigasi footer">
                <h2 class="footer-title">Navigasi</h2>
                <a href="index.php">Home</a>
                <a href="catalog.php">Katalog</a>
                <a href="<?= htmlspecialchars($loginTarget); ?>"><?= $currentUser === null ? 'Login' : 'Workspace'; ?></a>
            </nav>
            <nav class="footer-links" aria-label="Eksplorasi">
                <h2 class="footer-title">Eksplorasi</h2>
                <a href="catalog.php">Inspirasi</a>
                <a href="catalog.php?sort=newest">Terbaru</a>
                <a href="catalog.php?type=all">Semua gambar</a>
            </nav>
            <nav class="footer-social" aria-label="Social media">
                <h2 class="footer-title">Social</h2>
                <a href="#" aria-label="Instagram Cloudify"><span class="social-dot"></span>Instagram</a>
                <a href="#" aria-label="Dribbble Cloudify"><span class="social-dot"></span>Dribbble</a>
                <a href="#" aria-label="Behance Cloudify"><span class="social-dot"></span>Behance</a>
            </nav>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y'); ?> <?= htmlspecialchars($appName); ?>. All rights reserved.</span>
            <span>Visual library for ideas, design, and moodboards.</span>
        </div>
        <div class="footer-wordmark" aria-hidden="true">CLOUDIFY</div>
    </footer>

    <script>
        (() => {
            const slides = Array.from(document.querySelectorAll('.spotlight-slide'));
            const dots = Array.from(document.querySelectorAll('[data-slide-dot]'));
            const title = document.getElementById('spotlightTitle');

            if (slides.length <= 1 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            let current = 0;

            function showSlide(next) {
                slides[current].classList.remove('is-active');
                dots[current]?.classList.remove('is-active');
                current = next;
                slides[current].classList.add('is-active');
                dots[current]?.classList.add('is-active');

                const nextTitle = slides[current].getAttribute('data-slide-title');
                if (title && nextTitle) {
                    title.textContent = nextTitle;
                }
            }

            window.setInterval(() => {
                showSlide((current + 1) % slides.length);
            }, 3000);
        })();

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
