<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Controller\Adminhtml\Ghtk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Fee\FeeRequestMapper;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * Connectivity/health check (AC-005): validates the API token + pickup
 * configuration by issuing a real fee request with a sample VN destination.
 * Uses the same origin provider chain as the storefront rate path (SL-015),
 * so the probe reports exactly what checkout would send.
 */
class TestConnection extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_Ghtk::config';

    public function __construct(
        Context $context,
        private GhtkApiClient $apiClient,
        private GhtkOriginProvider $originProvider,
        private PickupAddressResolver $pickupResolver,
        private FeeRequestMapper $requestMapper,
        private ShippingContextFactory $contextFactory,
        private GhtkConfig $config,
        private MaskingLogger $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setRefererUrl();

        $storeId = (int) $this->getRequest()->getParam('store', 0);
        $context = $this->contextFactory->create($storeId > 0 ? $storeId : null, 'ghtk');
        $origin = $this->originProvider->resolve($context);

        $pickup = $this->pickupResolver->resolve($origin);
        if ($pickup === null) {
            $this->messageManager->addErrorMessage(
                __('Pickup configuration is invalid: set a Pickup Address ID, or Province + Ward, or configure the Magento Shipping Origin.')
            );

            return $resultRedirect;
        }

        // Sample VN destination for the probe.
        $sampleDest = new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true);

        try {
            $this->apiClient->getFee(
                $this->requestMapper->map($sampleDest, $pickup, 1000, 0.0, $this->config->getTransport($storeId > 0 ? $storeId : null)),
                $storeId > 0 ? $storeId : null
            );
            $this->messageManager->addSuccessMessage(
                __('GHTK connection OK — fee request succeeded with the current credentials and pickup config.')
            );
        } catch (GhtkApiException $e) {
            $this->logger->warning('GHTK connection test failed.', ['reason' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(
                __('GHTK connection failed: %1', $e->getMessage())
            );
        }

        return $resultRedirect;
    }
}
