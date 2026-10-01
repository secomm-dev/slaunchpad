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
 * @category  Mageplaza
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Mageplaza\RMA\Helper\Data;

/**
 * Class AdditionalField
 * @package Mageplaza\RMA\Model\Config\Backend
 */
class AdditionalField extends Value
{
    /**
     * Unset array element with '__empty' key
     *
     * @return Value
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function beforeSave()
    {
        /** @var array|string $value */
        $value = $this->getValue();
        unset($value['__empty']);
        if ($value) {
            /** @var array $item */
            /** @noinspection ForeachSourceInspection */
            foreach ($value['name'] as &$item) {
                $item['sort'] = (int)$item['sort'];
                $item['is_require'] = isset($item['is_require']) ? (int)$item['is_require'] : 0;
            }
            unset($item);
        }
        $this->setValue($value);
        if (is_array($this->getValue())) {
            $this->setValue(Data::jsonEncode($this->getValue()));
        }

        return parent::beforeSave();
    }

    /**
     * @return void
     */
    protected function _afterLoad()
    {
        if (!is_array($this->getValue())) {
            $value = $this->getValue();
            $this->setValue(empty($value) ? false : Data::jsonDecode($value));
        }
    }
}
