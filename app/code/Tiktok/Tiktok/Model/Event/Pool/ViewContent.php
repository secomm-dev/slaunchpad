<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Pool;

use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * @inheritdoc
 */
class ViewContent implements MetadataInterface
{
    /**
     * ViewContent construct
     *
     * @param \Tiktok\Tiktok\Model\Event\TiktokEventFactory $eventFactory
     * @param \Magento\Catalog\Helper\Data $catalogHelper
     */
    public function __construct(
        private readonly TiktokEventFactory $eventFactory,
        private readonly CatalogHelper $catalogHelper
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(?array $context = null): array
    {
        /** @var TiktokEvent $event */
        $event = $this->eventFactory->create();

        $product = isset($context['product']) && $context['product'] instanceof ProductInterface
            ? $context['product']
            : $this->catalogHelper->getProduct();

        $event->setEventName('ViewContent');

        if ($product && $product->getId()) {
            $event->addProduct($product);
        }

        $event->publish();

        return $event->getEvent();
    }
}
