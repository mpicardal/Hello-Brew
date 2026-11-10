<?php
declare(strict_types=1);

/* Server-rendered SVG charts: no external chart library, works under a strict CSP. */

function nice_max(float $v): float
{
    if ($v <= 0) return 1;
    $mag = 10 ** floor(log10($v));
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($v <= $m * $mag) return $m * $mag;
    }
    return 10 * $mag;
}

function short_num(float $v): string
{
    if ($v >= 1000000) return rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.') . 'M';
    if ($v >= 1000) return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k';
    return (string) round($v);
}

/** Vertical column chart for a time series. */
function column_chart(array $labels, array $values, string $title, string $prefix = ''): string
{
    $w = 760; $h = 240; $pl = 48; $pr = 8; $pt = 12; $pb = 30;
    $n = max(1, count($values));
    $max = nice_max((float) max($values ?: [0]));
    $plotW = $w - $pl - $pr; $plotH = $h - $pt - $pb;
    $slot = $plotW / $n; $bw = max(2, min(28, $slot * 0.7));
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $pt + $plotH - $plotH * $i / 4;
        $svg .= '<line class="grid" x1="' . $pl . '" x2="' . ($w - $pr) . '" y1="' . $y . '" y2="' . $y . '"/>';
        $svg .= '<text class="axis" x="' . ($pl - 6) . '" y="' . ($y + 4) . '" text-anchor="end">' . e($prefix . short_num($max * $i / 4)) . '</text>';
    }
    $every = (int) ceil($n / 10);
    foreach (array_values($values) as $i => $v) {
        $bh = $max > 0 ? $plotH * $v / $max : 0;
        $x = $pl + $slot * $i + ($slot - $bw) / 2;
        $y = $pt + $plotH - $bh;
        $svg .= '<rect class="bar" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max($bh, 0), 1) . '" rx="3">'
              . '<title>' . e($labels[$i] . ': ' . $prefix . number_format((float) $v, 2)) . '</title></rect>';
        if ($i % $every === 0) {
            $svg .= '<text class="axis" x="' . round($x + $bw / 2, 1) . '" y="' . ($h - 10) . '" text-anchor="middle">' . e($labels[$i]) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Horizontal bars with labels, e.g. top products or a funnel. */
function hbar_chart(array $rows, string $title, string $prefix = '', string $class = 'bar'): string
{
    $rowH = 34; $w = 600; $labelW = 160; $valW = 130;
    $h = max(1, count($rows)) * $rowH;
    $max = max(1, (float) max(array_column($rows, 'value') ?: [0]));
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '">';
    foreach (array_values($rows) as $i => $r) {
        $y = $i * $rowH;
        $bw = ($w - $labelW - $valW) * ((float) $r['value'] / $max);
        $svg .= '<text class="label" x="0" y="' . ($y + 21) . '">' . e(mb_strimwidth($r['label'], 0, 24, '…')) . '</text>';
        $svg .= '<rect class="track" x="' . $labelW . '" y="' . ($y + 7) . '" width="' . ($w - $labelW - $valW) . '" height="20" rx="10"/>';
        $svg .= '<rect class="' . $class . '" x="' . $labelW . '" y="' . ($y + 7) . '" width="' . round(max($bw, 4), 1) . '" height="20" rx="10"/>';
        $svg .= '<text class="value" x="' . ($w - $valW + 8) . '" y="' . ($y + 21) . '">' . e($r['display'] ?? ($prefix . short_num((float) $r['value']))) . '</text>';
    }
    return $svg . '</svg>';
}
