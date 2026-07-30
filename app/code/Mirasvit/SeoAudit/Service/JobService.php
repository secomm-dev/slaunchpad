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

namespace Mirasvit\SeoAudit\Service;

use Magento\Framework\App\MaintenanceMode;
use Magento\Sitemap\Model\ResourceModel\Sitemap\Collection as SitemapCollection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreManagerInterfaceFactory;
use Mirasvit\SeoAudit\Api\Data\CheckResultAggregatedInterface;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Model\ConfigProvider;
use Mirasvit\SeoAudit\Parser\ParserFactory;
use Mirasvit\SeoAudit\Repository\JobRepository;
use Mirasvit\SeoAudit\Repository\UrlRepository;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class JobService
{
    private $urlRepository;

    private $storeManagerFactory;

    private $sitemapCollection;

    private $urlService;

    private $parserFactory;

    private $jobRepository;

    private $checkResultService;

    private $maintenanceMode;

    private $config;

    private $logger;

    public function __construct(
        UrlRepository                $urlRepository,
        StoreManagerInterfaceFactory $storeManagerFactory,
        SitemapCollection            $sitemapCollection,
        UrlService                   $urlService,
        ParserFactory                $parserFactory,
        JobRepository                $jobRepository,
        CheckResultService           $checkResultServise,
        MaintenanceMode              $maintenanceMode,
        ConfigProvider               $config,
        LoggerInterface              $logger
    ) {
        $this->urlRepository       = $urlRepository;
        $this->storeManagerFactory = $storeManagerFactory;
        $this->sitemapCollection   = $sitemapCollection;
        $this->urlService          = $urlService;
        $this->parserFactory       = $parserFactory;
        $this->jobRepository       = $jobRepository;
        $this->checkResultService  = $checkResultServise;
        $this->maintenanceMode     = $maintenanceMode;
        $this->config              = $config;
        $this->logger              = $logger;
    }

    public function startJob(): JobInterface
    {
        $job = $this->jobRepository->create();

        $job->setStatus(JobInterface::STATUS_PENDING);

        $this->jobRepository->save($job);

        $this->finishJob();

        $job->setStatus(JobInterface::STATUS_PROCESSING)
            ->setStartedAt(date('Y-m-d H:i:s'));

        $job = $this->jobRepository->save($job);

        $this->urlService->resetUrls($job);
        $this->generateBasePool($job);

        return $job;
    }

    public function finishJob(): void
    {
        $running = $this->jobRepository->getCollection()
            ->addFieldToFilter(JobInterface::STATUS, JobInterface::STATUS_PROCESSING);

        foreach ($running as $job) {
            $job->setStatus(JobInterface::STATUS_FINISHED)
                ->setFinishedAt(date('Y-m-d H:i:s'))
                ->setResult($this->checkResultService->aggregateResult($job));

            $this->jobRepository->save($job);
        }
    }

    public function hasPendingUrls(): bool
    {
        return $this->urlRepository->getUnprocessedUrlsCollection()->count() > 0;
    }

    public function runJob(): void
    {
        $start      = microtime(true);
        $delay      = $this->config->getCrawlDelay();
        $maxUrls    = $this->config->getMaxUrlsPerRun();
        $processed  = 0;

        $runningJob = $this->jobRepository->getRunningJob();

        if (!$runningJob) {
            return;
        }

        while ($unprocessed = $this->urlRepository->getUnprocessedUrlsCollection()) {
            if (!$unprocessed->count()) {
                $this->finishJob();
                return;
            }

            foreach ($unprocessed as $url) {
                if (microtime(true) - $start >= 5 * 60) {
                    return; // crawl URLs only 5 minutes per cron run
                }

                if ($maxUrls > 0 && $processed >= $maxUrls) {
                    return;
                }

                // in case maintenance mode enabled while job is running
                if ($this->maintenanceMode->isOn()) {
                    $this->logger->notice('Maintenance mode enabled. SEO audit stopped crawling URLs');

                    return;
                }

                $this->retrieveChildUrls($url, $runningJob->getId());
                $processed++;

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }
    }

    public function reset(): void
    {
        $resource = $this->urlRepository->getCollection()->getResource();
        $connection = $resource->getConnection();

        $connection->query('TRUNCATE TABLE ' . $resource->getTable(UrlInterface::TABLE_NAME));
        $connection->query('TRUNCATE TABLE ' . $resource->getTable(JobInterface::TABLE_NAME));
        $connection->query('TRUNCATE TABLE ' . $resource->getTable(CheckResultInterface::TABLE_NAME));
        $connection->query('TRUNCATE TABLE ' . $resource->getTable(CheckResultAggregatedInterface::TABLE_NAME));
    }

    public function generateBasePool(JobInterface $job): void
    {
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $this->storeManagerFactory->create();

        /** @var \Magento\Store\Model\Store $store */
        foreach ($storeManager->getStores() as $store) {
            if (!$this->config->isEnabled((int)$store->getId())) {
                continue;
            }

            $url = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK, true);

            $this->urlService->ensureUrl(
                $url,
                UrlInterface::TYPE_PAGE,
                $job->getId()
            );

            $this->urlService->ensureUrl(
                $url . 'robots.txt',
                UrlInterface::TYPE_ROBOTS,
                $job->getId()
            );
        }

        /** @var \Magento\Sitemap\Model\Sitemap $sitemap */
        foreach ($this->sitemapCollection as $sitemap) {
            $sitemapPath = $sitemap->getSitemapPath();
            if (strpos($sitemapPath, '/') !== 0) {
                $sitemapPath = '/' . $sitemapPath;
            }

            $sitemapUrl = $sitemap->getSitemapUrl(
                $sitemapPath,
                $sitemap->getSitemapFilename()
            );

            $this->urlService->ensureUrl(
                $sitemapUrl,
                UrlInterface::TYPE_SITEMAP,
                $job->getId()
            );
        }
    }

    public function retrieveChildUrls(UrlInterface $url, int $jobId): void
    {
        $url = $this->urlService->loadResource($url);

        $parser = $this->parserFactory->create($url);

        if ($parser) {
            $parser->retrieveUrls($url, $jobId);
        }

        $url->setStatus(UrlInterface::STATUS_CRAWLED);

        $this->urlRepository->save($url);
    }
}
