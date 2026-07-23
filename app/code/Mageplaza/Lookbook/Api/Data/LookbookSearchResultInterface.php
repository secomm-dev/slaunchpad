<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Api\Data;

/**
 * Interface LookbookSearchResultInterface
 * @package Mageplaza\Lookbook\Api\Data
 */
interface LookbookSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * @return \Mageplaza\Lookbook\Api\Data\LookbookInterface[]
     */
    public function getItems();

    /**
     * @param \Mageplaza\Lookbook\Api\Data\LookbookInterface[] $items
     *
     * @return $this
     */
    public function setItems(?array $items = null);
}
