<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Render sync button html element in Stores Configuration
 */
class SyncNowButton extends Field
{
    /**
     * @var \Magento\Backend\Block\Template\Context
     */
    private Context $context;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->context = $context;
    }

    /**
     * Set template to display the button
     *
     * @var string
     */
    protected $_template = 'Tiktok_Tiktok::system/config/sync_now_button.phtml';

    /**
     * Render the button with a popup confirmation
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Get the URL for the Sync Now action
     *
     * @return string
     */
    public function getSyncUrl(): string
    {
        $websiteId = $this->context->getRequest()->getParam('website');
        return $this->getUrl('tiktok/catalog/sync', ['website_id' => $websiteId]);
    }
}
