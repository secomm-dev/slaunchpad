<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Serialize\Serializer\Serialize as PhpSerialize;
use Magento\Store\Model\ScopeInterface;

/**
 * Soft adapter for the site's effective SEO policy (Mirasvit SEO config).
 *
 * Reads raw config values only — never references Mirasvit classes, so the
 * module keeps compiling and operating when Mirasvit_Seo is absent/disabled
 * (SPEC-TASK-0X552E §0.H, implementation decision: config-path soft integration).
 *
 * Semantics mirror proven Mirasvit behavior:
 * - `seo/general/noindex_pages2`: JSON/serialized array of {pattern, option} rows;
 *   pattern matching is case-insensitive with `*` wildcards (Mirasvit\Seo\Helper\Data::checkPattern).
 *   option 1 = NOINDEX,NOFOLLOW, 2 = NOINDEX,FOLLOW, 3 = INDEX,NOFOLLOW, 4 = INDEX,FOLLOW.
 * - `seo/url/trailing_slash`: 0 = leave as-is, 1 = no trailing slash, 2 = force trailing slash
 *   (Mirasvit\Seo\Model\Config getTrailingSlash, .html/.htm suffix exempt).
 */
class SeoPolicy
{
    public const CONFIG_PATH_NOINDEX_RULES = 'seo/general/noindex_pages2';
    public const CONFIG_PATH_TRAILING_SLASH = 'seo/url/trailing_slash';

    private const MIRASVIT_SEO_MODULE = 'Mirasvit_Seo';
    private const OPTION_NOINDEX_NOFOLLOW = 1;
    private const OPTION_NOINDEX_FOLLOW = 2;

    private const TRAILING_SLASH_AS_IS = 0;
    private const TRAILING_SLASH_STRIP = 1;
    private const TRAILING_SLASH_APPEND = 2;

    /**
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     * @param Manager $moduleManager module enablement checker
     * @param JsonSerializer $jsonSerializer decodes JSON noindex rule rows
     * @param PhpSerialize $phpSerializer decodes legacy serialize() rule rows
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Manager $moduleManager,
        private readonly JsonSerializer $jsonSerializer,
        private readonly PhpSerialize $phpSerializer
    ) {
    }

    /**
     * Whether the path is matched by an effective NOINDEX rule of the active SEO policy.
     *
     * Fails open to "indexable" when the SEO module is absent (no rules exist).
     *
     * @param string $path internal URL path of the candidate URL
     * @param int|null $storeId store view scope
     * @return bool true when the path must be excluded from llms.txt
     */
    public function isNoindexed(string $path, ?int $storeId = null): bool
    {
        if (!$this->moduleManager->isEnabled(self::MIRASVIT_SEO_MODULE)) {
            return false;
        }

        $path = '/' . ltrim($path, '/');

        foreach ($this->getNoindexRules($storeId) as $rule) {
            $pattern = $rule['pattern'] ?? null;
            $option = (int) ($rule['option'] ?? 0);

            if (!is_string($pattern) || $pattern === '') {
                continue;
            }
            if (!in_array($option, [self::OPTION_NOINDEX_NOFOLLOW, self::OPTION_NOINDEX_FOLLOW], true)) {
                continue;
            }
            if ($this->matchPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply the effective trailing-slash policy to a URL path
     * (skip .html/.htm suffixes, keep bare "/" untouched).
     *
     * @param string $path internal URL path
     * @param int|null $storeId store view scope
     * @return string path adjusted to the site-wide trailing-slash policy
     */
    public function applyTrailingSlash(string $path, ?int $storeId = null): string
    {
        if (!$this->moduleManager->isEnabled(self::MIRASVIT_SEO_MODULE)) {
            return $path;
        }

        $mode = (int) $this->scopeConfig->getValue(
            self::CONFIG_PATH_TRAILING_SLASH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($mode === self::TRAILING_SLASH_AS_IS || $path === '' || $path === '/') {
            return $path;
        }
        if (preg_match('#\.html?$#i', $path)) {
            return $path;
        }

        if ($mode === self::TRAILING_SLASH_STRIP) {
            return rtrim($path, '/');
        }

        return $path . (substr($path, -1) === '/' ? '' : '/');
    }

    /**
     * Case-insensitive wildcard match, same semantics as Mirasvit checkPattern().
     *
     * @param string $value subject path or URL
     * @param string $pattern wildcard pattern (`*` = any run of characters)
     * @return bool true when the subject matches the pattern
     */
    public function matchPattern(string $value, string $pattern): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#ui';

        return (bool) preg_match($regex, $value);
    }

    /**
     * Decode the effective noindex rule rows for a store scope.
     *
     * @param int|null $storeId store view scope
     * @return array<int, array{pattern: mixed, option: mixed}>
     */
    private function getNoindexRules(?int $storeId): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_NOINDEX_RULES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($raw === '') {
            return [];
        }

        try {
            $decoded = $this->jsonSerializer->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            $decoded = null;
        }

        if (!is_array($decoded)) {
            try {
                $decoded = $this->phpSerializer->unserialize($raw);
            } catch (\InvalidArgumentException $e) {
                $decoded = [$raw];
            }
        }

        return array_values(array_filter(is_array($decoded) ? $decoded : [], 'is_array'));
    }
}
