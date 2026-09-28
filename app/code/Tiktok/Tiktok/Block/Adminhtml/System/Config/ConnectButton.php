<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Block\Adminhtml\System\Config;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;

/**
 * Render connect button html element in Stores Configuration
 */
class ConnectButton extends Field
{
    /**
     * Path to template file in theme.
     *
     * @var string
     */
    protected $_template = 'Tiktok_Tiktok::system/config/connect_button.phtml';

    /**
     * @var \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder
     */
    private TiktokApiClientBuilder $tiktokApiClientBuilder;

    /**
     * @var \Magento\Backend\Block\Template\Context
     */
    private Context $context;

    /**
     * Init dependencies
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        TiktokApiClientBuilder $tiktokApiClientBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);  // Pass $data to parent constructor
        $this->tiktokApiClientBuilder = $tiktokApiClientBuilder;
        $this->context = $context;
    }

    /**
     * Return Connect URL
     *
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConnectUrl(): string
    {
        $externalData = $this->getExternalData();
        return "https://ads.tiktok.com/business-extension/auth?external_data={$externalData}";
    }

    /**
     * Return External Data
     *
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getExternalData()
    {
        $websiteId = (int) $this->context->getRequest()->getParam('website');
        return $this->tiktokApiClientBuilder->create($websiteId)->getExternalData();
    }

    /**
     * Retrieve element HTML markup
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }
}
