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

namespace Mageplaza\RMA\Model\Api;

use Mageplaza\RMA\Helper\Data;

/**
 * Class InformationManagement
 * @package Mageplaza\RMA\Model\Api
 */
class InformationManagement extends AbstractManagement
{
    /**
     * @inheritdoc
     */
    public function get()
    {
        $data[] = [
            'reason' => $this->getData('rma/reason'),
            'solution' => $this->getData('rma/solution'),
            'additional_field' => $this->getData('rma/additional_field')
        ];

        return $data;
    }

    /**
     * @param string $config
     *
     * @return array
     */
    public function getData($config)
    {
        $items = Data::jsonDecode($this->helperData->getRequestConfig($config));
        $options = [];

        if (count($items)) {
            foreach ($items['name'] as $value => $content) {
                $options[] = compact('value', 'content');
            }
        }

        return $options;
    }
}
