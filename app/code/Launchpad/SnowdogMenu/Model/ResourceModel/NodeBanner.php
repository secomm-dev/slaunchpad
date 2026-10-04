<?php
/**
 * Launchpad Snowdog Menu — node banner resource model.
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Model\ResourceModel;

use Launchpad\SnowdogMenu\Model\NodeBanner as NodeBannerModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class NodeBanner extends AbstractDb
{
    /**
     * Non-auto-increment PK: keeps the node_id value in INSERT binds and
     * switches AbstractDb to the INSERT path for models flagged new
     * (isObjectNew).
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;
    protected $_useIsObjectNew = true;

    protected function _construct(): void
    {
        $this->_init('launchpad_snowdog_menu_node_banner', 'node_id');
    }

    /**
     * Batch load banner rows keyed by node id (single SELECT — no N+1 on
     * panel renders or the editor pre-fill).
     *
     * @param int[] $nodeIds
     * @return array<int, array{content: ?string, mobile: bool}>
     */
    public function loadForNodes(array $nodeIds): array
    {
        if (!$nodeIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [NodeBannerModel::NODE_ID, NodeBannerModel::BANNER_CONTENT, NodeBannerModel::SHOW_ON_MOBILE])
            ->where(NodeBannerModel::NODE_ID . ' IN (?)', array_map('intval', $nodeIds));

        $out = [];
        foreach ($connection->fetchAll($select) as $row) {
            $out[(int) $row[NodeBannerModel::NODE_ID]] = [
                'content' => $row[NodeBannerModel::BANNER_CONTENT],
                'mobile' => (int) $row[NodeBannerModel::SHOW_ON_MOBILE] === 1,
            ];
        }

        return $out;
    }

    /**
     * All banner rows of a menu, keyed by node id (single SELECT per render).
     */
    public function loadForMenu(int $menuId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['b' => $this->getMainTable()], [NodeBannerModel::NODE_ID, NodeBannerModel::BANNER_CONTENT, NodeBannerModel::SHOW_ON_MOBILE])
            ->join(['n' => 'snowmenu_node'], 'n.node_id = b.node_id', [])
            ->where('n.menu_id = ?', $menuId);

        $out = [];
        foreach ($connection->fetchAll($select) as $row) {
            $out[(int) $row[NodeBannerModel::NODE_ID]] = [
                'content' => $row[NodeBannerModel::BANNER_CONTENT],
                'mobile' => (int) $row[NodeBannerModel::SHOW_ON_MOBILE] === 1,
            ];
        }

        return $out;
    }
}
