<?php
namespace Tiktok\Tiktok\Controller\TikTokEvents;

use Tiktok\Tiktok\Model\Event\Pool;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;

class Index implements HttpPostActionInterface
{
    /**
     * PixelEventTracking construct
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Tiktok\Tiktok\Model\Event\Pool $eventPool
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        protected Context $context,
        private readonly Pool $eventPool,
        protected JsonFactory $jsonFactory,
        protected ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Retrieve TikTok View Events
     *
     * @param array $eventTypes
     * @param int|null $productId
     * @return array
     */
    public function getTiktokViewEvents(array $eventTypes = [], ?int $productId = null): array
    {
        if (!$eventTypes) {
            $eventTypes = ['PageView'];
        }

        $eventData = [];
        $product = null;
        if ($productId) {
            $product = $this->productRepository->getById($productId);
        }

        foreach ($eventTypes as $eventType) {
            if ($data = $this->eventPool->execute($eventType, ['product' => $product])) {
                $eventData[] = $data;
            }
        }
        return $eventData;
    }

    /**
     * Execute action based on request and return result
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $eventTypes = $this->context->getRequest()->getParam('tiktok_events');
        $product = ($this->context->getRequest()->getParam('product') ?: null);
        $events = $this->getTiktokViewEvents($eventTypes, $product);

        $result = $this->jsonFactory->create();
        return $result->setData(
            ['content' => $events]
        );
    }
}
