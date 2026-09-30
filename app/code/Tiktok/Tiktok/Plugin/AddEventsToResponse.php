<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Plugin;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Tiktok\Tiktok\Model\Event\Management\EventManager;
use Magento\Checkout\Controller\Cart\Add;

class AddEventsToResponse
{
    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Event\Management\EventManager $eventManager
     */
    public function __construct(
        private readonly EventManager $eventManager,
    ) {
    }

    /**
     * After execute for add product to shopping cart action
     *
     * @param \Magento\Checkout\Controller\Cart\Add $subject
     * @param \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface $result
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        Add $subject,
        ResponseInterface|ResultInterface $result
    ): ResponseInterface|ResultInterface {
        try {
            if (!$this->eventManager->hasEvents()) {
                return $result;
            }
            if (method_exists($result, 'setContent')) {
                $data = $result->getContent();
                if ($data === '[]') {
                    $result->setContent(json_encode($this->eventManager->prepareResponse(), JSON_THROW_ON_ERROR));
                }
            }
        } catch (\JsonException $e) {
            return $result;
        }
        return $result;
    }
}
