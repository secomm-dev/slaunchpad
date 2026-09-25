<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

/**
 * Seeds the CMS page `home` with the Launchpad homepage (PageBuilder master
 * format content — TASK-0NNZCW). Images are MODULE ASSETS
 * (Launchpad_CmsContent::images/homepage/*.webp — view/frontend/web/images/
 * homepage/), except the REAL SPACES video poster which is a MEDIA file
 * (wysiwyg/homepage/real-spaces.webp — upload to the target media storage).
 *
 * Applies once per database (tracked in patch_list). Re-running after Admin
 * edits will NOT re-apply — the patch only seeds on first deploy.
 *
 * Never overwrites an existing Launchpad homepage: when ANY `home` page already
 * carries the Launchpad layout (`lp-hero`), e.g. per-store-view pages translated
 * in Admin on staging, the patch is a no-op. The Launchpad_Homepage →
 * Launchpad_CmsContent rename changed this class name, so a database that ran
 * the old name runs this one again — this guard keeps that run harmless.
 */
class SeedHomepageContent implements DataPatchInterface, PatchVersionInterface
{
    private const PAGE_IDENTIFIER = 'home';
    private const CONTENT_TEMPLATE = __DIR__ . '/../../../etc/homepage-content.html';
    private const LAUNCHPAD_MARKER = 'lp-hero';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param PageFactory $pageFactory
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        PageFactory $pageFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            CreateHomepageNewsletterBlock::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @inheritdoc
     */
    public function apply(): SeedHomepageContent
    {
        $content = (string) file_get_contents(self::CONTENT_TEMPLATE);
        if ($content === '') {
            throw new \RuntimeException('Homepage content template is empty: ' . self::CONTENT_TEMPLATE);
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('identifier', self::PAGE_IDENTIFIER);
        foreach ($collection as $existing) {
            if (str_contains((string) $existing->getContent(), self::LAUNCHPAD_MARKER)) {
                return $this;
            }
        }
        $page = $collection->getFirstItem();

        if (!$page->getId()) {
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::PAGE_IDENTIFIER)
                ->setTitle('Home Page')
                ->setPageLayout('cms-full-width')
                ->setStores([0])
                ->setIsActive(1);
        }

        $page->setContent($content);
        $page->save();

        return $this;
    }
}
