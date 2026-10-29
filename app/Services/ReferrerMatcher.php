<?php

namespace App\Services;

use App\Models\Referrer;
use Illuminate\Support\Collection;

/**
 * Mendeteksi nama pembawa yang sama atau mirip.
 *  - "sama"  : setelah dinormalkan identik (misal "H. Budi Santoso" = "budi santoso"). Ditolak.
 *  - "mirip" : nama yang satu termuat di yang lain ("Budi" ~ "Budi Santoso") atau selisih ketikan kecil. Hanya peringatan.
 */
class ReferrerMatcher
{
    /** Gelar dan sapaan yang diabaikan saat membandingkan. */
    private const IGNORED = [
        'pak', 'bapak', 'bpk', 'bu', 'ibu', 'ustadz', 'ustadzah', 'ust', 'kyai', 'kiai', 'kh', 'gus',
        'h', 'hj', 'haji', 'hajah', 'drs', 'dra', 'dr', 'ir', 'prof', 'ns',
        'spd', 'sag', 'sh', 'se', 'ssi', 'skom', 'mpd', 'mm', 'msi',
    ];

    public function key(string $name): string
    {
        $lower = mb_strtolower($name);
        $lower = str_replace(['.', ','], '', $lower);
        $lower = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower));

        $tokens = $lower === '' ? [] : explode(' ', $lower);
        $kept = array_values(array_filter($tokens, fn ($t) => ! in_array($t, self::IGNORED, true)));

        return mb_substr(implode(' ', $kept ?: $tokens), 0, 100);
    }

    /**
     * @param  Collection<int, Referrer>  $existing  harus memuat id, name, name_key
     * @return array{exact: ?Referrer, similar: Collection<int, Referrer>}
     */
    public function match(string $key, Collection $existing, ?int $ignoreId = null): array
    {
        $exact = null;
        $similar = collect();
        $tokens = explode(' ', $key);

        foreach ($existing as $other) {
            if ($ignoreId !== null && $other->id === $ignoreId) {
                continue;
            }

            if ($other->name_key === $key) {
                $exact = $other;
                continue;
            }

            $otherTokens = explode(' ', $other->name_key);
            $subset = ! array_diff($tokens, $otherTokens) || ! array_diff($otherTokens, $tokens);
            $maxDistance = mb_strlen($key) >= 8 ? 2 : 1;
            $close = levenshtein($key, $other->name_key) <= $maxDistance;

            if ($subset || $close) {
                $similar->push($other);
            }
        }

        return ['exact' => $exact, 'similar' => $similar->take(5)->values()];
    }
}
