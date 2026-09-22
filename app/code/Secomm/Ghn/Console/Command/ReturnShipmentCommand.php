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
use Secomm\Ghn\Model\Shipment\GhnReturnService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-4ATBC4 (GHN-E2) — support/operations CLI: request provider RETURN (force R2S) of a GHN
 * order. Calls the same {@see GhnReturnService} the admin/orchestration path uses.
 *
 *   bin/magento secomm:ghn:shipment:return <shipment_id>
 *
 * Provider-eligible states: delivery_fail, storing, waiting_to_return, return.
 */
class ReturnShipmentCommand extends Command
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly GhnReturnService $returnService,
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
        $this->setName('secomm:ghn:shipment:return');
        $this->setDescription('Request GHN return (force R2S) for a shipment (provider mutation — use deliberately)');
        $this->addArgument('shipment_id', InputArgument::REQUIRED, 'sales_shipment.entity_id');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

        $outcome = $this->returnService->requestReturn($shipment);

        if ($outcome->isSuccessful()) {
            $output->writeln(sprintf('<info>GHN return requested for order %s.</info>', $outcome->getProviderOrderCode()));

            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>GHN return %s: %s%s</error>',
            (string) $outcome->getStatus(),
            (string) $outcome->getReasonCode(),
            $outcome->getMessage() !== null ? ' — ' . $outcome->getMessage() : ''
        ));

        return Cli::RETURN_FAILURE;
    }
}
