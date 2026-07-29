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

namespace Mirasvit\SeoAutolink\Api\Data;

/**
 * @api
 */
interface LinkSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get links list.
     *
     * @return \Mirasvit\SeoAutolink\Api\Data\LinkInterface[]
     */
    public function getItems();

    /**
     * Set links list.
     *
     * @param \Mirasvit\SeoAutolink\Api\Data\LinkInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
