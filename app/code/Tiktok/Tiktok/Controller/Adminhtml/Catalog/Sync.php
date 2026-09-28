<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Controller\Adminhtml\Catalog;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Manages sync action for TikTok catalog
 */
class Sync extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Sync construct
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager $syncFlagManager
     */
    public function __construct(
        Context $context,
        private readonly TiktokApiClientBuilder $tiktokApiClientBuilder,
        private readonly SyncFlagManager $syncFlagManager
    ) {
        parent::__construct($context);
    }

    /**
     * Execution of catalog sync
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $websiteId = (int)$this->getRequest()->getParam('website_id', 1);
        $tiktokApiClient = $this->tiktokApiClientBuilder->create($websiteId);

        $profile = $tiktokApiClient->getProfile();
        if (!isset($profile['bc_id'], $profile['catalog_id'])) {
            $this->messageManager->addErrorMessage(
                __('Your TikTok profile isn\'t already created. Please relink your account.')
            );
            return $resultRedirect->setPath(
                'admin/system_config/edit',
                ['section' => 'tiktok', 'website' => $websiteId]
            );
        }

        try {
            $feedId = $tiktokApiClient->getScopeManager()->getFeedId();
            if (!$feedId) {
                $response = $tiktokApiClient->createFeed(
                    $profile['bc_id'],
                    $profile['catalog_id'],
                    'OVERWRITE'
                );
                if (!$response
                    || !$response['message'] == 'OK'
                    || !in_array($response['code'], [0, 20001])
                    || !isset($response['data']['feed_id'])
                ) {
                    $this->messageManager->addErrorMessage(
                        __('Something went wrong with your request to create feed. Please try again later.')
                    );
                } else {
                    $feedId = $response['data']['feed_id'];
                    $tiktokApiClient->getScopeManager()->setFeedId($feedId);
                }
            }
        } catch (GuzzleException|LocalizedException $e) {
            $this->messageManager->addErrorMessage(__('Error creating feed: %1', $e->getMessage()));
            return $resultRedirect->setPath(
                'admin/system_config/edit',
                ['section' => 'tiktok', 'website' => $websiteId]
            );
        }

        try {
            $this->syncFlagManager->setCatalogSyncFlag($websiteId);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Failed adding export to CSV: %1', $e->getMessage()));
            return $resultRedirect->setPath(
                'admin/system_config/edit',
                ['section' => 'tiktok', 'website_id' => $websiteId]
            );
        }

        $this->messageManager->addSuccessMessage(__('Sync action triggered.'));
        return $resultRedirect->setPath(
            'adminhtml/system_config/edit/section/tiktok',
            ['section' => 'tiktok', 'website' => $websiteId]
        );
    }
}
