<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized;

/**
 * Backend for serialized array data
 */
class ArraySerialized extends Serialized
{
    /**
     * Processing object before save data
     *
     * @return ArraySerialized
     */
    public function beforeSave(): ArraySerialized
    {
        $value = $this->getValue();
        if (is_array($value)) {
            unset($value['__empty']);
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
