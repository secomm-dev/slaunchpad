<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Controller\Adminhtml\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Backend Controller for Disconnect
 */
class Disconnect extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Tiktok_Tiktok::general';

    /**
     * Disconnect constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     */
    public function __construct(
        Context $context,
        private readonly TiktokApiClientBuilder $tiktokApiClientBuilder
    ) {
        parent::__construct($context);
    }

    /**
     * Execute method for the Disconnect functionality
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $websiteId = (int) $this->getRequest()->getParam('website_id');

        try {
            $tiktokClient = $this->tiktokApiClientBuilder->create($websiteId);
            $result = $tiktokClient->disconnect();
            if ($result['success']) {
                $this->messageManager->addSuccessMessage(
                    __('TikTok integration has been disconnected successfully.')
                );
                $tiktokClient->getScopeManager()->clearAuthData();
            } else {
                $message = $result['message'] ?? __('Failed to disconnect from TikTok.');
                $this->messageManager->addErrorMessage($message);
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error disconnecting TikTok integration: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'tiktok', 'website' => $websiteId]
        );
    }
}
