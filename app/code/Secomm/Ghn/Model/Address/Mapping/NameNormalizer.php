<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

/**
 * SPEC-FEAT-FQWEQ3 §7 — deterministic name normalization used ONLY by the offline mapping
 * generation/audit pipeline. Runtime resolution never compares names (canonical code → approved
 * mapping → GHN identity); this class lives strictly on the generation side.
 */
final class NameNormalizer
{
    /**
     * Administrative prefixes stripped by normalizePrefixless() (TASK-6TNKDH §7/§8 — safe,
     * documented normalization; applied AFTER normalize(), prefix position anchored, looped).
     * Both Vietnamese-diacritic and ASCII spellings are listed — GHN emits "Xã/Phường/Thị trấn".
     */
    private const ADMIN_PREFIXES = [
        'xã', 'xa',
        'phường', 'phuong',
        'thị trấn', 'thi tran',
        'thị xã', 'thi xa',
        'quận', 'quan',
        'huyện', 'huyen',
        'thành phố', 'thanh pho',
        'tp',
    ];

    /**
     * NFC → trim → collapse internal whitespace → lowercase → strip separator punctuation
     * (. , ' - ( ) /). Vietnamese diacritics are PRESERVED (exact matching, not transliteration).
     */
    public function normalize(string $name): string
    {
        $normalized = normalizer_normalize(trim($name), \Normalizer::FORM_C);
        if ($normalized === false) {
            $normalized = trim($name);
        }

        $normalized = mb_strtolower($normalized);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);
        $normalized = (string) preg_replace('/[.,\'\-()\/]/u', '', $normalized);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * normalize() + strip anchored administrative prefixes ("Xã An Phú" → "an phú"). Diacritics
     * are still preserved — only the administrative word is removed, so "Ia Bang" and "Ia Băng"
     * remain distinct (§8). Evidence tier only: never upgrades a suggestion to approval.
     */
    public function normalizePrefixless(string $name): string
    {
        return $this->stripPrefixes($this->normalize($name));
    }

    /**
     * normalizePrefixless() + fold Vietnamese diacritics and đ→d ("Hòa Bình"/"Hoà Bình" → "hoa
     * binh"). DEEPEST evidence tier (TASK-6TNKDH §8: aggressive normalization is allowed only
     * evidence-driven + documented): the two official Vietnamese orthographies (oà/òa, hoà/hóa)
     * and dataset provenance differences make pure-diacritic matching miss real same-place pairs.
     * Identity collapse is prevented structurally: a folded match yielding MORE THAN ONE
     * candidate stays AMBIGUOUS in the matcher, and every suggestion remains REVIEW_REQUIRED.
     */
    public function normalizeAsciiFolded(string $name): string
    {
        $folded = $this->stripPrefixes($this->normalize($name));
        $decomposed = normalizer_normalize($folded, \Normalizer::FORM_KD);
        if ($decomposed !== false) {
            $folded = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        }

        return str_replace('đ', 'd', $folded);
    }

    private function stripPrefixes(string $normalized): string
    {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (self::ADMIN_PREFIXES as $prefix) {
                $withSpace = $prefix . ' ';
                if (str_starts_with($normalized, $withSpace)) {
                    $normalized = ltrim(substr($normalized, strlen($withSpace)));
                    $changed = true;
                }
            }
        }

        return $normalized;
    }
}
