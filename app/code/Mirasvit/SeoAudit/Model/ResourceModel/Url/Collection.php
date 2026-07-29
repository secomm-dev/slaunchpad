<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Model\ResourceModel\Url;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Mirasvit\SeoAudit\Model\Url::class,
            \Mirasvit\SeoAudit\Model\ResourceModel\Url::class
        );
    }

    public function addParentIdFilter(int $parentId): self
    {
        $connection = $this->getConnection();

        $relationSelect = $connection->select()
            ->from($this->getTable(UrlInterface::PARENT_TABLE_NAME), UrlInterface::PARENT_REL_URL_ID)
            ->where(UrlInterface::PARENT_REL_PARENT_ID . ' = ?', $parentId);

        $this->getSelect()->where(
            'main_table.' . UrlInterface::ID . ' IN (' . $relationSelect . ')'
        );

        return $this;
    }
}
