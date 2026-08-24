<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Controller\Adminhtml\Preview;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Store-aware admin preview. Thin controller: delegates to the SAME
 * LlmsTxtGenerator (via the provider's fresh seam, bypassing the public
 * response cache) so preview bytes equal runtime bytes for the same state.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = Config::ACL_PREVIEW;

    /**
     * @var LlmsTxtProvider
     */
    private $provider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context backend action context
     * @param LlmsTxtProvider $provider llms.txt body provider (fresh seam)
     * @param StoreManagerInterface $storeManager default store view resolver
     */
    public function __construct(
        Context $context,
        LlmsTxtProvider $provider,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->provider = $provider;
        $this->storeManager = $storeManager;
    }

    /**
     * Render a fresh (cache-bypassing) llms.txt preview for the selected store.
     *
     * @return Raw|ResponseInterface raw text preview result
     */
    public function execute()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);

        if ($storeId === 0) {
            $storeId = (int) $this->storeManager->getDefaultStoreView()->getId();
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $result->setContents($this->provider->getFresh($storeId));

        return $result;
    }
}
