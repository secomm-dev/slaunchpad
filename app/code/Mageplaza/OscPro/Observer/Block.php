<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscPro\Observer;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Mageplaza\OscPro\Helper\Data;
use Psr\Log\LoggerInterface;

/**
 * Class Block
 * @package Mageplaza\OscPro\Observer
 */
class Block implements ObserverInterface
{
    /**
     * @var bool
     */
    private $isSet = false;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @varLoggerInterface
     */
    private $logger;

    /**
     *
     * @param Data $helperData
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $helperData,
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->helperData = $helperData;
        $this->request    = $request;
        $this->logger     = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            if ($this->request->getFullActionName() === 'onestepcheckout_index_index') {
                /** @var AbstractBlock $block */
                $block              = $observer->getEvent()->getBlock();
                $transport          = $observer->getEvent()->getTransport();
                $loadingSpeedConfig = $this->helperData->getLoadingSpeedConfig();
                $html               = $transport->getHtml();
                $html               .= '<script> window.loadingSpeedConfig  = ' . $this->helperData->jsonEncodeData($loadingSpeedConfig) . '</script>';
                if (!$this->isSet && $block->getLayout()->isBlock('require.js')) {
                    $transport->setHtml($html);
                    $this->isSet = true;
                }
            }

        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

    }
}
