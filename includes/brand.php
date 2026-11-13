<?php
/*
 * Small inline SVGs used across the site: the tree mark, the copper arches
 * from the dining-room wall, and the menu icons. Inline so they inherit
 * colour from CSS (currentColor) and cost no extra requests.
 */

if (!function_exists('render_tree_mark')) {

function render_tree_mark(string $class = '', string $root = ''): void
{
    // The October tree, cut from the restaurant's own logo.
    ?>
    <img class="<?= e($class) ?> tree-mark-img" src="<?= e($root) ?>images/logo/tree-mark.png" alt="" width="64" height="64" aria-hidden="true">
    <?php
}

/** The interlocking copper arches painted on the restaurant's feature wall. */
function render_arches(string $class = 'arches'): void
{
    ?>
    <svg class="<?= e($class) ?>" viewBox="0 0 600 300" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
        <g fill="none" stroke="currentColor" stroke-width="1.4">
            <path d="M0 300V180a70 70 0 0 1 140 0v120"/>
            <path d="M90 300V120a85 85 0 0 1 170 0v180"/>
            <path d="M210 300V200a55 55 0 0 1 110 0v100"/>
            <path d="M280 300V90a75 75 0 0 1 150 0v210"/>
            <path d="M390 300V170a60 60 0 0 1 120 0v130"/>
            <path d="M470 300V110a80 80 0 0 1 160 0v190"/>
            <path d="M140 300V230a40 40 0 0 1 80 0v70"/>
            <path d="M430 300V240a35 35 0 0 1 70 0v60"/>
        </g>
    </svg>
    <?php
}

function icon(string $name): string
{
    $icons = [
        'leaf'   => '<path d="M5 19c0-8 5-14 14-14 0 9-6 14-14 14Z"/><path d="M5 19 14 10"/>',
        'wave'   => '<path d="M3 9c3-3 6-3 9 0s6 3 9 0"/><path d="M3 14c3-3 6-3 9 0s6 3 9 0"/><path d="M3 19c3-3 6-3 9 0s6 3 9 0"/>',
        'chef'   => '<path d="M7 18h10v3H7z"/><path d="M7 18v-4a4 4 0 0 1-1-7.5A5 5 0 0 1 12 3a5 5 0 0 1 6 3.5A4 4 0 0 1 17 14v4"/>',
        'chilli' => '<path d="M17.5 6.5c2.3 1.6 2.6 5 .6 8.4C15.6 19 10.4 21.6 4 20.6c4.6-1.6 7.4-4.3 8.7-8.3.8-2.5 2-4.6 4.8-5.8Z" fill="currentColor" stroke="none"/><path d="M16.2 7.2c-.3-1.8.4-3.3 2.3-4.2" stroke-width="1.8"/>',
        'star'   => '<path d="m12 2 3 6.6 7 .8-5.2 4.8 1.4 7L12 17.7 5.8 21.2l1.4-7L2 9.4l7-.8Z" fill="currentColor" stroke="none"/>',
        'phone'  => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2Z"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2a5 5 0 0 1 6 4.8"/>',
    ];
    $body = $icons[$name] ?? '';
    return '<svg class="icon icon-' . $name . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

/** Chilli rating as printed on the menu, out of five. */
function spice_meter(int $level): string
{
    if ($level < 1) {
        return '';
    }
    $out = '<span class="spice" title="Spice ' . $level . ' of 5"><span class="visually-hidden">Spice level ' . $level . ' of 5</span>';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="spice-pip' . ($i <= $level ? ' is-hot' : '') . '">' . icon('chilli') . '</span>';
    }
    return $out . '</span>';
}

/** "18.95" -> "18.95", "8.00" -> "8" for a cleaner menu. */
function fmt_price(string $price): string
{
    return preg_replace('/\.00$/', '', $price);
}

}
