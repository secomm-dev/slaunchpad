<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Psr\Log\LoggerInterface;
use Secomm\GiaoHangNhanh\Helper\Data as GHNHelperData;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Secomm\GiaoHangNhanh\Model\Config;

class OrderSyncService
{
    public const GHN_SERVICE = [
        'giaohangnhanh_standard_giaohangnhanh_standard' => 'giaohangnhanh_standard',
        'giaohangnhanh_express_giaohangnhanh_express' => 'giaohangnhanh_express'
    ];

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteRepository $quoteRepository
     * @param CommandPoolInterface $commandPool
     * @param GHNHelperData $ghnHelperData
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param TransactionFactory $transactionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly QuoteRepository $quoteRepository,
        private readonly CommandPoolInterface $commandPool,
        private readonly GHNHelperData $ghnHelperData,
        private readonly ShipmentFactory $shipmentFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly TransactionFactory $transactionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Synchronize an order to GHN by Order ID or Order Instance.
     *
     * @param int|OrderInterface $orderOrId
     * @return void
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function sync($orderOrId): void
    {
        if ($orderOrId instanceof OrderInterface) {
            $order = $orderOrId;
        } else {
            $order = $this->orderRepository->get((int)$orderOrId);
        }

        if (false === strpos((string)$order->getShippingMethod(), Config::GHN_CODE)) {
            return;
        }

        $quote = $this->quoteRepository->get($order->getQuoteId());
        $shippingAddress = $quote->getShippingAddress();

        $this->commandPool->get('synchronize_order')->execute([
            'order' => $order,
            'district' => $shippingAddress->getDistrict(),
            'shipping_service_id' => $shippingAddress->getShippingServiceId(),
            'shipping_service_type_id' => $shippingAddress->getShippingServiceTypeId(),
            'is_order_payment_cod' => $this->ghnHelperData->isOrderPaymentCod($order)
        ]);

        // Auto-create Magento shipment after successful GHN sync
        $this->createShipment($order);
    }

    /**
     * Create Magento Shipment after GHN order sync.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function createShipment(OrderInterface $order): void
    {
        $trackingCode = $order->getTrackingCode();
        if (!array_key_exists($order->getShippingMethod(), self::GHN_SERVICE) || !$trackingCode) {
            return;
        }

        if (!$order->canShip()) {
            return;
        }

        $track = [[
            'carrier_code' => self::GHN_SERVICE[$order->getShippingMethod()],
            'number' => (string)$trackingCode,
            'title' => (string)$order->getShippingDescription(),
            'description' => (string)$order->getShippingDescription()
        ]];

        $items = [];
        foreach ($order->getAllItems() as $item) {
            if (!$item->getItemId()) {
                continue;
            }
            $items[$item->getItemId()] = $item->getQtyOrdered();
        }

        $shipment = $this->shipmentFactory->create($order, $items, $track);
        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        // Save both shipment AND order so that qty_shipped on sales_order_item is persisted.
        // register() updates qty_shipped in memory on the order object; without saving the order
        // the column stays at 0 and "Qty Shipped" never appears in the admin Items Ordered grid.
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($shipment);
        $transaction->addObject($shipment->getOrder());
        $transaction->save();
    }
}
