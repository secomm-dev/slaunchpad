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


declare(strict_types=1);

namespace Mirasvit\Seo\Service\TemplateEngine\Data;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;

class FilterData extends AbstractData
{
    private $pageFilters = [];

    private $layerResolver;

    public function __construct(
        LayerResolver $layerResolver
    ) {
        $this->layerResolver = $layerResolver;

        parent::__construct();
    }

    public function getTitle(): string
    {
        return (string)__('Filter Data');
    }

    public function getVariables(): array
    {
        return [
            'selected_options',
            'named_selected_options',
        ];
    }

    public function getValue(string $attribute, array $additionalData = []): ?string
    {
        if (class_exists('Manadev\LayeredNavigation\EngineFilter')) {
            $filters = $this->getManaDevFilters();
        } else {
            $filters = $this->getFilters();
        }

        switch ($attribute) {
            case 'selected_options':
                $value = [];
                foreach ($filters as $options) {
                    $value[] = implode(', ', $options);
                }

                return implode(', ', $value);

            case 'named_selected_options':
                $value = [];
                foreach ($filters as $label => $options) {
                    $value[] = $label . ': ' . implode(', ', $options);
                }

                return implode(', ', $value);
            default:
                return null;
        }
    }

    public function setPageFilters(array $filters): array
    {
        $this->pageFilters = $filters;

        return $filters;
    }

    public function getPageFilters(): array
    {
        if (!$this->pageFilters) {
            return $this->layerResolver->get()->getState()->getFilters();
        }

        return $this->pageFilters;
    }

    private function getFilters(): array
    {
        $filters = $this->getPageFilters();
        if (!is_array($filters)) {
            $filters = [];
        }

        $result = [];

        foreach ($filters as $filter) {
            if (!$filter->getData('filter')) {
                continue; #to prevent "The filter must be an object. Please set a correct filter" error.
            }

            if (!$filter->getFilter()->getData('attribute_model')) {
                continue;
            }

            $name = $filter->getName();

            if (is_scalar($filter->getData('label')) ||
                $this->isPriceFilter($filter)) {
                $selected = strip_tags((string)$filter->getData('label'));

                $result[$name][] = $selected;
            }
        }

        return $result;
    }

    private function getManaDevFilters(): array
    {
        $filters = [];
        foreach ($this->layerResolver->get()->getState()->getFilters() as $filter) {
            /** @var mixed $filter */
            if ($filter->isApplied()) {
                $filters[] = $filter;
            }
        }

        $result = [];

        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $productResourceFactory = $objectManager->get('Magento\Catalog\Model\ResourceModel\ProductFactory');
        $productResource = $productResourceFactory !== null ? $productResourceFactory->create() : null;

        foreach ($filters as $filter) {
            if (!$filter->getFilter()) {
                continue; #to prevent "The filter must be an object. Please set a correct filter" error.
            }

            if ($filter->getFilter()->getData('param_name') != 'price' && $productResource !== null) {
                $attribute = $productResource->getAttribute($filter->getFilter()->getData('param_name'));

                $name = $filter->getName();
                foreach ($filter->getAppliedOptions() as $key => $value) {
                    if ($attribute !== false && $attribute->usesSource()) {
                        $optionText = $attribute->getSource()->getOptionText($value);
                    } else {
                        $optionText = $value;
                    }
                    $result[$name][] = $optionText;
                }
            }
        }

        return $result;
    }

    /**
     * @param object $filter
     *
     * @return bool
     */
    private function isPriceFilter($filter): bool
    {
        $filterObject = $filter->getData('filter');
        if ($filterObject instanceof \Magento\CatalogSearch\Model\Layer\Filter\Price) {
            return true;
        }
        if (is_object($filterObject) && strpos(get_class($filterObject), '\Mirasvit\LayeredNavigation\Model\Layer\Filter\Price') !== false) {
            return true;
        }
        return false;
    }
}
