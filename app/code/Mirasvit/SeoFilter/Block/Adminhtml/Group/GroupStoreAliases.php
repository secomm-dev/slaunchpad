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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);


namespace Mirasvit\SeoFilter\Block\Adminhtml\Group;

use Mirasvit\LayeredNavigation\Api\Data\GroupInterface;
use Magento\Backend\Block\Template;
use Mirasvit\SeoFilter\Service\RewriteService;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;

class GroupStoreAliases extends Template
{
    protected $_template = 'Mirasvit_SeoFilter::group.phtml';
    
    private   $rewriteService;

    private   $configProvider;

    private   $moduleManager;

    public function __construct(
        RewriteService   $rewriteService,
        ConfigProvider   $configProvider,
        ModuleManager    $moduleManager,
        Template\Context $context
    ) {
        $this->rewriteService  = $rewriteService;
        $this->configProvider  = $configProvider;
        $this->moduleManager   = $moduleManager;
        
        parent::__construct($context);
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface[]
     */
    public function getStores(): array
    {
        return $this->_storeManager->getStores();
    }

    public function getAliases(): array
    {
        $aliases = [];
        $id      = (int)$this->getRequest()->getParam('group_id');

        if (
            !$id
            || !class_exists('Mirasvit\LayeredNavigation\Repository\GroupRepository')
            || !$this->moduleManager->isEnabled('Mirasvit_LayeredNavigation')
        ) {
            return $aliases;
        }

        /** @var \Mirasvit\LayeredNavigation\Repository\GroupRepository $groupRepository */
        $groupRepository = ObjectManager::getInstance()->create('Mirasvit\LayeredNavigation\Repository\GroupRepository');

        /** @var \Mirasvit\LayeredNavigation\Api\Data\GroupInterface|null $group */
        $group = $groupRepository->get($id);

        if (!$group) {
            return $aliases;
        }

        $attributeCode = $group->getAttributeCode();

        if (!$this->configProvider->isAttributeEnabled($attributeCode)) {
            return $aliases;
        }

        $optionCode = $group->getCode();
        
        foreach ($this->getStores() as $store) {
            $storeId = intval($store->getId());
            $aliases[$storeId] = $this->rewriteService->getOptionRewrite($attributeCode, $optionCode, $storeId, false)->getRewrite();
        }
        
        return $aliases;
    }
}
