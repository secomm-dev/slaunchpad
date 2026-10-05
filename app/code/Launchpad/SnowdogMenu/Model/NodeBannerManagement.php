<?php
/**
 * Launchpad Snowdog Menu — node banner persistence/reader service.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Model;

use Launchpad\SnowdogMenu\Model\ResourceModel\NodeBanner as NodeBannerResource;
use Magento\Framework\App\Cache\Manager as CacheManager;

/**
 * Single service for reading (batched) and persisting per-node banner data.
 *
 * Cache invalidation contract: save() reports whether the row actually
 * changed; the caller (SaveRequestProcessorPlugin::afterSaveData) invalidates
 * the affected cache types ONCE after the whole tree save succeeds. No purge
 * of foreign menus is performed — node deletions remove banner rows through
 * the snowmenu_node FK CASCADE, which cannot touch other menus' rows.
 */
class NodeBannerManagement
{
    /** Menu block markup embeds banner content; FPC pages embed the menu. */
    private const CACHE_TYPES = ['block_html', 'full_page'];

    private bool $bannerCacheDirty = false;

    public function __construct(
        private readonly NodeBannerFactory $nodeBannerFactory,
        private readonly NodeBannerResource $nodeBannerResource,
        private readonly CacheManager $cacheManager
    ) {
    }

    /**
     * All banner rows of a menu keyed by node id (one query — batched read
     * for a full panel/drawer render).
     *
     * @return array<int, array{content: ?string, mobile: bool}>
     */
    public function getForMenu(int $menuId): array
    {
        return $this->nodeBannerResource->loadForMenu($menuId);
    }

    /**
     * Batch read keyed by node id; absent ids mean "no banner row" (defaults).
     *
     * @param int[] $nodeIds
     * @return array<int, array{content: ?string, mobile: bool}>
     */
    public function getForNodes(array $nodeIds): array
    {
        return $this->nodeBannerResource->loadForNodes($nodeIds);
    }

    /**
     * Upsert/delete the banner row for a node. A null content deletes the row
     * (the node carries no banner). Returns whether storage actually changed
     * (identical values are no-ops so unchanged saves cause no invalidation).
     */
    public function save(int $nodeId, ?string $content, bool $showOnMobile): bool
    {
        /** @var NodeBanner $banner */
        $banner = $this->nodeBannerFactory->create();
        $this->nodeBannerResource->load($banner, $nodeId, NodeBanner::NODE_ID);

        $normalized = $content === null ? null : (string) $content;
        $mobile = $showOnMobile ? 1 : 0;

        // defaults (no content, mobile No): the row is deleted so legacy
        // nodes and "banner removed" nodes behave identically
        if ($normalized === null && !$mobile) {
            if ($banner->getId()) {
                $this->nodeBannerResource->delete($banner);
                $this->bannerCacheDirty = true;
                return true;
            }
            return false;
        }

        if (!$banner->getId()) {
            // non-auto-increment primary key: the resource performs INSERT
            // only when the model is explicitly flagged new
            $banner->isObjectNew(true);
        } elseif ((string) $banner->getBannerContent() === $normalized
            && (int) $banner->getData(NodeBanner::SHOW_ON_MOBILE) === $mobile
        ) {
            return false;
        }

        $banner->setData(NodeBanner::NODE_ID, $nodeId);
        $banner->setData(NodeBanner::BANNER_CONTENT, $normalized);
        $banner->setData(NodeBanner::SHOW_ON_MOBILE, $mobile);
        $this->nodeBannerResource->save($banner);
        $this->bannerCacheDirty = true;

        return true;
    }

    /**
     * Invalidate the caches that embed menu/banner markup — once per tree
     * save, and only when at least one banner row actually changed.
     */
    public function invalidateIfDirty(): void
    {
        if (!$this->bannerCacheDirty) {
            return;
        }
        $this->bannerCacheDirty = false;
        $this->cacheManager->clean(self::CACHE_TYPES);
    }
}
