<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Formatter;

/**
 * Convert base unit to valid one
 */
class WeightUnit
{
    /**
     * Glossary of unit abbreviations.
     *
     * @var array
     */
    private array $unitMappings = [
        'pounds' => 'lb',
        'lbs' => 'lb',
        'kilograms' => 'kg',
        'kgs' => 'kg',
        'grams' => 'g',
        'gramm' => 'g',
        'ounces' => 'oz',
        'ozs' => 'oz'
    ];

    /**
     * Format weight to valid one
     *
     * @param string $weight
     * @return string
     */
    public function getFormattedWeightUnit(string $weight) : string
    {
        $words = explode(' ', $weight);

        foreach ($words as &$word) {
            $lowerWord = strtolower($word);
            if (array_key_exists($lowerWord, $this->unitMappings)) {
                $word = $this->unitMappings[$lowerWord];
            }
        }

        $weight = implode(' ', $words);
        if (!preg_match('/\b(lb|kg|oz|g)\b$/', $weight)) {
            $weight .= ' lb';
        }

        return $weight;
    }
}
