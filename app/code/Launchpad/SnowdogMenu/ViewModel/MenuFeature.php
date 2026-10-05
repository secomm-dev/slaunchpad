<?php
/**
 * TASK-08343C (SLP-245): Launchpad menu feature-content ViewModel.
 *
 * Exposes the two managed feature sources for the Launchpad menu panels:
 * the level-0 branch node's own image (Snowdog node image, resolved through
 * the vendor ImageFile URL service) and the optional Desktop feature copy CMS
 * block `launchpad-menu-feature-<nodeId>`. Keeps the vendor-managed URL
 * resolution out of the templates without duplicating it.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\ViewModel;

use Launchpad\SnowdogMenu\Model\NodeBannerManagement;
use Magento\Cms\Block\BlockByIdentifier;
use Magento\Cms\Model\Template\FilterProvider;
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

    /** @var array<int, array<int, array{content: ?string, mobile: bool}>> per-menu per-request cache */
    private array $menuBannerCache = [];

    public function __construct(
        private readonly File $nodeImageFile,
        private readonly BlockFactory $blockFactory,
        private readonly NodeBannerManagement $nodeBannerManagement,
        private readonly FilterProvider $filterProvider
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
     * Banner content for a node, after the empty-content rule: a WYSIWYG
     * shell without real text (e.g. an empty paragraph, <br>-only content)
     * counts as empty and falls through to the CMS block convention.
     */
    public function getBannerContent(NodeInterface $node): ?string
    {
        $content = $this->loadBanner($node)['content'] ?? null;
        if ($content !== null && $this->isEffectivelyEmpty($content)) {
            $content = null;
        }

        return $content;
    }

    /**
     * CMS-block fallback for the node (launchpad-menu-feature-<nodeId>),
     * only when the editor banner did not provide real content.
     */
    public function getFallbackContent(NodeInterface $node): ?string
    {
        if ($this->getBannerContent($node) !== null) {
            return null;
        }
        $cms = $this->getCmsBlockHtml($node);

        return $cms !== '' ? $cms : null;
    }

    /**
     * Whether the banner CONTENT (not the image) renders on mobile: applies
     * to both the editor content and the CMS fallback. Default No (nodes
     * without a banner row are mobile-hidden).
     */
    public function isBannerContentMobile(NodeInterface $node): bool
    {
        return $this->loadBanner($node)['mobile'];
    }

    /**
     * Render trusted Admin-authored WYSIWYG content through the Magento page
     * template filter for widget/directive/media-URL processing. The filter
     * is not an HTML sanitizer.
     */
    public function renderRichContent(string $html): string
    {
        return (string) $this->filterProvider->getPageFilter()->filter($html);
    }

    /**
     * Whether a category node is the exact category currently being viewed.
     * Non-category nodes and providers without Magento category context are
     * intentionally false.
     */
    public function isCurrentCategory(NodeInterface $node, ?object $provider = null): bool
    {
        if ((string) $node->getType() !== 'category'
            || !$provider
            || !method_exists($provider, 'isCurrentCategory')
        ) {
            return false;
        }

        return (bool) $provider->isCurrentCategory((int) $node->getNodeId());
    }

    /**
     * Whether a category node is the current category or one of its Magento
     * category-path ancestors. This keeps its menu branch visibly current on
     * descendant category pages without claiming aria-current on ancestors.
     */
    public function isCurrentCategoryPath(NodeInterface $node, ?object $provider = null): bool
    {
        if ((string) $node->getType() !== 'category'
            || !$provider
            || !method_exists($provider, 'getCurrentCategory')
        ) {
            return false;
        }

        $currentCategory = $provider->getCurrentCategory();
        if (!$currentCategory) {
            return false;
        }

        return in_array(
            (int) $node->getContent(),
            array_map('intval', $currentCategory->getPathIds()),
            true
        );
    }

    private function loadBanner(NodeInterface $node): array
    {
        // single batched read per menu (lazily populated on first lookup)
        $menuId = (int) $node->getMenuId();
        if (!isset($this->menuBannerCache[$menuId])) {
            $this->menuBannerCache[$menuId] = $this->nodeBannerManagement->getForMenu($menuId);
        }

        return $this->menuBannerCache[$menuId][(int) $node->getNodeId()]
            ?? ['content' => null, 'mobile' => false];
    }

    private function isEffectivelyEmpty(string $html): bool
    {
        $stripped = preg_replace('/<\/?p[^>]*>|<br\s*\/?>|&nbsp;|\s+/u', '', strip_tags($html, '<p><br>'));

        return $stripped === '' || $stripped === false;
    }

    /**
     * Destination URL of a branch node through the vendor node-type URL
     * resolvers (category/custom URL); empty string for types without a
     * simple destination (wrapper, CMS page) or without configured target.
     *
     * $provider must be the menu block's own configured provider instance
     * ($block->getNodeTypeProvider(...)) — its node registry carries the
     * resolved category URL data. A missing registry entry means the node has
     * no resolvable destination: the title renders as text while its trailing
     * disclosure button remains available.
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
