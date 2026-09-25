<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Render disconnect button html element in Stores Configuration
 */
class DisconnectButton extends Field
{
    /**
     * @var \Magento\Backend\Block\Template\Context
     */
    private Context $context;

    /**
     * Init dependencies
     *
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
     * Retrieve disconnect button element HTML markup
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $websiteId = $this->context->getRequest()->getParam('website');
        $buttonHtml = '<button type="button" id="disconnect-button" class="scalable" ' .
            'style="background: #e02b27; color: white;">' .
            __('Disconnect') .
            '</button>';

        $buttonHtml .= '
            <script type="text/javascript">
                require(["jquery", "Magento_Ui/js/modal/modal"], function($, modal) {
                    $("#disconnect-button").on("click", function() {
                        var options = {
                            type: "popup",
                            title: "Disconnect TikTok API",
                            modalClass: "modal-popup",
                            buttons: [{
                                text: "Yes, Disconnect",
                                class: "action-primary",
                                click: function () {
                                    window.location.href = "' .
                                        $this->getUrl(
                                            'tiktok/account/disconnect',
                                            ['website_id' => $websiteId]
                                        ) . '";
                                }
                            }, {
                                text: "Cancel",
                                class: "action-secondary",
                                click: function () {
                                    this.closeModal();
                                }
                            }]
                        };
                        var msg = "" +
                         "<p>Are you sure you want to disconnect the TikTok API? This action cannot be undone.</p>" +
                          "";
                        var popup = modal(options, $("<div>").html(msg));
                        popup.openModal();
                    });
                });
            </script>';

        return $buttonHtml;
    }
}
