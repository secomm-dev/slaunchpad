<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Cron;

use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData;
use Tiktok\Tiktok\Logger\TiktokLogger;

class PartnerDataSync
{
    /**
     * @var \Magento\Store\Model\ResourceModel\Store\CollectionFactory
     */
    private CollectionFactory $storeCollectionFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder
     */
    private TiktokApiClientBuilder $tiktokApiClientBuilder;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData
     */
    private PartnerData $partnerData;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * Init dependencies
     *
     * @param \Magento\Store\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData $partnerData
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(
        CollectionFactory $storeCollectionFactory,
        TiktokApiClientBuilder $tiktokApiClientBuilder,
        PartnerData $partnerData,
        TiktokLogger $logger
    ) {
        $this->storeCollectionFactory = $storeCollectionFactory;
        $this->tiktokApiClientBuilder = $tiktokApiClientBuilder;
        $this->partnerData = $partnerData;
        $this->logger = $logger;
    }

    /**
     * Sync partner data
     *
     * @return void
     * @throws \Exception
     */
    public function execute(): void
    {
        $this->logger->info('Starting TikTok partner data syncing');
        $stores = $this->storeCollectionFactory->create()
            ->setWithoutDefaultFilter();

        foreach ($stores as $store) {
            $websiteId = $store->getWebsiteId();
            $builder = $this->tiktokApiClientBuilder->create((int) $websiteId);
            $scopeManager = $builder->getScopeManager();
            if (!$scopeManager->isConfigured()) {
                $this->logger->info('Tiktok is not configured for website ID: ' . $websiteId);
                $this->logger->info('Skipping partner data sync');
                continue;
            }

            $storeId = (string)$store->getId();
            $partnerData = $this->partnerData->getInfo($storeId);
            $this->logger->info('Finished getting partner data');
            $this->logger->info('Sending partner data to Tiktok');
            $builder->sendTrustSignals($partnerData);
            $this->logger->info('Completed Partner data sync');
        }
    }
}
