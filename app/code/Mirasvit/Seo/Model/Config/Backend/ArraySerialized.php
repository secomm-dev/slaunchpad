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



namespace Mirasvit\Seo\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized as SerializedArraySerialized;

class ArraySerialized extends SerializedArraySerialized
{
    /**
     * @return void
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            return;
        }
        if (empty($value)) {
            $this->setValue(false);

            return;
        }
        try {
            parent::_afterLoad();
        } catch (\Exception $e) {
            // Legacy non-JSON data; surface the raw value so the admin form
            // renders a single editable row instead of throwing a 500.
            $this->setValue([0 => $value]);
        }
    }

    /**
     * @return SerializedArraySerialized
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (is_array($value)) {
            unset($value['__empty']);
        }

        if ($this->getField() == 'noindex_pages2' && is_array($value)) {
            $value = $this->normalizeValue($value);
        }

        $this->setValue($value);

        return parent::beforeSave();
    }

    private function normalizeValue(array $value): array
    {
        uasort($value, function ($elem1, $elem2) {
            return $elem1['pattern'] > $elem2['pattern'] ? -1 : 1;
        });

        uasort($value, function ($elem1, $elem2) {
            $sortRule = strlen($elem2['pattern']) > strlen($elem1['pattern']) &&
                strrpos(str_replace(['/', '*'], [' ', ''], $elem2['pattern']), str_replace(['/', '*'], [' ', ''], $elem1['pattern'])) !== false;

            return $sortRule ? -1 : 1;
        });

        return $value;
    }
}
