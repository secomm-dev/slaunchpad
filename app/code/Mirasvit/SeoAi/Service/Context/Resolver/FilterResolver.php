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

namespace Mirasvit\SeoAi\Service\Context\Resolver;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class FilterResolver
{
    private $storeManager;

    private $attributeRepository;

    public function __construct(
        StoreManagerInterface $storeManager,
        ProductAttributeRepositoryInterface $attributeRepository
    ) {
        $this->storeManager        = $storeManager;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getFiltersData(?array $params = null): array
    {
        $filters = [];

        if (!$params || !count($params)) {
            return $filters;
        }

        foreach ($params as $code => $value) {
            try {
                $attribute = $this->attributeRepository->get($code);
            } catch (NoSuchEntityException $e) {
                // intentional no-op: unknown attribute code in request params ⇒ skip it, not a failure.
                continue;
            }

            $name = $attribute->getDefaultFrontendLabel();

            foreach ($attribute->getFrontendLabels() as $label) {
                if ($label->getStoreId() == $this->storeManager->getStore()->getId()) {
                    $name = $label->getLabel();
                    break;
                }
            }

            if (isset($value)) {
                $options = explode(',', $value);
                $optionLabels = [];

                if ($attribute->getFrontendInput() == 'price') {
                    $minValue = 100000000;
                    $maxValue = 0;

                    foreach ($options as $optionValue) {
                        $optionParts = explode('-', $optionValue);

                        if ((float)$optionParts[0] < $minValue) {
                            $minValue = (float)$optionParts[0];
                        }

                        if ($maxValue !== null && $optionParts[1] > $maxValue) {
                            $maxValue = (float)$optionParts[1];
                        }

                        if (!$optionParts[1]) {
                            $maxValue = null;
                        }
                    }

                    $optionText = 'from ' . $minValue;

                    if (!is_null($maxValue)) {
                        $optionText .= ' to ' . $maxValue;
                    }

                    $optionLabels[] = $optionText;
                } else {
                    foreach ($attribute->getOptions() as $option) {
                        if (in_array($option->getValue(), $options)) {
                            $optionLabels[] = $option->getLabel();
                        }
                    }
                }

                if (count($optionLabels)) {
                    $filters[] = $name . ': ' . implode(', ', $optionLabels);
                }
            }
        }

        return $filters;
    }
}
