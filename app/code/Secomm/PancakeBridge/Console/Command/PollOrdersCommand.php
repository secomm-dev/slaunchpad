<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Console\Command;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Api\StatusMapResolverInterface;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as StateResource;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;
use Secomm\PancakeFunction\Model\Client\PosClient;
use Secomm\PancakeBridge\Model\Config\PancakeConfig;
use Secomm\PancakeFunction\Model\Inbound\OrderPayloadParser;
use Secomm\PancakeBridge\Model\Order\PancakeOrderExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run Pancake inbound poll without cron. Prints each step to the console for debug.
 *
 * Flow per order:
 * 1) GET Pancake status → store on secomm_fulfillment_export.current_status
 * 2) Lookup Status Mapping (Pancake code → Magento sales_order.status)
 * 3) InboundUpdateApplier updates Magento order status (if map exists) + comment
 *
 * Usage:
 *   bin/magento secomm:pancake:poll --force --reapply --increment-id=000000046-7
 */
class PollOrdersCommand extends Command
{
    public function __construct(
        private readonly PancakeConfig $config,
        private readonly ExportCollectionFactory $exportCollectionFactory,
        private readonly PosClient $posClient,
        private readonly OrderPayloadParser $parser,
        private readonly InboundUpdateApplier $applier,
        private readonly StatusMapResolverInterface $statusMapResolver,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FulfillmentLogger $fulfillmentLogger,
        private readonly StateCollectionFactory $stateCollectionFactory,
        private readonly StateResource $stateResource,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('secomm:pancake:poll');
        $this->setDescription(
            'Poll Pancake POS order status for Magento-origin exports (no cron). Prints debug to stdout.'
        );
        $this->addOption(
            'increment-id',
            'i',
            InputOption::VALUE_REQUIRED,
            'Only poll this Magento increment_id'
        );
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Max export rows to poll (default 40)',
            '40'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Ignore pancake/general/enabled and pancake/poll/enabled for this run'
        );
        $this->addOption(
            'reapply',
            'r',
            InputOption::VALUE_NONE,
            'Clear last_event_id so the same POS status can update Magento again (debug)'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool) $input->getOption('force');
        $reapply = (bool) $input->getOption('reapply');
        $incrementId = trim((string) $input->getOption('increment-id'));
        $limit = max(1, (int) $input->getOption('limit'));

        $output->writeln('<info>secomm:pancake:poll</info>');
        $output->writeln(sprintf(
            'enabled=%s poll=%s log=%s force=%s reapply=%s',
            $this->config->isEnabled() ? 'yes' : 'no',
            $this->config->isPollEnabled() ? 'yes' : 'no',
            $this->config->isLogEnabled() ? 'yes' : 'no',
            $force ? 'yes' : 'no',
            $reapply ? 'yes' : 'no'
        ));

        if (!$force && (!$this->config->isEnabled() || !$this->config->isPollEnabled())) {
            $output->writeln(
                '<error>Skipped: enable Pancake export + Poll, or pass --force.</error>'
            );
            return Command::FAILURE;
        }

        $this->fulfillmentLogger->info(
            PancakeOrderExporter::SERVICE_CODE,
            'Pancake poll CLI started.',
            ['increment_id' => $incrementId !== '' ? $incrementId : null, 'limit' => $limit]
        );

        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', PancakeOrderExporter::SERVICE_CODE)
            ->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO)
            ->addFieldToFilter('push_status', ExportPushStatus::SUCCESS)
            ->addFieldToFilter('external_order_id', ['notnull' => true])
            ->setOrder('entity_id', 'DESC')
            ->setPageSize($limit)
            ->setCurPage(1);
        if ($incrementId !== '') {
            $collection->addFieldToFilter('magento_increment_id', $incrementId);
        }

        $rows = $collection->getItems();
        $output->writeln(sprintf('<comment>Found %d export row(s).</comment>', count($rows)));
        if ($rows === []) {
            $output->writeln('<error>No matching secomm_fulfillment_export rows (need push_status=success).</error>');
            return Command::FAILURE;
        }

        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            if (!$row instanceof FulfillmentExport) {
                continue;
            }
            if ($this->pollOne($row, $output, $reapply)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        $output->writeln(sprintf('<info>Done. ok=%d fail=%d</info>', $ok, $fail));
        return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Poll one mapping row: export.current_status + Magento sales_order.status via Status Mapping.
     *
     * @param FulfillmentExport $row Magento-origin export mapping
     * @param OutputInterface $output Console output
     * @param bool $reapply Clear last_event_id before apply
     */
    private function pollOne(FulfillmentExport $row, OutputInterface $output, bool $reapply): bool
    {
        $incrementId = (string) $row->getMagentoIncrementId();
        $externalId = (string) $row->getExternalOrderId();
        $magentoOrderId = (int) $row->getMagentoOrderId();

        $output->writeln('');
        $output->writeln(sprintf(
            '--- order=%s export_id=%s external_id=%s export.current_status=%s ---',
            $incrementId,
            (string) $row->getEntityId(),
            $externalId,
            (string) ($row->getCurrentStatus() ?? 'null')
        ));

        if ($externalId === '') {
            $output->writeln('<error>empty external_order_id</error>');
            return false;
        }

        try {
            if ($reapply) {
                $this->clearLastEventId($magentoOrderId, $output);
            }

            /** @var Order $orderBefore */
            $orderBefore = $this->orderRepository->get($magentoOrderId);
            $magentoStatusBefore = (string) $orderBefore->getStatus();
            $output->writeln(sprintf(
                'Magento order BEFORE: entity_id=%d status=%s state=%s',
                $magentoOrderId,
                $magentoStatusBefore,
                (string) $orderBefore->getState()
            ));

            $response = $this->posClient->getOrder($externalId);
            $payload = $this->unwrapOrder($response);
            $rawStatus = $this->extractRawStatus($payload);
            $output->writeln(sprintf(
                'GET Pancake ok. raw_status=%s',
                $rawStatus !== '' ? $rawStatus : 'empty'
            ));

            // Step 1: mirror POS status on export mapping table only (not sales_order).
            if ($rawStatus !== '' && $row->getCurrentStatus() !== $rawStatus) {
                $row->setCurrentStatus($rawStatus);
                $row->getResource()->save($row);
                $output->writeln(sprintf(
                    'export.current_status updated -> %s (secomm_fulfillment_export only)',
                    $rawStatus
                ));
            } else {
                $output->writeln('export.current_status unchanged');
            }

            $update = $this->parser->parse($payload);
            if ($update === null) {
                $output->writeln('<error>parser returned null (missing POS id in payload)</error>');
                return false;
            }

            $pancakeCode = $this->normalizeRawCode($update->getRawStatus());
            $statusMap = $pancakeCode !== ''
                ? $this->statusMapResolver->resolve(PancakeOrderExporter::SERVICE_CODE, $pancakeCode)
                : null;

            // Step 2: Status Mapping table (same idea as warehouse map).
            if ($statusMap !== null) {
                $output->writeln(sprintf(
                    '<info>Status map FOUND: Pancake %s -> Magento order status "%s"</info>',
                    $pancakeCode,
                    $statusMap->getMagentoOrderStatus()
                ));
            } else {
                $output->writeln(sprintf(
                    '<comment>Status map MISSING for Pancake code "%s" '
                    . '(Secomm → Pancake POS → Status Mapping). Magento order status will NOT change.</comment>',
                    $pancakeCode !== '' ? $pancakeCode : '(empty)'
                ));
            }

            $this->rememberPosOrderId($row, $update->getExternalOrderId(), $output);
            $bound = $this->bindToExportRow($row, $update);

            // Step 3: applier sets Magento sales_order.status when map exists + writes comment.
            $applyResult = $this->applier->applyToExport($row, $bound);
            $output->writeln(sprintf('<info>apply result: %s</info>', $applyResult));

            /** @var Order $orderAfter */
            $orderAfter = $this->orderRepository->get($magentoOrderId);
            $magentoStatusAfter = (string) $orderAfter->getStatus();
            $output->writeln(sprintf(
                'Magento order AFTER: status=%s state=%s',
                $magentoStatusAfter,
                (string) $orderAfter->getState()
            ));

            if ($applyResult === 'skipped_same_event') {
                $output->writeln(
                    '<comment>Skipped Magento update: same event_id. Use --reapply or change Pancake status.</comment>'
                );
            } elseif ($applyResult === 'applied_status') {
                $output->writeln(sprintf(
                    '<info>Magento order status changed: %s -> %s (from Status Mapping)</info>',
                    $magentoStatusBefore,
                    $magentoStatusAfter
                ));
            } elseif ($applyResult === 'applied_comment_only') {
                $output->writeln(
                    '<comment>Order comment saved only. Add Status Mapping to change Magento order status.</comment>'
                );
            } elseif (str_starts_with($applyResult, 'error:')) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $this->fulfillmentLogger->error(
                PancakeOrderExporter::SERVICE_CODE,
                'Pancake poll CLI failed.',
                ['increment_id' => $incrementId, 'error' => $e->getMessage()]
            );
            return false;
        }
    }

    /**
     * Clear idempotency key so the same POS event can write Magento again.
     *
     * @param int $magentoOrderId sales_order.entity_id
     * @param OutputInterface $output Console output
     */
    private function clearLastEventId(int $magentoOrderId, OutputInterface $output): void
    {
        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('magento_order_id', $magentoOrderId);
        $collection->setPageSize(1);
        $state = $collection->getFirstItem();
        if (!$state->getEntityId()) {
            $output->writeln('reapply: no fulfillment_state row yet');
            return;
        }
        $state->setLastEventId(null);
        $this->stateResource->save($state);
        $output->writeln('reapply: cleared last_event_id');
    }

    /**
     * @param FulfillmentExport $row Export mapping row
     * @param string $parsedId POS id from payload
     * @param OutputInterface $output Console output
     */
    private function rememberPosOrderId(FulfillmentExport $row, string $parsedId, OutputInterface $output): void
    {
        $stored = (string) $row->getExternalOrderId();
        $incrementId = (string) $row->getMagentoIncrementId();
        if ($parsedId === '' || $parsedId === $stored || $parsedId === $incrementId || !ctype_digit($parsedId)) {
            return;
        }
        $row->setExternalOrderId($parsedId);
        $row->getResource()->save($row);
        $output->writeln(sprintf('external_order_id corrected %s -> %s', $stored, $parsedId));
    }

    private function bindToExportRow(FulfillmentExport $row, InboundUpdate $update): InboundUpdate
    {
        return new InboundUpdate(
            $update->getServiceCode(),
            (string) $row->getExternalOrderId(),
            $update->getRawStatus(),
            $update->getEventId(),
            $update->getCarrierName(),
            $update->getTrackingNumber(),
            $update->getTrackingUrl()
        );
    }

    /**
     * @param string $rawStatus POS raw status
     */
    private function normalizeRawCode(string $rawStatus): string
    {
        $raw = trim($rawStatus);
        if ($raw === '') {
            return '';
        }
        if (is_numeric($raw)) {
            return (string) (int) $raw;
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrapOrder(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function extractRawStatus(array $order): string
    {
        if (isset($order['status']) && $order['status'] !== '' && !is_array($order['status'])) {
            return (string) $order['status'];
        }
        if (isset($order['order']['status']) && $order['order']['status'] !== '' && !is_array($order['order']['status'])) {
            return (string) $order['order']['status'];
        }

        return '';
    }
}
