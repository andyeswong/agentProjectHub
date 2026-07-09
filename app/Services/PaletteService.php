<?php

namespace App\Services;

/**
 * Extract a brand palette from an image (F3).
 *
 * Deliberately NOT a vision-model call: colors live in the pixels, so we read
 * them. The result is deterministic, self-hosted, free, and explainable — a
 * VLM here would add latency and a chance to hallucinate a hex that isn't in
 * the image. Typography is a different matter: it cannot be read off pixels
 * honestly, so this service does not guess fonts.
 *
 * Method: sample the image, quantize to a coarse grid, count buckets, merge
 * near-duplicates, then assign roles by how designers actually use color —
 * the background is what there is most of, the text is what contrasts hardest
 * with it, the accent is the most saturated thing that is neither.
 */
class PaletteService
{
    /** Cap on sampled pixels — enough signal, bounded work on a 4K screenshot. */
    private const MAX_SAMPLES = 20000;

    /** Channel bucket size when quantizing (smaller = more distinct colors). */
    private const BUCKET = 24;

    /** Below this ratio a bucket is noise (antialiasing, JPEG ringing). */
    private const MIN_RATIO = 0.005;

    /**
     * @return array{palette: array<int,array{hex:string,ratio:float}>, proposed: array<string,string>, sampled: int}
     */
    public function extract(string $bytes): array
    {
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            throw new \InvalidArgumentException('No pude decodificar la imagen.');
        }

        [$counts, $sampled] = $this->sample($img);
        imagedestroy($img);

        if ($sampled === 0) {
            throw new \InvalidArgumentException('La imagen no tiene píxeles opacos.');
        }

        $palette = $this->rank($counts, $sampled);

        return [
            'palette'  => $palette,
            'proposed' => $this->assignRoles($palette),
            'sampled'  => $sampled,
        ];
    }

    /**
     * Walk the image on a stride so any size costs the same, bucketing colors.
     * Fully/mostly transparent pixels are skipped — they are not brand color.
     *
     * @return array{0: array<string,array{n:int,r:int,g:int,b:int}>, 1: int}
     */
    private function sample(\GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $stride = max(1, (int) floor(sqrt(($w * $h) / self::MAX_SAMPLES)));

        $counts  = [];
        $sampled = 0;

        for ($y = 0; $y < $h; $y += $stride) {
            for ($x = 0; $x < $w; $x += $stride) {
                $rgba = imagecolorat($img, $x, $y);

                // GD alpha: 0 = opaque, 127 = fully transparent.
                if ((($rgba >> 24) & 0x7F) > 64) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $key = $this->bucketKey($r, $g, $b);
                if (! isset($counts[$key])) {
                    $counts[$key] = ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                }
                // Accumulate real values so the reported hex is the bucket's
                // true average, not the rounded grid corner.
                $counts[$key]['n']++;
                $counts[$key]['r'] += $r;
                $counts[$key]['g'] += $g;
                $counts[$key]['b'] += $b;
                $sampled++;
            }
        }

        return [$counts, $sampled];
    }

    private function bucketKey(int $r, int $g, int $b): string
    {
        $q = fn (int $c) => intdiv($c, self::BUCKET);
        return $q($r) . ':' . $q($g) . ':' . $q($b);
    }

    /**
     * Average each bucket, drop noise, merge perceptually-identical buckets,
     * and return the palette ordered by how much of the image it covers.
     *
     * @return array<int,array{hex:string,ratio:float}>
     */
    private function rank(array $counts, int $sampled): array
    {
        $colors = [];
        foreach ($counts as $c) {
            $ratio = $c['n'] / $sampled;
            if ($ratio < self::MIN_RATIO) {
                continue;
            }
            $colors[] = [
                'r'     => (int) round($c['r'] / $c['n']),
                'g'     => (int) round($c['g'] / $c['n']),
                'b'     => (int) round($c['b'] / $c['n']),
                'ratio' => $ratio,
            ];
        }

        usort($colors, fn ($a, $b) => $b['ratio'] <=> $a['ratio']);

        // Merge neighbours: adjacent grid cells often describe one flat colour.
        $merged = [];
        foreach ($colors as $c) {
            $hit = null;
            foreach ($merged as $i => $m) {
                if ($this->distance($c, $m) < 30) {
                    $hit = $i;
                    break;
                }
            }
            if ($hit === null) {
                $merged[] = $c;
            } else {
                $merged[$hit]['ratio'] += $c['ratio'];   // the dominant one keeps its hex
            }
        }

        return array_map(fn ($c) => [
            'hex'   => $this->hex($c),
            'ratio' => round($c['ratio'], 4),
        ], array_slice($merged, 0, 8));
    }

    /**
     * Name the colors the way a designer would read the screen.
     *
     * @param array<int,array{hex:string,ratio:float}> $palette
     * @return array<string,string>
     */
    private function assignRoles(array $palette): array
    {
        if (empty($palette)) {
            return [];
        }

        $rgb = fn (string $hex) => $this->rgb($hex);

        // The background is simply what there is most of.
        $bg = $palette[0]['hex'];
        $roles = ['bg' => $bg];

        $rest = array_slice($palette, 1);
        if (empty($rest)) {
            return $roles;
        }

        // Accent BEFORE text. The accent is the most saturated color on the
        // page; text is nearly always a neutral. Naming text first lets a
        // vivid accent steal the label whenever it happens to contrast most —
        // which is exactly what a two-colour image does.
        $saturated = array_values(array_filter($rest, fn ($c) => $this->saturation($rgb($c['hex'])) > 0.25));
        if ($saturated) {
            usort($saturated, fn ($a, $b) => $this->saturation($rgb($b['hex'])) <=> $this->saturation($rgb($a['hex'])));
            $roles['accent'] = $saturated[0]['hex'];
        }

        // Text: hardest contrast against the background, among what is left.
        $textCandidates = array_values(array_filter($rest, fn ($c) => $c['hex'] !== ($roles['accent'] ?? null)));
        if ($textCandidates) {
            usort($textCandidates, fn ($a, $b) => $this->contrast($rgb($b['hex']), $rgb($bg)) <=> $this->contrast($rgb($a['hex']), $rgb($bg)));
            if ($this->contrast($rgb($textCandidates[0]['hex']), $rgb($bg)) >= 3.0) {
                $roles['text'] = $textCandidates[0]['hex'];
            }
        }

        // Surface: a near-neutral close to the background but distinguishable —
        // the card sitting on the page.
        foreach ($rest as $c) {
            if (in_array($c['hex'], $roles, true)) {
                continue;
            }
            $d = $this->distance($rgb($c['hex']), $rgb($bg));
            if ($d > 8 && $d < 60 && $this->saturation($rgb($c['hex'])) < 0.25) {
                $roles['surface'] = $c['hex'];
                break;
            }
        }

        return $roles;
    }

    // ── Color math ──────────────────────────────────────────────────────────

    private function distance(array $a, array $b): float
    {
        return sqrt((($a['r'] - $b['r']) ** 2) + (($a['g'] - $b['g']) ** 2) + (($a['b'] - $b['b']) ** 2));
    }

    /** WCAG relative luminance. */
    private function luminance(array $c): float
    {
        $f = function (int $v) {
            $s = $v / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $f($c['r']) + 0.7152 * $f($c['g']) + 0.0722 * $f($c['b']);
    }

    /** WCAG contrast ratio, 1..21. */
    private function contrast(array $a, array $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);
        [$hi, $lo] = $la > $lb ? [$la, $lb] : [$lb, $la];
        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** HSL saturation, 0..1. */
    private function saturation(array $c): float
    {
        $max = max($c['r'], $c['g'], $c['b']) / 255;
        $min = min($c['r'], $c['g'], $c['b']) / 255;
        if ($max === $min) {
            return 0.0;
        }
        $l = ($max + $min) / 2;
        return $l > 0.5 ? ($max - $min) / (2.0 - $max - $min) : ($max - $min) / ($max + $min);
    }

    private function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', $c['r'], $c['g'], $c['b']);
    }

    private function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        return ['r' => hexdec(substr($h, 0, 2)), 'g' => hexdec(substr($h, 2, 2)), 'b' => hexdec(substr($h, 4, 2))];
    }
}
