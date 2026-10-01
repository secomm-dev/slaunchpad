<?php
/**
 * TASK-08343C (SLP-246): Launchpad menu feature-content ViewModel.
 *
 * Exposes the two managed feature sources for the Launchpad menu panels:
 * the level-0 branch node's own image (Snowdog node image, resolved through
 * the vendor ImageFile URL service) and the optional Desktop feature copy CMS
 * block `launchpad-menu-feature-<nodeId>`. Keeps the vendor-managed URL
 * resolution out of the templates without duplicating it.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\ViewModel;

use Magento\Cms\Block\BlockByIdentifier;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\BlockFactory;
use Snowdog\Menu\Api\Data\NodeInterface;
use Snowdog\Menu\Block\NodeType\Category;
use Snowdog\Menu\Block\NodeType\CmsPage;
use Snowdog\Menu\Block\NodeType\CustomUrl;
use Snowdog\Menu\Model\Menu\Node\Image\File;

class MenuFeature implements ArgumentInterface
{
    public const FEATURE_BLOCK_PREFIX = 'launchpad-menu-feature-';

    public function __construct(
        private readonly File $nodeImageFile,
        private readonly BlockFactory $blockFactory
    ) {
    }

    /**
     * Feature visual for a menu branch: the Snowdog node image URL, resolved
     * exactly like the vendor renderer resolves it (empty string when the
     * node has no image or its type does not support one).
     */
    public function getImageUrl(?NodeInterface $node): string
    {
        $image = $node ? (string) $node->getImage() : '';
        return $image === ''
            ? ''
            : $this->nodeImageFile->getUrl($image);
    }

    /**
     * Optional Desktop feature copy: rendered CMS block HTML for
     * `launchpad-menu-feature-<nodeId>`, empty string when absent.
     */
    public function getCmsBlockHtml(?NodeInterface $node): string
    {
        if (!$node) {
            return '';
        }
        $block = $this->blockFactory->createBlock(
            BlockByIdentifier::class,
            ['data' => ['identifier' => self::FEATURE_BLOCK_PREFIX . (string) $node->getNodeId()]]
        );

        return (string) $block->toHtml();
    }

    /**
     * Destination URL of a branch node through the vendor node-type URL
     * resolvers (category/custom URL); empty string for types without a
     * simple destination (wrapper, CMS page) or without configured target.
     *
     * $provider must be the menu block's own configured provider instance
     * ($block->getNodeTypeProvider(...)) — its node registry carries the
     * resolved category URL data. A missing registry entry means the node has
     * no resolvable destination: no "See all" row is rendered (deliberate
     * graceful degradation, documented in SPEC-FEAT-ZNJ4KF §2.1.4).
     */
    public function getBranchUrl(?NodeInterface $node, ?object $provider = null): string
    {
        if (!$node || !$provider) {
            return '';
        }
        try {
            return match (true) {
                $provider instanceof Category => (string) $provider->getCategoryUrl($node->getContent()),
                $provider instanceof CmsPage => (string) $provider->getPageUrl($node->getNodeId()),
                $provider instanceof CustomUrl => (string) $provider->getCustomUrl($node->getNodeId()),
                default => '',
            };
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
