<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

/**
 * Deterministic llms.txt plain-Markdown output (SPEC-TASK-0X552E §5).
 * Section order fixed; entries passed in already ordered/deduped/bounded.
 */
class LlmsTxtFormatter
{
    private const SUMMARY_MAX_LENGTH = 500;

    /**
     * Format the llms.txt document body.
     *
     * @param string $siteName site display name
     * @param string $summary brand summary block
     * @param string $locale locale code
     * @param array $sections ordered section-key => entries
     * @return string UTF-8, LF newlines, no BOM
     */
    public function format(string $siteName, string $summary, string $locale, array $sections): string
    {
        $lines = [];
        $lines[] = '# ' . $this->sanitizeText($siteName, false);

        $summary = $this->sanitizeText($summary);
        if ($summary !== '') {
            $lines[] = '> ' . $summary;
        }

        $locale = trim($locale);
        if ($locale !== '') {
            $lines[] = '';
            $lines[] = 'Locale: ' . $this->sanitizeText($locale, false);
        }

        foreach ($sections as $heading => $entries) {
            if ($entries === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = '## ' . $this->sanitizeText($heading, false);
            foreach ($entries as $entry) {
                $lines[] = '- [' . $this->sanitizeText($entry['label']) . ']: ' . $entry['url'];
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Sanitize merchant-entered text to a single safe line.
     *
     * @param string $text raw input text
     * @param bool $escapeBrackets whether to escape square brackets
     * @return string sanitized single-line text
     */
    private function sanitizeText(string $text, bool $escapeBrackets = true): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $text = mb_substr($text, 0, self::SUMMARY_MAX_LENGTH);

        if ($escapeBrackets) {
            $text = str_replace(['[', ']'], ['\\[', '\\]'], $text);
        }

        return $text;
    }
}
