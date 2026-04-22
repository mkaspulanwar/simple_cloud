<?php
declare(strict_types=1);

namespace App\Support;

final class Formatter
{
    public static function bytes(int $bytes): string
    {
        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }

    public static function datetime(int $timestamp): string
    {
        return date('d M Y H:i:s', $timestamp);
    }
}
