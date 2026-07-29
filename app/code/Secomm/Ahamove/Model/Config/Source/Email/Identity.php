<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\Config\Source\Email;

use Magento\Framework\Data\OptionSourceInterface;
/**
 * @api
 * @since 100.0.2
 */
class Identity implements OptionSourceInterface
{
    /**
     * Email Identity options
     *
     * @var array
     */
    protected $options = null;

    /**
     * Configuration structure
     *
     * @var \Magento\Config\Model\Config\Structure
     */
    protected $configStructure;

    /**
     * @param \Magento\Config\Model\Config\Structure $configStructure
     */
    public function __construct(\Magento\Config\Model\Config\Structure $configStructure)
    {
        $this->configStructure = $configStructure;
    }

    /**
     * Retrieve list of options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [];
            // Default Magento
            /** @var $section \Magento\Config\Model\Config\Structure\Element\Section */
            $section = $this->configStructure->getElement('trans_email');

            /** @var $group \Magento\Config\Model\Config\Structure\Element\Group */
            foreach ($section->getChildren() as $group) {
                $this->options[] = [
                    'value' => preg_replace('#^ident_(.*)$#', '$1', $group->getId()),
                    'label' => $group->getLabel(),
                ];
            }
            ksort($this->options);
        }
        return $this->options;
    }
}
