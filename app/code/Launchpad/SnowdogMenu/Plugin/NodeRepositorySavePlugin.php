<?php
/**
 * Launchpad Snowdog Menu — persist staged banner data after each node save.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin;

use Launchpad\SnowdogMenu\Model\NodeBannerManagement;
use Snowdog\Menu\Api\Data\NodeInterface;
use Snowdog\Menu\Api\NodeRepositoryInterface;

/**
 * Reads the values staged by SaveRequestProcessorPlugin::afterProcessNodeObject
 * off the saved node object and upserts/deletes the companion row. Nodes
 * saved without staged banner data (other save paths) are no-ops, so foreign
 * save flows keep working untouched.
 */
class NodeRepositorySavePlugin
{
    public function __construct(
        private readonly NodeBannerManagement $nodeBannerManagement
    ) {
    }

    public function afterSave(NodeRepositoryInterface $subject, $result, NodeInterface $node)
    {
        $staged = $node->getData(SaveRequestProcessorPlugin::STAGE_KEY);
        if (is_array($staged)) {
            $node->unsetData(SaveRequestProcessorPlugin::STAGE_KEY);
            $this->nodeBannerManagement->save(
                (int) $node->getId(),
                $staged['content'] ?? null,
                (bool) ($staged['mobile'] ?? false)
            );
        }

        return $result;
    }
}
