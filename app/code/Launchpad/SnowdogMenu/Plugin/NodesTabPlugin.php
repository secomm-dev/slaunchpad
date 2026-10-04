<?php
/**
 * Launchpad Snowdog Menu — pre-fill banner values in the node editor JSON.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin;

use Launchpad\SnowdogMenu\Model\NodeBannerManagement;
use Snowdog\Menu\Block\Adminhtml\Edit\Tab\Nodes;

/**
 * Appends `banner_content` / `show_banner_content_mobile` to every node in
 * the editor payload (batched read) so the overridden Vue form binds the
 * stored values; nodes without a banner row get the No default.
 */
class NodesTabPlugin
{
    public function __construct(
        private readonly NodeBannerManagement $nodeBannerManagement
    ) {
    }

    public function afterRenderNodes(Nodes $subject, array $result): array
    {
        $ids = [];
        $collectIds = function (array $nodes) use (&$collectIds, &$ids): void {
            foreach ($nodes as $node) {
                if (isset($node['id'])) {
                    $ids[] = (int) $node['id'];
                }
                if (!empty($node['columns']) && is_array($node['columns'])) {
                    $collectIds($node['columns']);
                }
            }
        };
        $collectIds($result);

        if (!$ids) {
            return $result;
        }

        $banners = $this->nodeBannerManagement->getForNodes($ids);

        $decorate = function (array $nodes) use (&$decorate, $banners): array {
            foreach ($nodes as &$node) {
                $id = (int) ($node['id'] ?? 0);
                $node['banner_content'] = $banners[$id]['content'] ?? null;
                $node['show_banner_content_mobile'] = isset($banners[$id]) && $banners[$id]['mobile'] ? 1 : 0;
                if (!empty($node['columns']) && is_array($node['columns'])) {
                    $node['columns'] = $decorate($node['columns']);
                }
            }
            unset($node);
            return $nodes;
        };

        return $decorate($result);
    }
}
