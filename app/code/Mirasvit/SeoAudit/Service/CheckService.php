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
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;
use Mirasvit\SeoAudit\Repository\CheckResultRepository;
use Mirasvit\SeoAudit\Repository\JobRepository;
use Mirasvit\SeoAudit\Repository\UrlRepository;
use Psr\Log\LoggerInterface;

class CheckService
{
    private $urlRepository;

    private $checkResultRepository;

    private $jobRepository;

    private $checkResultAggregatedService;

    private $checkResultService;

    private $maintenanceMode;

    private $logger;

    public function __construct(
        UrlRepository                $urlRepository,
        CheckResultRepository        $checkResultRepository,
        JobRepository                $jobRepository,
        CheckResultAggregatedService $checkResultAggregatedService,
        CheckResultService           $checkResultService,
        MaintenanceMode              $maintenanceMode,
        LoggerInterface              $logger
    ) {
        $this->urlRepository                = $urlRepository;
        $this->checkResultRepository        = $checkResultRepository;
        $this->jobRepository                = $jobRepository;
        $this->checkResultAggregatedService = $checkResultAggregatedService;
        $this->checkResultService           = $checkResultService;
        $this->maintenanceMode              = $maintenanceMode;
        $this->logger                       = $logger;
    }

    public function runChecks(?int $limit = null): void
    {
        $start      = microtime(true);
        $runningJob = $this->jobRepository->getRunningJob();

        if (!$runningJob) {
            return;
        }

        $forChecks = $this->urlRepository->getUrlsCollectionForCheck($runningJob->getId());
        $limit     = $limit ?: 10000;

        $forChecks->getSelect()->limit($limit);

        foreach ($forChecks as $url) {
            // in case maintenance mode enabled while job is running
            if ($this->maintenanceMode->isOn()) {
                $this->logger->notice('Maintenance mode enabled. SEO audit stopped performing checks');

                return;
            }

            if (microtime(true) - $start >= 5 * 60) {
                break;
            }

            $this->runChecksForUrl($url);
        }

        // need this for correct job status. Job can be finished while checks still running
        $runningJob = $this->jobRepository->get($runningJob->getId());

        $runningJob->setResult($this->checkResultService->aggregateResult($runningJob));

        $this->jobRepository->save($runningJob);

        $this->checkResultAggregatedService->aggregate($runningJob->getId());
    }

    public function runChecksForUrl(UrlInterface $url): void
    {
        $url->setStatus(UrlInterface::STATUS_PROCESSING);
        $this->urlRepository->save($url);

        $allowedChecks = $this->checkResultRepository->getAllowedChecks($url);

        /** @var AbstractCheck $check */
        foreach ($allowedChecks as $check) {
            $result = $check->getCheckResult($url);

            $checkResult = $this->checkResultRepository->create();

            $checkResult->setUrlId($url->getId())
                ->setUrlType($url->getType())
                ->setJobId($url->getJobId())
                ->setIdentifier($check->getIdentifier())
                ->setImportance($check->getImportance())
                ->setResult($result[CheckResultInterface::RESULT])
                ->setValue($result[CheckResultInterface::VALUE])
                ->setMessage($result[CheckResultInterface::MESSAGE]);

            $this->checkResultRepository->save($checkResult);
        }

        $url->setStatus(UrlInterface::STATUS_FINISHED)
            ->setContent(null);

        $this->urlRepository->save($url);
    }
}
