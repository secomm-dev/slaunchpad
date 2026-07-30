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


namespace Mirasvit\SeoAudit\Block\Adminhtml\Job;


use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Repository\JobRepository;

class Details extends Template
{
    const CHART_JOBS_ID = 'jobs';

    const RING_RADIUS = 50;

    const SCORE_TIER_COLORS = [
        ['min' => 90, 'color' => '#0cce6a'],
        ['min' => 50, 'color' => '#ffa400'],
        ['min' => 0, 'color' => '#ff4e43'],
    ];

    private $jobRepository;

    private $serializer;

    public function __construct(
        JobRepository $jobRepository,
        Template\Context $context,
        Json $serializer,
        array $data = []
    ) {
        $this->jobRepository = $jobRepository;
        $this->serializer    = $serializer;

        parent::__construct($context, $data);
    }

    public function getJob()
    {
        $id = $this->getRequest()->getParam(JobInterface::ID);

        $model = $this->jobRepository->get((int)$id);

        return $model;
    }

    public function getJobResult()
    {
        return $this->getJob()->getResult();
    }

    public function getErrorsTotal()
    {
        $resource = $this->jobRepository->getCollection()->getResource();
        $connection = $resource->getConnection();

        $query = "SELECT COUNT(check_id) as errors FROM {$resource->getTable(CheckResultInterface::TABLE_NAME)} WHERE job_id = {$this->getJob()->getId()} AND result < 0";

        $result = $connection->query($query)->fetchAll();

        return $result[0]['errors'];
    }

    public function getIssueLink(string $type): string
    {
        return $this->_urlBuilder->getUrl('*/*/url', [
            CheckResultInterface::JOB_ID => $this->getJob()->getId(),
            CheckResultInterface::RESULT => $type
        ]);
    }

    public function getHealthScoreColor(): string
    {
        $score = (int)$this->getJobResult()['score'];

        foreach (self::SCORE_TIER_COLORS as $tier) {
            if ($score >= $tier['min']) {
                return $tier['color'];
            }
        }

        return '#ff4e43';
    }

    public function getHealthScoreTint(): string
    {
        $tints = [
            '#0cce6a' => 'rgba(12,206,106,0.12)',
            '#ffa400' => 'rgba(255,164,0,0.12)',
            '#ff4e43' => 'rgba(255,78,67,0.12)',
        ];

        return $tints[$this->getHealthScoreColor()];
    }

    public function getHealthScoreCircumference(): float
    {
        return round(2 * M_PI * self::RING_RADIUS, 1);
    }

    public function getHealthScoreDashOffset(): float
    {
        $score = max(0, min(100, (int)$this->getJobResult()['score']));

        return round($this->getHealthScoreCircumference() * (1 - $score / 100), 1);
    }

    public function getChartInitConfig(): string
    {
        return $this->serializer->serialize([
            '#' . self::CHART_JOBS_ID => [
                'Mirasvit_SeoAudit/js/component/chart' => [
                    'id'           => self::CHART_JOBS_ID,
                    'data'         => $this->getChartDataConfig(),
                    'chartOptions' => $this->getChartOptionsConfig(),
                ],
            ],
        ]);
    }

    private function getChartDataConfig(): array
    {
        $rows = $this->getJobsChartData();

        $job      = $this->getJob();
        $jobStart = $job->getStartedAt() ? date('Y-m-d', strtotime($job->getStartedAt())) : null;

        $labels       = [];
        $scores       = [];
        $currentIndex = null;

        foreach ($rows as $i => $row) {
            $startLabel  = date('M j', strtotime($row['started']));
            $finishLabel = date('M j', strtotime($row['finished']));
            $labels[]    = $startLabel === $finishLabel ? $startLabel : $startLabel . ' - ' . $finishLabel;
            $scores[]    = $row['score'];

            if ($jobStart && $row['started'] === $jobStart) {
                $currentIndex = $i;
            }
        }

        return [
            'labels'       => $labels,
            'currentIndex' => $currentIndex,
            'datasets'     => [
                [
                    'type'               => 'bar',
                    'label'              => '',
                    'data'               => $scores,
                    'borderWidth'        => 0,
                    'categoryPercentage' => 0.85,
                    'barPercentage'      => 0.8,
                    'order'              => 2,
                ],
                [
                    'type'        => 'line',
                    'label'       => '',
                    'data'        => $scores,
                    'order'       => 1,
                    'borderColor' => 'rgba(20,23,26,0.3)',
                    'borderWidth' => 1,
                    'pointRadius' => 0,
                    'fill'        => false,
                    'tension'     => 0,
                ],
            ],
        ];
    }

    public function getErrorChartData(): array
    {
        $job    = $this->getJob();
        $result = $job->getResult();

        $total         = isset($result['pages']) ? (int)$result['pages'] : 0;
        $withErrors    = isset($result['pages_error']) ? (int)$result['pages_error'] : 0;
        $withoutErrors = $total - $withErrors;

        $withoutErrorsRate = $withErrors ? round(100 * $withoutErrors / $total) : 100;

        $rates = [
            $withoutErrorsRate,
            100 - $withoutErrorsRate
        ];

        return [
            'withoutErrors' => $withoutErrors,
            'withErrors'    => $withErrors,
            'rates'         => $rates
        ];
    }

    private function getJobsChartData(): array
    {
        $data = [];

        $resource   = $this->jobRepository->getCollection()->getResource();
        $connection = $resource->getConnection();

        $jobsQuery = "SELECT DATE(started_at) AS started, COALESCE(DATE(finished_at), DATE(started_at)) AS finished, result_serialized
                    FROM {$resource->getTable(JobInterface::TABLE_NAME)}
                    ORDER BY started_at DESC
                    LIMIT 10";

        $rows = array_reverse($connection->query($jobsQuery)->fetchAll());

        foreach ($rows as $row) {
            try {
                $score = (array)$this->serializer->unserialize($row['result_serialized']);
            } catch (\Exception $e) {
                $score = [];
            }

            $data[] = [
                'started'  => $row['started'],
                'finished' => $row['finished'],
                'score'    => isset($score['score']) ? $score['score'] : 0,
            ];
        }

        return $data;
    }

    private function getChartOptionsConfig(): array
    {
        return [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'plugins'             => [
                'legend'  => ['display' => false],
                'tooltip' => [
                    'enabled'   => true,
                    'mode'      => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid'   => ['display' => false],
                    'border' => ['display' => false],
                    'ticks'  => [
                        'color' => '#A6ABAE',
                        'font'  => ['size' => 10],
                    ],
                ],
                'y' => [
                    'min'    => 0,
                    'max'    => 100,
                    'grid'   => ['color' => 'rgba(20,23,26,0.08)'],
                    'border' => ['display' => false],
                    'ticks'  => [
                        'stepSize' => 25,
                        'color'    => '#A6ABAE',
                        'font'     => ['size' => 10],
                    ],
                ],
            ],
        ];
    }
}
