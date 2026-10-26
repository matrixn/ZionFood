<?php

namespace App\Domain\Delivery;

class Polygon
{
    private static function cross(array $a, array $b, array $p): float
    {
        return ($b[0] - $a[0]) * ($p[1] - $a[1]) - ($b[1] - $a[1]) * ($p[0] - $a[0]);
    }

    private static function on(array $a, array $b, array $p): bool
    {
        return abs(self::cross($a, $b, $p)) < 1e-10 && $p[0] >= min($a[0], $b[0]) - 1e-10 && $p[0] <= max($a[0], $b[0]) + 1e-10 && $p[1] >= min($a[1], $b[1]) - 1e-10 && $p[1] <= max($a[1], $b[1]) + 1e-10;
    }

    private static function intersects(array $a, array $b, array $c, array $d): bool
    {
        $ab1 = self::cross($a, $b, $c);
        $ab2 = self::cross($a, $b, $d);
        $cd1 = self::cross($c, $d, $a);
        $cd2 = self::cross($c, $d, $b);

        return ($ab1 * $ab2 < 0 && $cd1 * $cd2 < 0) || self::on($a, $b, $c) || self::on($a, $b, $d) || self::on($c, $d, $a) || self::on($c, $d, $b);
    }

    public static function valid(mixed $geometry): bool
    {
        if (! is_array($geometry) || ($geometry['type'] ?? '') !== 'Polygon' || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) !== 1) {
            return false;
        }
        if (! array_is_list($geometry['coordinates'])) {
            return false;
        }
        $ring = $geometry['coordinates'][0];
        if (! is_array($ring) || ! array_is_list($ring) || count($ring) < 4 || count($ring) > 201) {
            return false;
        }
        foreach ($ring as $p) {
            if (! is_array($p) || ! array_is_list($p) || count($p) !== 2 || ! (is_int($p[0]) || is_float($p[0])) || ! (is_int($p[1]) || is_float($p[1])) || ! is_finite((float) $p[0]) || ! is_finite((float) $p[1]) || abs($p[0]) > 180 || abs($p[1]) > 85) {
                return false;
            }
        }
        $n = count($ring) - 1;
        if ((float) $ring[0][0] !== (float) $ring[$n][0] || (float) $ring[0][1] !== (float) $ring[$n][1]) {
            return false;
        }
        $area = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($ring[$i] == $ring[$i + 1] || abs($ring[$i][0] - $ring[$i + 1][0]) > 180) {
                return false;
            }
            $area += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
            for ($j = $i + 2; $j < $n; $j++) {
                if ($i === 0 && $j === $n - 1) {
                    continue;
                } if (self::intersects($ring[$i], $ring[$i + 1], $ring[$j], $ring[$j + 1])) {
                    return false;
                }
            }
        }

        return abs($area) > 1e-10;
    }

    public static function contains(array $geometry, float $latitude, float $longitude): bool
    {
        $ring = $geometry['coordinates'][0];
        $p = [$longitude, $latitude];
        $inside = false;
        for ($i = 0,$j = count($ring) - 1; $i < count($ring); $j = $i++) {
            $a = $ring[$j];
            $b = $ring[$i];
            if (self::on($a, $b, $p)) {
                return true;
            } if (($a[1] > $latitude) !== ($b[1] > $latitude) && $longitude < ($b[0] - $a[0]) * ($latitude - $a[1]) / ($b[1] - $a[1]) + $a[0]) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
