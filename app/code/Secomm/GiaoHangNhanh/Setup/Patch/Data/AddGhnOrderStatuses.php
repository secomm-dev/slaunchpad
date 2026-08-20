<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Framework\Exception\AlreadyExistsException;

class AddGhnOrderStatuses implements DataPatchInterface
{
    /**
     * @var StatusFactory
     */
    private $statusFactory;

    /**
     * @var StatusResourceFactory
     */
    private $statusResourceFactory;

    /**
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     */
    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $this->addNewOrderProcessingStatus();
        $this->addNewOrderClosedStatus();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * Create new order processing status and assign it to the existent state
     *
     * @return void
     */
    private function addNewOrderProcessingStatus()
    {
        $arrayStatusCode = [
            'ready_to_pick',
            'picking',
            'picked',
            'transporting',
            'delivering',
            'delivery_fail',
        ];
        $arrayStatusLabel = [
            'Ready to Pick',
            'Picking',
            'Picked',
            'Transporting',
            'Delivering',
            'Delivery Fail',
        ];
        foreach ($arrayStatusCode as $index => $code) {
            $this->addStatus($code, $arrayStatusLabel[$index], Order::STATE_PROCESSING);
        }
    }

    /**
     * Create new order closed status and assign it to the existent state
     *
     * @return void
     */
    private function addNewOrderClosedStatus()
    {
        $arrayStatusCode = ['exception'];
        $arrayStatusLabel = ['Exception'];
        foreach ($arrayStatusCode as $index => $code) {
            $this->addStatus($code, $arrayStatusLabel[$index], Order::STATE_CLOSED);
        }
    }

    /**
     * Create new order status and assign it to the existent state
     *
     * @param string $statusCode
     * @param string $label
     * @param string $state
     * @return void
     */
    private function addStatus($statusCode, $label, $state)
    {
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();

        $status->setData([
            'status' => $statusCode,
            'label' => $label,
        ]);

        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }

        $status->assignState($state, false, true);
    }
}
