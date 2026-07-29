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



namespace Mirasvit\SeoContent\Model\ResourceModel\Rewrite;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\Store;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Model\Rewrite;
use Mirasvit\SeoContent\Model\ResourceModel\Rewrite as RewriteResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(
            Rewrite::class,
            RewriteResource::class
        );
    }

    public function addStoreFilter($store): self
    {
        if ($store instanceof Store) {
            $id = $store->getId();
        } else {
            $id = $store;
        }

        $this->getSelect()->where('FIND_IN_SET(' . $id . ', store_ids) OR store_ids = 0');

        return $this;
    }

    public function addSitemapFilter(): self
    {
        $this->addFieldToFilter(RewriteInterface::ADD_TO_SITEMAP, ['neq' => RewriteInterface::SITEMAP_NO]);

        return $this;
    }
}
