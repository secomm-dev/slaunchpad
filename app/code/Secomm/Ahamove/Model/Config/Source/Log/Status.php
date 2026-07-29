<?php
/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source\Log;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    const STATUS_FAILED = 0;
    const STATUS_SUCCESS = 1;


    const STATUS_FAILED_LABEL = 'Failed';
    const STATUS_SUCCESS_LABEL = 'Success';

    /**
     * @var array
     */
    protected $options;

    public function toOptionArray()
    {
        if ($this->options === null) {
            foreach ($this->getOptions() as $value => $label) {
                $this->options[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }
        }
        return $this->options;
    }

    public function getOptions()
    {
        return [
            self::STATUS_FAILED => self::STATUS_FAILED_LABEL,
            self::STATUS_SUCCESS => self::STATUS_SUCCESS_LABEL,
        ];
    }
}
