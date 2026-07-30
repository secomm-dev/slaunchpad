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


use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoAi\Model\ConfigProvider;
use Mirasvit\SeoAi\Service\Context\Resolver\FilterResolver;

class CategoryContext extends AbstractContext
{
    const IGNORED_FIELDS = [
        "is_active", "url_key", "path", "include_in_menu", "position", "level", "children_count", "display_mode",
        "landing_page", "is_anchor", "custom_use_parent_settings", "custom_apply_to_products", "custom_design",
        "page_layout", "layout_update", "custom_layout_update", "custom_layout_update_file"
    ];

    private $categoryRepository;

    private $filterResolver;

    public function __construct(
        FilterResolver $filterResolver,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ConfigProvider $configProvider,
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->filterResolver     = $filterResolver;

        parent::__construct($storeManager, $configProvider, $objectManager, $moduleManager);
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getContext(?int $entityId = null, ?array $params = null): array
    {
        $context = parent::getContext($entityId, $params);

        try {
            $category = $this->categoryRepository->get($entityId, $this->storeManager->getStore()->getId());
        } catch (NoSuchEntityException $e) {
            return $context;
        }

        $attributes = $category->getAttributes();
        $data       = [];

        foreach ($attributes as $attribute) {
            if (in_array($attribute->getAttributeCode(), self::IGNORED_FIELDS)) {
                continue;
            }

            $value = $attribute->getFrontend()->getValue($category);
            if ($value instanceof Phrase) {
                $value = (string)$value;
            }

            if (is_string($value) && strlen(trim($value)) && $attribute->getStoreLabel()) {
                if ($value == "No") { //fix me. Multi lang
                    continue;
                }
                $value = $this->clear($value);
                if (trim($value) == "") {
                    continue;
                }
                $data[] = [
                    'id'    => 'category.' . $attribute->getAttributeCode(),
                    'code'  => $attribute->getAttributeCode(),
                    'label' => $attribute->getStoreLabel(),
                    'value' => $value,
                ];
            }
        }

        $context['category'] = $data;

        $filters = $this->filterResolver->getFiltersData($params);

        if (count($filters)) {
            $context['applied_filters'][] = [
                'id'    => 'filters.applied',
                'label' => 'Applied Filters',
                'value' => $filters
            ];
        }

        return $context;
    }
}
