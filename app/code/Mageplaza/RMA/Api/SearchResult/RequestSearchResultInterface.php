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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Api\SearchResult;

/**
 * Interface RequestSearchResultInterface
 * @package Mageplaza\RMA\Api\SearchResult
 */
interface RequestSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * @return \Mageplaza\RMA\Api\Data\RequestInterface[]
     */
    public function getItems();

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestInterface[] $items
     *
     * @return $this
     */
    public function setItems(?array $items = null);
}
