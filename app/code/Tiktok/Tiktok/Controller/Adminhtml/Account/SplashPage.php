<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Controller\Adminhtml\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Storage\Admin;
use JsonException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Prepare iframe url and render it
 */
class SplashPage extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Tiktok_Tiktok::general';

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Tiktok\Tiktok\Model\Storage\Admin $adminStorage
     */
    public function __construct(
        Context $context,
        private readonly TiktokApiClientBuilder $tiktokApiClientBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Admin $adminStorage
    ) {
        parent::__construct($context);
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\View\Result\Page
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws JsonException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $defaultWebsiteId = $this->storeManager->getDefaultStoreView()->getWebsiteId();
        $websiteId = (int) $this->getRequest()->getParam('website', $defaultWebsiteId);
        $website = $this->storeManager->getWebsite($websiteId);
        $this->adminStorage->set('current_website', $website);
        $this->adminStorage->set('current_website_id', $websiteId);
        $store = $this->storeManager->getStoreByWebsiteId($websiteId);
        if ($store) {
            $storeId = array_shift($store);
            $this->storeManager->setCurrentStore($storeId);
        }
        $apiBuilder = $this->tiktokApiClientBuilder->create($websiteId);
        $isLinkedToWebsite = $apiBuilder->getScopeManager()->getAccessToken() !== null;
        $this->adminStorage->set('is_linked_to_website', $isLinkedToWebsite);

        $externalData = $apiBuilder->getExternalData();
        $this->adminStorage->set('external_data', $externalData);
        $encodedData = urlencode($externalData);
        $this->adminStorage->set('encoded_external_data', $encodedData);
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        if ($switchBlock = $resultPage->getLayout()->getBlock('store_switcher')) {
            $switchBlock->setWebsiteId($websiteId)->hasDefaultOption(false);
        }

        return $resultPage;
    }
}
