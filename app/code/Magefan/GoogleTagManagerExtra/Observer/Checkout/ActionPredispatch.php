<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magefan\GoogleTagManagerExtra\Observer\Checkout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magefan\GoogleTagManager\Model\Config;
use Magefan\GoogleTagManagerExtra\Model\ServerTracker\ClientIdProvider;
use Magefan\GoogleTagManagerExtra\Model\ServerTracker\SessionIdProvider;
use Magento\Framework\App\ResourceConnection;

class ActionPredispatch implements ObserverInterface
{

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ClientIdProvider
     */
    private $clientIdProvider;

    /**
     * @var SessionIdProvider
     */
    private $sessionIdProvider;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Config $config
     * @param ClientIdProvider $clientIdProvider
     * @param SessionIdProvider $sessionIdProvider
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Config $config,
        ClientIdProvider $clientIdProvider,
        SessionIdProvider $sessionIdProvider,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        ResourceConnection $resourceConnection
    ) {
        $this->config = $config;
        $this->clientIdProvider = $clientIdProvider;
        $this->sessionIdProvider = $sessionIdProvider;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $fan = $observer->getRequest()->getFullActionName();

        if (in_array($fan, ['checkout_cart_index', 'checkout_index_index', 'firecheckout_index_index'])) {
            if ($quote = $this->checkoutSession->getQuote()) {
                $clientId = $this->clientIdProvider->get();

                $quoteDataToSave = [];

                if ($quote->getMfGtmClientId() !== $clientId) {
                    $quote->setMfGtmClientId($clientId);
                    $quoteDataToSave['mf_gtm_client_id'] = $clientId;
                }

                $sessionId = $this->sessionIdProvider->get();
                if ($quote->getMfGtmSessionId() !== $sessionId) {
                    $quote->setMfGtmSessionId($sessionId);
                    $quoteDataToSave['mf_gtm_session_id'] = $sessionId;
                }

                if ($quoteDataToSave) {
                    $this->resourceConnection->getConnection()->update(
                        $this->resourceConnection->getTableName('quote'),
                        $quoteDataToSave,
                        ['entity_id = ?' => $quote->getId()]
                    );
                }
            }
        } elseif (in_array($fan, ['checkout_onepage_success'])) {
            if ($order = $this->checkoutSession->getLastRealOrder()) {
                $orderDataToSave = [];

                $clientId = $this->clientIdProvider->get();
                if ($order->getMfGtmClientId() !== $clientId) {
                    $order->setMfGtmClientId($clientId);
                    $orderDataToSave['mf_gtm_client_id'] = $clientId;
                }

                $sessionId = $this->sessionIdProvider->get();
                if ($order->getMfGtmSessionId() !== $sessionId) {
                    $order->setMfGtmSessionId($sessionId);
                    $orderDataToSave['mf_gtm_session_id'] = $sessionId;
                }

                if ($orderDataToSave) {
                    $this->resourceConnection->getConnection()->update(
                        $this->resourceConnection->getTableName('sales_order'),
                        $orderDataToSave,
                        ['entity_id = ?' => $order->getId()]
                    );
                }
            }

        }
    }
}
