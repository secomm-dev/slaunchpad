<?php

namespace Boolfly\GiaoHangNhanh\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Exception;

class UpgradeData implements UpgradeDataInterface
{
    protected $statusFactory;
	protected $statusResourceFactory;

	public function __construct(
		\Magento\Sales\Model\Order\StatusFactory $statusFactory,
        \Magento\Sales\Model\ResourceModel\Order\StatusFactory $statusResourceFactory
	) {
		$this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
	}

	public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		if (version_compare($context->getVersion(), '1.1.0', '<')) {
			$this->addNewOrderProcessingStatus();
			$this->addNewOrderClosedStatus();
		}
	}

	/**
     * Create new order processing status and assign it to the existent state
     * @return void
     * @throws Exception
     */
    protected function addNewOrderProcessingStatus()
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
		foreach ($arrayStatusCode as $index=>$code) {
			$this->addStatus($code, $arrayStatusLabel[$index], Order::STATE_PROCESSING);
		}
    }

	/**
     * Create new order closed status and assign it to the existent state
     * @return void
     * @throws Exception
     */
    protected function addNewOrderClosedStatus()
    {
		$arrayStatusCode = [
			'exception',
		];
		$arrayStatusLabel = [
			'Exception',
		];
		foreach ($arrayStatusCode as $index=>$code) {
			$this->addStatus($code, $arrayStatusLabel[$index], Order::STATE_CLOSED);
		}
    }

	 /**
     * Create new order status and assign it to the existent state
     * @return void
     * @throws Exception
     */
    protected function addStatus($statusCode, $label, $state)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Status $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        /** @var \Magento\Sales\Model\Order\Status $status */
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