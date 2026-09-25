<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * SLP-272 (TASK-JBHGNR): turns on "Display Out of Stock Products"
 * (`cataloginventory/options/show_out_of_stock`) so configurable product
 * cards / PDP / Quick View keep out-of-stock options in the swatch list —
 * Hyvä renders them disabled from `jsonConfig.salable`, which Magento only
 * fills when this flag is on. Side effect (accepted): out-of-stock products
 * are listed on PLP / search / widgets.
 *
 * Saved at default scope through the config writer, so it bypasses the
 * backend model; the indexers the Admin save (or the flag itself) affects
 * are invalidated here instead, for cron / `indexer:reindex` to rebuild.
 * Admin can still change the value afterwards (not pinned in config.php).
 */
class EnableShowOutOfStockProducts implements DataPatchInterface
{
    private const XML_PATH = 'cataloginventory/options/show_out_of_stock';
    private const INDEXERS = [
        'catalog_product_attribute',
        'catalog_product_price',
        'cataloginventory_stock',
        'inventory',
        'catalogsearch_fulltext',
    ];

    /**
     * @param WriterInterface $configWriter
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->configWriter->save(self::XML_PATH, '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        foreach (self::INDEXERS as $indexerId) {
            $this->indexerRegistry->get($indexerId)->invalidate();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
