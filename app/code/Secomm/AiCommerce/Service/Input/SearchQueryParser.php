<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Input;

use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Service\InvalidParameterException;

/**
 * Fixed-allowlist parser for /ai/catalog/search (plan rev 2 §4).
 *
 * Accepts only: q, category, price_min, price_max, page, page_size, sort,
 * filter[<allowlisted attribute>]. Everything else — unknown keys, oversized
 * values, non-numeric bounds, non-allowlisted sorts/attributes — is rejected
 * with a deterministic 400 invalid_parameter. No SearchCriteria structure,
 * no field names, no query documents are ever accepted from the caller.
 */
class SearchQueryParser
{
    public const SORT_ALLOWLIST = [
        'relevance',
        'price_asc',
        'price_desc',
        'name_asc',
        'name_desc',
    ];

    private const MAX_Q_LENGTH = 128;
    private const MAX_FILTERS = 4;
    private const MAX_PAGE = 50;

    /**
     * @param Config $config module config reader (page size cap, filter allowlist)
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Parse raw GET parameters into a normalized criteria array.
     *
     * @param mixed[] $params raw request params
     * @param int $storeId store view scope (for scoped config)
     * @return array normalized criteria (q, category_id, price_min,
     *     price_max, page, page_size, sort, filters)
     * @throws InvalidParameterException on any unknown/invalid input
     */
    public function parse(array $params, int $storeId): array
    {
        $allowed = ['q', 'category', 'price_min', 'price_max', 'page', 'page_size', 'sort', 'filter', 'store'];

        foreach (array_keys($params) as $key) {
            if (!in_array((string) $key, $allowed, true)) {
                throw new InvalidParameterException(__('Invalid request parameters.'));
            }
        }

        $q = isset($params['q']) ? trim((string) $params['q']) : '';

        if (mb_strlen($q) > self::MAX_Q_LENGTH) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return [
            'q' => $q !== '' ? $q : null,
            'category_id' => $this->parseInt($params, 'category', 1),
            'price_min' => $this->parsePrice($params, 'price_min'),
            'price_max' => $this->parsePrice($params, 'price_max'),
            'page' => $this->boundedInt($params, 'page', 1, self::MAX_PAGE, 1),
            'page_size' => $this->boundedInt(
                $params,
                'page_size',
                1,
                $this->config->getMaxPageSize($storeId),
                20
            ),
            'sort' => $this->parseSort($params),
            'filters' => $this->parseFilters($params, $storeId),
        ];
    }

    /**
     * Parse an optional positive integer parameter.
     *
     * @param mixed[] $params raw request params
     * @param string $key parameter name
     * @param int $min minimum accepted value
     * @return int|null parsed value or null when absent
     * @throws InvalidParameterException on non-numeric/non-positive input
     */
    private function parseInt(array $params, string $key, int $min): ?int
    {
        if (!isset($params[$key]) || $params[$key] === '') {
            return null;
        }

        $value = (string) $params[$key];

        if (!preg_match('/^\d{1,10}$/', $value)) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        $int = (int) $value;

        if ($int < $min) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return $int;
    }

    /**
     * Parse an optional bounded integer parameter with default.
     *
     * @param mixed[] $params raw request params
     * @param string $key parameter name
     * @param int $min minimum accepted value
     * @param int $max maximum accepted value
     * @param int $default default when absent
     * @return int bounded value
     * @throws InvalidParameterException on out-of-range input
     */
    private function boundedInt(array $params, string $key, int $min, int $max, int $default): int
    {
        $value = $this->parseInt($params, $key, $min);

        if ($value === null) {
            return $default;
        }

        if ($value > $max) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return $value;
    }

    /**
     * Parse an optional decimal price bound.
     *
     * @param mixed[] $params raw request params
     * @param string $key parameter name (price_min|price_max)
     * @return float|null parsed bound or null when absent
     * @throws InvalidParameterException on non-decimal/out-of-range input
     */
    private function parsePrice(array $params, string $key): ?float
    {
        if (!isset($params[$key]) || $params[$key] === '') {
            return null;
        }

        $value = (string) $params[$key];

        if (!preg_match('/^\d{1,9}(\.\d{1,4})?$/', $value)) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return (float) $value;
    }

    /**
     * Parse the sort parameter against the fixed allowlist.
     *
     * @param mixed[] $params raw request params
     * @return string normalized sort key
     * @throws InvalidParameterException on non-allowlisted sort
     */
    private function parseSort(array $params): string
    {
        if (!isset($params['sort']) || $params['sort'] === '') {
            return 'relevance';
        }

        $sort = (string) $params['sort'];

        if (!in_array($sort, self::SORT_ALLOWLIST, true)) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return $sort;
    }

    /**
     * Parse filter[<attribute>] = value against the configured allowlist.
     *
     * @param mixed[] $params raw request params
     * @param int $storeId store view scope
     * @return array<string, string> attribute_code => value
     * @throws InvalidParameterException on non-allowlisted attribute or excess filters
     */
    private function parseFilters(array $params, int $storeId): array
    {
        if (!isset($params['filter']) || !is_array($params['filter'])) {
            return [];
        }

        $allowlist = $this->config->getFilterAllowlist($storeId);
        $filters = [];

        foreach ($params['filter'] as $attribute => $value) {
            $attribute = strtolower(trim((string) $attribute));

            if (!in_array($attribute, $allowlist, true)) {
                throw new InvalidParameterException(__('Invalid request parameters.'));
            }

            $value = trim((string) $value);

            if ($value === '' || mb_strlen($value) > 64) {
                throw new InvalidParameterException(__('Invalid request parameters.'));
            }

            $filters[$attribute] = $value;
        }

        if (count($filters) > self::MAX_FILTERS) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        return $filters;
    }
}
