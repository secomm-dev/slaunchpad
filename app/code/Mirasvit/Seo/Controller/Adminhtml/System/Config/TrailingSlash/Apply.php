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

namespace Mirasvit\Seo\Controller\Adminhtml\System\Config\TrailingSlash;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Service\TrailingSlashService;

class Apply extends Action
{
    private $context;

    private $resultJsonFactory;

    private $storeManager;

    private $trailingSlashService;

    public function __construct(
        Context               $context,
        JsonFactory           $resultJsonFactory,
        StoreManagerInterface $storeManager,
        TrailingSlashService  $trailingSlashService
    ) {
        $this->context              = $context;
        $this->resultJsonFactory    = $resultJsonFactory;
        $this->storeManager         = $storeManager;
        $this->trailingSlashService = $trailingSlashService;

        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->context->getAuthorization()->isAllowed('Mirasvit_Seo::seo');
    }

    public function execute(): Json
    {
        $storeId   = (int)$this->getRequest()->getParam('store_id');
        $websiteId = (int)$this->getRequest()->getParam('website_id');

        try {
            $storeIds = $this->getStoreIds($storeId, $websiteId);

            foreach ($storeIds as $storeId) {
                $this->trailingSlashService->processUrlRewrites($storeId);
            }

            $result = __('URLs have been processed.');
        } catch (Exception $e) {
            $result = __('Something went wrong.');
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData([
            'valid' => 1,
            'message' => $result,
        ]);
    }

    private function getStoreIds(?int $storeId = null, ?int $websiteId = null): array
    {
        $storeIds = [];

        if ($storeId) {
            $storeIds = [$storeId];
        } elseif ($websiteId) {
            $storeIds = $this->storeManager->getWebsite($websiteId)->getStoreIds();
        } else {
            $storeIds = array_keys($this->storeManager->getStores());
        }

        return $storeIds;
    }
}
