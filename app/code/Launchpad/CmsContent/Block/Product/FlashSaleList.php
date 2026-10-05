<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Block\Product;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\ViewModel\Product\OptionsData;
use Magento\CatalogWidget\Model\Rule;
use Magento\CatalogWidget\Block\Product\ProductsList;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\Condition\Sql\Builder as SqlBuilder;
use Magento\Widget\Helper\Conditions;

/**
 * Flash Sale product slider (TASK-0NNZCW v4, SLP-213).
 *
 * CatalogWidget ProductsList with one extra widget option: `sale_end`
 * (store-local datetime, per store timezone) driving the "Sale ending in"
 * countdown rendered by the flash-sale template. Empty value counts down to
 * the next midnight.
 *
 * An expired `sale_end` ends the sale server-side: isSaleEnded() makes the
 * template render nothing (BUG-9X14Y1) and getCacheLifetime() caps the block
 * cache at the seconds remaining until sale_end so a cached "sale active"
 * render is never served after the countdown expired.
 *
 * @api
 */
class FlashSaleList extends ProductsList
{
    private TimezoneInterface $timezone;

    /**
     * Parent signature + TimezoneInterface (DI resolves the extra param).
     */
    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        HttpContext $httpContext,
        SqlBuilder $sqlBuilder,
        Rule $rule,
        Conditions $conditionsHelper,
        TimezoneInterface $timezone,
        array $data = [],
        ?JsonSerializer $json = null,
        ?LayoutFactory $layoutFactory = null,
        ?EncoderInterface $urlEncoder = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?OptionsData $optionsData = null
    ) {
        $this->timezone = $timezone;
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data,
            $json,
            $layoutFactory,
            $urlEncoder,
            $categoryRepository,
            $optionsData
        );
    }

    /**
     * NOTE: media gallery batching deliberately does NOT happen here. The
     * Smile Elasticsuite virtual-category plugin wraps createCollection and
     * reloads the collection afterwards — any addMediaGalleryData() call made
     * inside this method gets discarded and the page limit is lost (8 → 162
     * items). The templates (flash-sale.phtml / PB carousel.phtml) therefore
     * call addMediaGalleryData() AFTER this method returns.
     */

    /**
     * Countdown target in Unix epoch milliseconds for the Alpine client script.
     */
    public function getSaleEndMillis(): int
    {
        $raw = trim((string) $this->getData('sale_end'));
        try {
            $zone = new \DateTimeZone($this->timezone->getConfigTimezone());
            $end = $raw !== ''
                ? new \DateTimeImmutable($raw, $zone)
                : new \DateTimeImmutable('tomorrow midnight', $zone);
        } catch (\Exception $e) {
            $end = new \DateTimeImmutable('tomorrow midnight');
        }

        return $end->getTimestamp() * 1000;
    }

    /**
     * True when the configured sale_end has already passed (store-local
     * time). Empty/invalid sale_end falls back to the next midnight and
     * therefore never ends server-side (evergreen daily countdown).
     */
    public function isSaleEnded(): bool
    {
        return $this->getSaleEndMillis() <= time() * 1000;
    }

    /**
     * Cap the block cache lifetime at the seconds remaining until sale_end
     * (BUG-9X14Y1) — ProductsList caches 86400s by default, which would
     * serve an "sale active" render up to 24h after the sale ended. Once
     * expired the floor is 1s. Blocks without a cache lifetime are left
     * uncached, as before.
     */
    protected function getCacheLifetime()
    {
        $lifetime = parent::getCacheLifetime();
        if (!$lifetime) {
            return $lifetime;
        }

        $remaining = (int) ceil(($this->getSaleEndMillis() - time() * 1000) / 1000);

        return max(1, min((int) $lifetime, $remaining));
    }
}
