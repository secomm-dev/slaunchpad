<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Plugin;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Batch-load media gallery on category product lists (TASK-0NNZCW v4.3) so the
 * card image slider (item.phtml) renders on PLP without per-product queries.
 */
class ListProductGallery
{
    /**
     * @param ListProduct $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetLoadedProductCollection(ListProduct $subject, $result)
    {
        if ($result instanceof Collection) {
            $result->addMediaGalleryData();
        }

        return $result;
    }
}
