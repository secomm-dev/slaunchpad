<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Source;

use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Returns options for the custom_labels mapping
 */
class ProductAttributes implements OptionSourceInterface
{
    /**
     * ProductAttributes constructor.
     *
     * @param \Magento\Eav\Model\Config $eavConfig
     */
    public function __construct(protected Config $eavConfig)
    {
    }

    /**
     * Retrieve list of product attributes as options.
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function toOptionArray(): array
    {
        $attributes = $this->eavConfig->getEntityType('catalog_product')->getAttributeCollection();
        $options = [
            [
                'value' => null,
                'label' => 'Select product attribute to map']];

        foreach ($attributes as $attribute) {
            /** @var AbstractAttribute $attribute */
            if ($attribute->getFrontendLabel()) {
                $options[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'];
            }
        }

        return $options;
    }
}
