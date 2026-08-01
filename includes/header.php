<?php
/**
 * Shared page header. Include after any page-specific business logic and
 * redirects, and before page-specific markup.
 */

require_once __DIR__ . '/functions.php';

handle_language_switch();

$lang_code = get_current_lang_code();
$dir = $lang_code === 'ar' ? 'rtl' : 'ltr';
$current_page = basename($_SERVER['SCRIPT_NAME']);

/**
 * Small inline logo mark, reused on the print receipt.
 */
function esolar_logo_mark_svg(string $class): string
{
    return '<svg class="' . htmlspecialchars($class) . '" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M13 1.5L4 14h6.2L9 22.5 20 9.5h-6.6L13 1.5Z" fill="#f0a93c"/>'
        . '</svg>';
}

/**
 * Small inline "view" (eye) icon, used for icon-only row actions.
 */
function icon_eye_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/>'
        . '</svg>';
}

/**
 * Small inline "print" (printer) icon, used for icon-only row actions.
 */
function icon_printer_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<polyline points="6 9 6 2 18 2 18 9"/>'
        . '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>'
        . '<rect x="6" y="14" width="12" height="8"/>'
        . '</svg>';
}

/**
 * The full E-Solar mark: three slanted bars forming an "E" with a lightning
 * bolt cutting through the left side - recolored for dark backgrounds (the
 * sidebar and mobile top bar), so $bar_color defaults to white rather than
 * the brand's usual navy.
 */
function esolar_icon_svg(string $class, string $bar_color = '#ffffff'): string
{
    return '<svg class="' . htmlspecialchars($class) . '" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M9 5h23l-4 8H5Z" fill="' . htmlspecialchars($bar_color) . '"/>'
        . '<path d="M9 17h17l-4 8H5Z" fill="' . htmlspecialchars($bar_color) . '"/>'
        . '<path d="M9 29h23l-4 8H5Z" fill="' . htmlspecialchars($bar_color) . '"/>'
        . '<path d="M20 0 6 17h6l-2 21 16-22h-6.5L20 0Z" fill="#f0a93c"/>'
        . '</svg>';
}

/**
 * Small inline "hamburger" (menu) icon, used on the mobile top bar to open
 * the sidebar.
 */
function icon_menu_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>'
        . '</svg>';
}

/**
 * Sidebar nav icons, one per $nav_items entry below. Stroke uses currentColor
 * so each icon automatically follows its link's text color (default, hover,
 * active) with no extra CSS.
 */
function icon_nav_dashboard_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>'
        . '<rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'
        . '</svg>';
}

function icon_nav_add_record_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6"/>'
        . '<path d="M12 12v6M9 15h6"/>'
        . '</svg>';
}

function icon_nav_records_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>'
        . '<line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'
        . '</svg>';
}

function icon_nav_calendar_svg(): string
{
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>'
        . '<line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
        . '</svg>';
}

$nav_items = [
    ['href' => 'index.php', 'label' => t('nav_dashboard'), 'icon' => icon_nav_dashboard_svg()],
    ['href' => 'add_record.php', 'label' => t('nav_new_record'), 'icon' => icon_nav_add_record_svg()],
    ['href' => 'records.php', 'label' => t('nav_records'), 'icon' => icon_nav_records_svg()],
    ['href' => 'calendar.php', 'label' => t('nav_calendar'), 'icon' => icon_nav_calendar_svg()],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('app_name')) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%2316233f'/%3E%3Cpath d='M17 4L9 18h6.4L14 28l9-14.5h-6.7L17 4Z' fill='%23f0a93c'/%3E%3C/svg%3E">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css"
        rel="stylesheet"
        integrity="<?= $dir === 'rtl' ? 'sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb' : 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH' ?>"
        crossorigin="anonymous"
    >
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="esolar-mobile-topbar d-md-none d-flex align-items-center justify-content-between px-3 py-2">
    <a class="navbar-brand" href="index.php">
        <?= esolar_icon_svg('esolar-logo-mark') ?>
        E-SOLAR
    </a>
    <button
        class="btn esolar-sidebar-toggle"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#appSidebar"
        aria-controls="appSidebar"
        aria-label="<?= htmlspecialchars(t('nav_toggle')) ?>"
    ><?= icon_menu_svg() ?></button>
</div>

<div class="d-flex esolar-app-shell">
    <nav class="offcanvas-md offcanvas-start esolar-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <!-- The close button lives in its own row (not .esolar-sidebar-brand)
             because Bootstrap's responsive offcanvas sets .offcanvas-header
             to display:none once the sidebar becomes static at md+, and the
             brand must survive past that breakpoint. -->
        <div class="offcanvas-header d-md-none justify-content-end py-2">
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
        </div>
        <div class="esolar-sidebar-brand">
            <a class="navbar-brand esolar-brand-stacked" href="index.php" id="appSidebarLabel">
                <?= esolar_icon_svg('esolar-logo-icon') ?>
                <span class="esolar-brand-name">E-SOLAR</span>
                <span class="esolar-brand-tagline"><?= htmlspecialchars(t('brand_tagline')) ?></span>
            </a>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <ul class="nav nav-pills flex-column mb-auto">
                <?php foreach ($nav_items as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2<?= $current_page === $item['href'] ? ' active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                            <?= $item['icon'] ?>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex gap-2 esolar-sidebar-langs">
                <a
                    href="<?= htmlspecialchars(build_lang_switch_url('en')) ?>"
                    class="btn btn-sm btn-lang<?= $lang_code === 'en' ? ' active' : '' ?>"
                >EN</a>
                <a
                    href="<?= htmlspecialchars(build_lang_switch_url('ar')) ?>"
                    class="btn btn-sm btn-lang<?= $lang_code === 'ar' ? ' active' : '' ?>"
                >عربي</a>
            </div>
        </div>
    </nav>

    <div class="flex-grow-1 esolar-main">
        <div class="container-fluid py-4">
