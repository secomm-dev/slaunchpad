<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Cron;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Catalog\Export\CsvExport;
use Tiktok\Tiktok\Model\Catalog\Export\FileService;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory;
use Magento\Store\Model\Website;
use Magento\Framework\Message\ManagerInterface;

/**
 * Full catalog sync for specified website ID
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
class FullCatalogSync
{
    /**
     * FullCatalogSync construct
     *
     * @param \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager $syncFlagManager
     * @param \Tiktok\Tiktok\Model\Catalog\Export\CsvExport $csvExport
     * @param \Tiktok\Tiktok\Model\Catalog\Export\FileService $fileService
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $apiClientBuilder
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        private readonly CollectionFactory $websiteCollectionFactory,
        private readonly TiktokLogger $logger,
        private readonly SyncFlagManager $syncFlagManager,
        private readonly CsvExport $csvExport,
        private readonly FileService $fileService,
        private readonly TiktokApiClientBuilder $apiClientBuilder,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * Execute full catalog sync
     *
     * @return void
     */
    public function execute()
    {
        $websites = $this->websiteCollectionFactory->create()->setLoadDefault(false);
        /** @var Website $website */
        foreach ($websites->getItems() as $website) {
            $this->logger->info('Starting full catalog sync for website: ' . $website->getName());
            $isTriggered = $this->syncFlagManager->getCatalogSyncFlag((int)$website->getId());
            if (!$isTriggered) {
                $this->logger->info('Skipping full catalog sync for website: ' . $website->getName());
                continue;
            }

            $this->syncFlagManager->setCatalogSyncFlag((int)$website->getId(), false);

            try {
                $builder = $this->apiClientBuilder->create((int) $website->getId());
                $profile = $builder->getProfile();
                $feedId = $builder->getScopeManager()->getFeedId();
                if (!$feedId) {
                    $response = $builder->createFeed($profile['bc_id'], $profile['catalog_id'], 'OVERWRITE');
                    if (!$response
                        || !$response['message'] == 'OK'
                        || !in_array($response['code'], [0, 20001])
                        || !isset($response['data']['feed_id'])) {
                        $message = 'Something went wrong with your request to upload your catalog to Tiktok.';
                        $message .= ' Please try again later.';
                        $this->messageManager->addErrorMessage(__($message));
                    } else {
                        $feedId = $response['data']['feed_id'];
                        $builder->getScopeManager()->setFeedId($feedId);
                    }
                }
                if (!isset($profile['bc_id'], $profile['catalog_id']) || !$feedId) {
                    $this->logger->info('Missed required information: bc_id or catalog_id or feed_id');
                    continue;
                }

                $this->csvExport->export((int)$website->getId());
                $filePath = $this->fileService->getExportFilePath('tiktok.csv', (string)$website->getId());
                $builder->uploadCatalogProducts($profile['bc_id'], $profile['catalog_id'], $filePath, $feedId);
                $this->logger->info('Full product sync complete');
            } catch (Exception $e) {
                $this->logger->critical('Error during export: ' . $e->getMessage());
            } catch (GuzzleException $e) {
                $this->logger->critical('Error during export: ' . $e->getMessage());
            }
        }
    }
}
