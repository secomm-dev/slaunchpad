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


use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Helper\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoAi\Model\ConfigProvider;

class CmsPageContext extends AbstractContext
{
    private $cmsPageRepository;

    private $scopeConfig;

    public function __construct(
        PageRepositoryInterface $cmsPageRepository,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ConfigProvider $configProvider,
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager
    ) {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->scopeConfig       = $scopeConfig;

        parent::__construct($storeManager, $configProvider, $objectManager, $moduleManager);
    }

    public function getContext(?int $entityId = null, ?array $params = null): array
    {
        $context = parent::getContext($entityId, $params);

        if (!$entityId) {
            $entityId = $this->scopeConfig->getValue(
                Page::XML_PATH_HOME_PAGE,
                ScopeInterface::SCOPE_STORE
            );
        }

        $cmsPage = $this->cmsPageRepository->getById($entityId);
        $data    = [];

        $cmsPage->getContent();

        foreach(['title', 'content_heading', 'content'] as $field) {
            if ($value = $cmsPage->getData($field)) {
                $data[] = [
                    'id'    => 'page.' . $field,
                    'label' => $this->fieldNameToLabel($field),
                    'value' => $this->stripTags($value)
                ];
            }
        }

        if (count($data)) {
            $context['page'] = $data;
        }

        return $context;
    }

    private function fieldNameToLabel(string $fieldName): string
    {
        return implode(' ', array_map('ucfirst', explode('_', $fieldName)));
    }
}
