<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Launchpad\CmsContent\Model\FooterBlockContent;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\ResourceModel\Block as BlockResource;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Seeds the footer CMS blocks (TASK-7EYJ4C, SLP-275): footer_newsletter,
 * footer_links, footer_social, footer_trust_payments — PageBuilder content
 * (see Launchpad\CmsContent\Model\FooterBlockContent for the markup contract).
 *
 * Content-only patch — no schema change. Each identifier is seeded per staging
 * store map: store 4 (English) and store 1 (Tiếng Việt). There is NO store-0
 * fallback row — a store view without a dedicated row shows no footer block,
 * by design (staging stores: 1 = VI, 4 = EN).
 *
 * A store view that does not exist in the target environment is skipped
 * (cms_block_store.store_id has an FK to store): local envs without the
 * staging store views only seed the rows they have.
 *
 * Idempotent per (identifier, store): an existing row is kept untouched so
 * Admin edits survive a patch re-run (use the reseed evidence script to
 * re-apply the canonical content over existing rows).
 */
class SeedFooterBlocks implements DataPatchInterface, PatchRevertableInterface
{
    private const IDENTIFIERS = [
        'footer_newsletter',
        'footer_links',
        'footer_social',
        'footer_trust_payments',
    ];

    /** Staging store views (user-confirmed 09-29): 1 = Tiếng Việt, 4 = English. */
    private const STORE_ID_VI = 1;
    private const STORE_ID_EN = 4;

    private BlockFactory $blockFactory;

    private BlockResource $blockResource;

    private StoreManagerInterface $storeManager;

    public function __construct(
        BlockFactory $blockFactory,
        BlockResource $blockResource,
        StoreManagerInterface $storeManager
    ) {
        $this->blockFactory = $blockFactory;
        $this->blockResource = $blockResource;
        $this->storeManager = $storeManager;
    }

    public function apply(): SeedFooterBlocks
    {
        foreach (FooterBlockContent::blocks() as $identifier => [$title, $contents]) {
            foreach ($contents as $index => $content) {
                // Index 0 → store 4 (English); index 1 → store 1 (Tiếng Việt).
                $storeId = $index === 0 ? self::STORE_ID_EN : self::STORE_ID_VI;
                if (!$this->storeExists($storeId)) {
                    continue;
                }
                $this->saveBlock($identifier, $index === 0 ? $title : $title . ' (VI)', $storeId, $content);
            }
        }

        return $this;
    }

    private function storeExists(int $storeId): bool
    {
        try {
            return (bool) $this->storeManager->getStore($storeId)->getId();
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    public function revert(): void
    {
        foreach (self::IDENTIFIERS as $identifier) {
            $connection = $this->blockResource->getConnection();
            $blockIds = $connection->fetchCol(
                $connection->select()
                    ->from($this->blockResource->getTable('cms_block'), ['block_id'])
                    ->where('identifier = ?', $identifier)
            );
            foreach ($blockIds as $blockId) {
                $block = $this->blockFactory->create();
                $this->blockResource->load($block, (int) $blockId);
                if ($block->getId()) {
                    $this->blockResource->delete($block);
                }
            }
        }
    }

    /**
     * Create the block for (identifier, store) unless it already exists —
     * matched through cms_block_store, not load-by-identifier (which is
     * ambiguous once an identifier has per-store rows; see the duplicated
     * homepage-newsletter rows from TASK-0NNZCW).
     */
    private function saveBlock(string $identifier, string $title, int $storeId, string $content): void
    {
        $connection = $this->blockResource->getConnection();
        $existingId = $connection->fetchOne(
            $connection->select()
                ->from(['b' => $this->blockResource->getTable('cms_block')], ['block_id'])
                ->join(
                    ['s' => $this->blockResource->getTable('cms_block_store')],
                    's.block_id = b.block_id',
                    []
                )
                ->where('b.identifier = ?', $identifier)
                ->where('s.store_id = ?', $storeId)
                ->limit(1)
        );
        if ($existingId !== false && (int) $existingId > 0) {
            return;
        }

        $block = $this->blockFactory->create();
        $block->setData([
            'identifier' => $identifier,
            'title' => $title,
            'is_active' => 1,
            'content' => $content,
        ]);
        $this->blockResource->save($block);

        $connection->insertArray(
            $this->blockResource->getTable('cms_block_store'),
            ['block_id', 'store_id'],
            [[(int) $block->getId(), $storeId]]
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
