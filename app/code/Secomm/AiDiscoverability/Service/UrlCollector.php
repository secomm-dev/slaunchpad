<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

/**
 * Deterministic collection of emitted URLs: per-section ordering preserved
 * by caller, cross-section deduplication by normalized URL, global bound.
 */
class UrlCollector
{
    private const SORT_FLAG = SORT_NATURAL | SORT_FLAG_CASE;

    /**
     * Collect entries deduplicated by URL up to the global bound.
     *
     * @param array $entries ordered entries (priority order)
     * @param int $maxUrls global bound
     * @return array collected entries
     */
    public function collect(array $entries, int $maxUrls): array
    {
        $seen = [];
        $result = [];

        foreach ($entries as $entry) {
            $url = $entry['url'];
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $result[] = $entry;

            if (count($result) >= $maxUrls) {
                break;
            }
        }

        return $result;
    }

    /**
     * Deterministic alphabetical sort by label, stable for equal labels.
     *
     * @param array $entries entries to sort
     * @return array sorted entries
     */
    public function sortByLabel(array $entries): array
    {
        $labels = array_column($entries, 'label');
        array_multisort(
            array_map(static fn ($l) => mb_strtolower($l), $labels),
            self::SORT_FLAG,
            $entries
        );

        return $entries;
    }
}
