<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\ResourceModel\Block as BlockResource;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Seeds the homepage newsletter CMS block (TASK-0NNZCW v4, SLP-213).
 * Content-only patch — no schema change. Idempotent: updates the existing
 * block (matched by identifier) when the patch re-runs after a rollback.
 */
class CreateHomepageNewsletterBlock implements DataPatchInterface, PatchRevertableInterface
{
    public const BLOCK_IDENTIFIER = 'homepage-newsletter';

    private BlockFactory $blockFactory;

    private BlockResource $blockResource;

    public function __construct(
        BlockFactory $blockFactory,
        BlockResource $blockResource
    ) {
        $this->blockFactory = $blockFactory;
        $this->blockResource = $blockResource;
    }

    public function apply(): CreateHomepageNewsletterBlock
    {
        $content = <<<HTML
<form class="lp-newsletter-form mx-auto flex w-full max-w-[1136px] flex-col gap-3 lg:flex-row lg:items-center lg:gap-4"
      action="/newsletter/subscriber/new/" method="post"
      id="homepage-newsletter-form">
    <label class="sr-only" for="homepage-newsletter-email">Email Address</label>
    <input class="lp-newsletter-input h-11 w-full rounded-md border border-[#d1d5dc] bg-white px-4 text-[16px] leading-6 text-[#101828] placeholder:text-[#99a1af] focus:border-[#588f60] focus:outline-none"
           id="homepage-newsletter-email" name="email" type="email" required autocomplete="email"
           placeholder="Enter your email address">
    <button class="lp-newsletter-btn h-11 shrink-0 rounded-md bg-[#45744c] px-6 text-[16px] font-medium text-white transition hover:bg-[#35573a]"
            type="submit">Subscribe</button>
</form>
HTML;

        $block = $this->blockFactory->create();
        $this->blockResource->load($block, self::BLOCK_IDENTIFIER, 'identifier');
        if ($block->getId()) {
            // Keep an existing (possibly Admin-edited / translated) block — the
            // module rename re-runs this patch under its new class name.
            return $this;
        }
        $block->setData([
            'identifier' => self::BLOCK_IDENTIFIER,
            'title' => 'Homepage Newsletter Form',
            'is_active' => 1,
            'content' => $content,
        ]);
        $this->blockResource->save($block);

        // Core CMS resource save does not persist the store relation — write it
        // explicitly so the block resolves on every store view (store 0 = all).
        $connection = $this->blockResource->getConnection();
        $connection->delete(
            $this->blockResource->getTable('cms_block_store'),
            ['block_id = ?' => (int) $block->getId()]
        );
        $connection->insertArray(
            $this->blockResource->getTable('cms_block_store'),
            ['block_id', 'store_id'],
            [[(int) $block->getId(), 0]]
        );

        return $this;
    }

    public function revert(): void
    {
        $block = $this->blockFactory->create();
        $this->blockResource->load($block, self::BLOCK_IDENTIFIER, 'identifier');
        if ($block->getId()) {
            $this->blockResource->delete($block);
        }
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
