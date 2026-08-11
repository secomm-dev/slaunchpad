<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\Queue;

use Secomm\GiaoHangNhanh\Helper\Data as GHNHelperData;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Psr\Log\LoggerInterface;

class OrderSyncConsumer
{
    const GHN_SERVICE = [
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly QuoteRepository             $quoteRepository,
        private readonly CommandPoolInterface        $commandPool,
        private readonly GHNHelperData               $ghnHelperData,
        private readonly ShipmentFactory             $shipmentFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly LoggerInterface             $logger
    ) {
    }

    /**
     * @param string $message
     */
    public function process(string $message)
    {
        try {
            $data = json_decode($message, true);
            $orderId = (int) ($data['order_id'] ?? 0);

            if (!$orderId) {
                $this->logger->error('[GHN OrderSync] Invalid order_id in message');
                return;
            }

            $order = $this->orderRepository->get($orderId);

            if (false === strpos($order->getShippingMethod(), Config::GHN_CODE)) {
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

            // Tạo shipment sau khi sync thành công
            $this->createShipment($order);

        } catch (NoSuchEntityException $e) {
            $this->logger->error('[GHN OrderSync] Order not found: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[GHN OrderSync] Error: ' . $e->getMessage());
            throw $e; // Re-throw để Magento MQ retry
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    private function createShipment($order): void
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
            'number' => (string) $trackingCode,
            'title' => (string) $order->getShippingDescription(),
            'description' => (string) $order->getShippingDescription()
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
        $this->shipmentRepository->save($shipment);
    }
}
