<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model\OptionSource;

use Magento\Framework\Locale\ListsInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

class SourceItemLocale implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var ListsInterface
     */
    protected $localeLists;

    /**
     * Constructor
     *
     * @param ListsInterface $localeLists
     */
    public function __construct(ListsInterface $localeLists)
    {
        $this->localeLists = $localeLists;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $locales = $this->localeLists->getOptionLocales();
        $options = [];

        foreach ($locales as $localeName) {
            $options[] = $localeName;
        }

        return $options;
    }
}
