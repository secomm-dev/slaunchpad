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
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\ShipmentTrackAttacher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-9Q5ZAK (GHN-D, SPEC §28) — recovery path for FAILED / UNKNOWN create attempts:
 * resubmits the SAME client_order_code (GHNS + shipment id) so the provider's idempotency
 * (sandbox-proven: same code → same order_code) reconciles instead of duplicating.
 *
 *   bin/magento secomm:ghn:shipment:retry <shipment_id>
 */
class RetryShipmentCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly GhnShipmentCreationService $creationService,
        private readonly ShipmentTrackAttacher $trackAttacher,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('secomm:ghn:shipment:retry');
        $this->setDescription('Retry the GHN order creation for a shipment (idempotent — same client_order_code)');
        $this->addArgument('shipment_id', InputArgument::REQUIRED, 'sales_shipment.entity_id');
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

        $outcome = $this->creationService->createForShipment($shipment);
        if ($outcome->isSuccessful()) {
            $attached = $this->trackAttacher->attach($shipment, (string) $outcome->getOrderCode(), 'GHN');
            $output->writeln(sprintf(
                '<info>Track %s attached: %s</info>',
                $attached ? 'yes' : 'already present',
                (string) $outcome->getOrderCode()
            ));
            $output->writeln(sprintf(
                '<info>GHN order %s (client_order_code %s)%s</info>',
                (string) $outcome->getOrderCode(),
                $outcome->getClientOrderCode(),
                $outcome->getTotalFee() !== null
                    ? sprintf(' — fee %s VND', number_format($outcome->getTotalFee(), 0, ',', '.'))
                    : ''
            ));

            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>GHN create %s: %s (client_order_code %s)</error>',
            (string) $outcome->getStatus(),
            (string) $outcome->getReason(),
            $outcome->getClientOrderCode()
        ));

        return Cli::RETURN_FAILURE;
    }
}
