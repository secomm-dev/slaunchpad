<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

/**
 * Deterministic llms.txt plain-Markdown output (SPEC-TASK-0X552E §5, §12).
 * Section order fixed; entries passed in already ordered/deduped/bounded.
 *
 * Link entries use the llms.txt v2 Markdown hyperlink form
 * `- [Label](url)` with an optional `: Description` suffix.
 */
class LlmsTxtFormatter
{
    private const SUMMARY_MAX_LENGTH = 500;
    private const DESCRIPTION_MAX_LENGTH = 200;

    /**
     * Format the llms.txt document body.
     *
     * @param string $siteName site display name
     * @param string $summary brand summary block
     * @param string $locale locale code
     * @param array $sections ordered section-key => entries
     * @param string $currency ISO currency code ('' to omit the line)
     * @return string UTF-8, LF newlines, no BOM
     */
    public function format(
        string $siteName,
        string $summary,
        string $locale,
        array $sections,
        string $currency = ''
    ): string {
        $lines = [];
        $lines[] = '# ' . $this->sanitizeText($siteName);

        $summary = $this->sanitizeText($summary);
        if ($summary !== '') {
            $lines[] = '> ' . $summary;
        }

        $locale = trim($locale);
        if ($locale !== '' || $currency !== '') {
            $lines[] = '';
            if ($locale !== '') {
                $lines[] = 'Locale: ' . $this->sanitizeText($locale);
            }
            if ($currency !== '') {
                $lines[] = 'Currency: ' . $this->sanitizeText($currency);
            }
        }

        foreach ($sections as $heading => $entries) {
            if ($entries === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = '## ' . $this->sanitizeText($heading);
            foreach ($entries as $entry) {
                $lines[] = $this->formatEntry($entry);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Format one link entry as a Markdown hyperlink with optional description.
     *
     * Entries flagged `plain` (route templates carrying a placeholder, never a
     * resolvable URL) render as `- Label: url` instead of a hyperlink.
     *
     * @param array $entry link entry (label, url, optional description, optional plain)
     * @return string formatted entry line
     */
    private function formatEntry(array $entry): string
    {
        if (!empty($entry['plain'])) {
            return '- ' . $this->sanitizeText((string) ($entry['label'] ?? '')) . ': ' . $entry['url'];
        }

        $line = '- [' . $this->sanitizeText((string) ($entry['label'] ?? '')) . '](' . $entry['url'] . ')';

        $description = $this->sanitizeText((string) ($entry['description'] ?? ''), self::DESCRIPTION_MAX_LENGTH);
        if ($description !== '') {
            $line .= ': ' . $description;
        }

        return $line;
    }

    /**
     * Sanitize merchant-entered text to a single safe line.
     *
     * Strips HTML, control characters and square-bracket Markdown breaks.
     *
     * @param string $text raw input text
     * @param int|null $maxLength character bound (null = summary default)
     * @return string sanitized single-line text
     */
    private function sanitizeText(string $text, ?int $maxLength = null): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $text = mb_substr($text, 0, $maxLength ?? self::SUMMARY_MAX_LENGTH);
        $text = str_replace(['[', ']'], ['\\[', '\\]'], $text);

        return $text;
    }
}
