<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Service;

final class DeterministicValueGenerator
{
    public function int(string $key, int $min, int $max): int
    {
        if ($min >= $max) {
            return $min;
        }

        $value = (int)hexdec(substr(hash('sha256', $key), 0, 8));

        return $min + ($value % (($max - $min) + 1));
    }

    public function float(string $key, float $min, float $max, int $precision): float
    {
        if ($min >= $max) {
            return round($min, $precision);
        }

        $value = (int)hexdec(substr(hash('sha256', $key), 0, 8));
        $ratio = $value / 4294967295;

        return round($min + (($max - $min) * $ratio), $precision);
    }
}
