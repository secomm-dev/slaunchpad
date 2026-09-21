<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Secomm\Ghn\Model\Shipment\GhnCancelService;
use Secomm\Ghn\Model\Tracking\ShipmentReconciler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-4ATBC4 (GHN-E2) — support/operations CLI: request provider cancellation of a GHN order.
 * Calls the same {@see GhnCancelService} the admin/orchestration path uses — no duplicated logic.
 *
 *   bin/magento secomm:ghn:shipment:cancel <shipment_id> <reason_code> [--reason=...]
 */
class CancelShipmentCommand extends Command
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly GhnCancelService $cancelService,
        private readonly ShipmentReconciler $reconciler,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('secomm:ghn:shipment:cancel');
        $this->setDescription('Request GHN cancellation for a shipment (provider mutation — use deliberately)');
        $this->addArgument('shipment_id', InputArgument::REQUIRED, 'sales_shipment.entity_id');
        $this->addArgument('reason_code', InputArgument::REQUIRED, 'GHN-CO001 | GHN-CO002 | GHN-CO003 | GHN-CANCEL-OTHER');
        $this->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Optional free-text reason');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The shipment/tracking graph needs a fully booted area (CLI has none by default).
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException) {
            $this->appState->setAreaCode('adminhtml');
        }

        $shipmentId = (int) $input->getArgument('shipment_id');
        $reasonCode = (string) $input->getArgument('reason_code');
        if ($shipmentId <= 0) {
            $output->writeln('<error>Invalid shipment id.</error>');

            return Cli::RETURN_FAILURE;
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException | InputException) {
            $output->writeln(sprintf('<error>Shipment %d not found.</error>', $shipmentId));

            return Cli::RETURN_FAILURE;
        }

        $outcome = $this->cancelService->cancel($shipment, $reasonCode, (string) $input->getOption('reason'));

        if ($outcome->isSuccessful()) {
            $output->writeln(sprintf('<info>GHN order %s cancelled.</info>', $outcome->getProviderOrderCode()));
            // Sync CANCELLED through the E1 pipeline now (non-fatal — webhook is the fallback).
            $this->reconciler->reconcileByOrderCode((string) $outcome->getProviderOrderCode());

            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>GHN cancel %s: %s%s</error>',
            (string) $outcome->getStatus(),
            (string) $outcome->getReasonCode(),
            $outcome->getMessage() !== null ? ' — ' . $outcome->getMessage() : ''
        ));

        return Cli::RETURN_FAILURE;
    }
}
