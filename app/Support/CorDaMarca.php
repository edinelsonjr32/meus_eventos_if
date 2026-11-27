<?php

namespace App\Support;

/**
 * Cor temática do evento nas telas: os botões e títulos usam texto branco sobre a cor (e a cor sobre branco),
 * então uma cor muito clara deixa tudo ilegível. Nesse caso ela é escurecida, mantendo o mesmo tom.
 */
class CorDaMarca
{
    public const PADRAO = '#059669';

    /** Contraste mínimo com o branco (a cor padrão do sistema tem cerca de 3,8). */
    public const CONTRASTE_MINIMO = 3.7;

    public static function legivel(?string $cor): string
    {
        $cor = strtolower(trim((string) $cor));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $cor, $m)) {
            $cor = "#{$m[1]}{$m[1]}{$m[2]}{$m[2]}{$m[3]}{$m[3]}";
        }
        if (!preg_match('/^#[0-9a-f]{6}$/', $cor)) {
            return self::PADRAO;
        }
        if (self::contrasteComBranco($cor) >= self::CONTRASTE_MINIMO) {
            return $cor;
        }

        [$h, $s, $l] = self::paraHsl($cor);
        do {
            $l = max(0, $l - 0.01);
            $nova = self::deHsl($h, $s, $l);
        } while ($l > 0 && self::contrasteComBranco($nova) < self::CONTRASTE_MINIMO);

        return $nova;
    }

    public static function contrasteComBranco(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 1.05 / (0.2126 * $r + 0.7152 * $g + 0.0722 * $b + 0.05);
    }

    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(substr($hex, 1), 2));
    }

    private static function paraHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            return [0, 0, $l];
        }
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, min(1, $s), $l];
    }

    private static function deHsl(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match ((int) floor($h / 60) % 6) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }
}
