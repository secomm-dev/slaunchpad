<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — `secomm_shipping_zone` mapper. JSON code-list columns
 * are serialized/deserialized here so the model only ever sees string arrays.
 */
class Zone extends AbstractDb
{
    private const CODE_LIST_FIELDS = ['include_province_codes', 'include_ward_codes', 'exclude_ward_codes'];

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('secomm_shipping_zone', 'zone_id');
    }

    /**
     * Serialize code lists before write (columns are NOT NULL JSON — an empty list is `[]`).
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        foreach (self::CODE_LIST_FIELDS as $field) {
            $value = $object->getData($field);
            if (!is_array($value)) {
                $value = [];
            }
            $object->setData($field, json_encode(array_values(array_map('strval', $value)), JSON_THROW_ON_ERROR));
        }

        return parent::_beforeSave($object);
    }
}
