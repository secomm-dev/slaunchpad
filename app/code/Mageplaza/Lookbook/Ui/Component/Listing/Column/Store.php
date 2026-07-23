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

namespace Mageplaza\Lookbook\Ui\Component\Listing\Column;

/**
 * Class Store
 * @package Mageplaza\Lookbook\Ui\Component\Listing\Column
 */
class Store extends \Magento\Store\Ui\Component\Listing\Column\Store
{
    /**
     * Get data
     *
     * @param array $item
     *
     * @return string
     */
    protected function prepareItem(array $item)
    {
        $content = '';
        $storeKey = 'store_ids';
        $origStores = $item[$storeKey];
        if (!is_array($origStores)) {
            $origStores = explode(',', $item[$storeKey]);
        }
        if (in_array('0', $origStores, true)) {
            return __('All Store Views');
        }
        $data = $this->systemStore->getStoresStructure(false, $origStores);

        foreach ($data as $website) {
            $content .= '<b>' . $website['label'] . '</b><br/>';
            foreach ($website['children'] as $group) {
                $content .= str_repeat('&nbsp;', 3) . '<b>' . $this->escaper->escapeHtml($group['label']) . '</b><br/>';
                foreach ($group['children'] as $store) {
                    $content .= str_repeat('&nbsp;', 6) . $this->escaper->escapeHtml($store['label']) . '<br/>';
                }
            }
        }

        return $content;
    }
}
