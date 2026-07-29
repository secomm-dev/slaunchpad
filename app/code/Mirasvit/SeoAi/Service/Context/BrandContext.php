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


namespace Mirasvit\SeoAi\Service\Context;


class BrandContext extends AbstractContext
{
    public function getContext(?int $entityId = null, ?array $params = null): array
    {
        $context = parent::getContext($entityId, $params);

        if ($this->moduleManager->isEnabled('Mirasvit_Brand')) {
            if ($entityId) {
                $context['brand'] = $this->getMirasvitBrandContext($entityId);
            } else {
                $context['all_brands'] = $this->getMirasvitAllBrandsContext();
            }
        }

        return $context;
    }

    private function getMirasvitBrandContext(int $entityId): array
    {
        $data = [];

        $brandRepository = $this->objectManager->get('Mirasvit\Brand\Repository\BrandRepository');
        $brand           = $brandRepository->get($entityId);

        if (!$brand) {
            return $data;
        }

        $brandPage = $brand->getPage();

        if (!$brandPage || !$brandPage->getId()) {
            $data = [
                [
                    'id'    => 'brand.name',
                    'label' => 'Brand Name',
                    'value' => $this->stripTags($brand->getLabel())
                ]
            ];
        } else {
            $data = [
                [
                    'id'    => 'brand.name',
                    'label' => 'Brand Name',
                    'value' => $this->stripTags($brandPage->getBrandTitle())
                ],
                [
                    'id'    => 'brand.description',
                    'label' => 'Brand Description',
                    'value' => $this->stripTags($brandPage->getBrandDescription())
                ]
            ];
        }

        return $data;
    }

    private function getMirasvitAllBrandsContext(): array
    {
        $brandRepository = $this->objectManager->get('Mirasvit\Brand\Repository\BrandRepository');
        
        $visibleBrands = [];
        
        foreach ($brandRepository->getList() as $brand) {
            $visibleBrands[] = $this->stripTags($brand->getLabel());
        }
        
        return [
            [
                'id'    => 'brands.all',
                'label' => 'List of brands',
                'value' => implode(', ', $visibleBrands)
            ]
        ];
    }
}
