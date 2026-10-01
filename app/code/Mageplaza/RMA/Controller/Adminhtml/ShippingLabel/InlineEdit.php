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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ShippingLabel;
use Mageplaza\RMA\Model\ShippingLabelFactory;
use RuntimeException;

/**
 * Class InlineEdit
 * @package Mageplaza\RMA\Controller\Adminhtml\ShippingLabel
 */
class InlineEdit extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::shipping_label';

    /**
     * @var JsonFactory
     */
    public $jsonFactory;

    /**
     * @var ShippingLabelFactory
     */
    public $shippingLabelFactory;

    /**
     * @var ShippingLabelResource
     */
    protected $_shippingLabelResource;

    /**
     * InlineEdit constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ShippingLabelFactory $shippingLabelFactory
     * @param ShippingLabelResource $shippingLabelResource
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ShippingLabelFactory $shippingLabelFactory,
        ShippingLabelResource $shippingLabelResource
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->shippingLabelFactory = $shippingLabelFactory;
        $this->_shippingLabelResource = $shippingLabelResource;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];
        $shippingLabelItems = $this->getRequest()->getParam('items', []);
        if (!(!empty($shippingLabelItems) && $this->getRequest()->getParam('isAjax'))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        $key = array_keys($shippingLabelItems);
        $shippingLabelId = !empty($key) ? (int)$key[0] : '';
        /** @var ShippingLabel $shippingLabel */
        $shippingLabel = $this->shippingLabelFactory->create();
        $this->_shippingLabelResource->load($shippingLabel, $shippingLabelId);
        try {
            $shippingLabelData = $shippingLabelItems[$shippingLabelId];
            $shippingLabel->addData($shippingLabelData);
            $this->_shippingLabelResource->save($shippingLabel);
        } catch (LocalizedException $e) {
            $messages[] = $this->getErrorWithShippingLabelId($shippingLabel, $e->getMessage());
            $error = true;
        } catch (RuntimeException $e) {
            $messages[] = $this->getErrorWithShippingLabelId($shippingLabel, $e->getMessage());
            $error = true;
        } catch (Exception $e) {
            $messages[] = $this->getErrorWithShippingLabelId(
                $shippingLabel,
                __('Something went wrong while saving the Shipping Label.')
            );
            $error = true;
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * Add ShippingLabel id to error message
     *
     * @param ShippingLabel $shippingLabel
     * @param string $errorText
     *
     * @return string
     */
    public function getErrorWithShippingLabelId($shippingLabel, $errorText)
    {
        return '[Shipping Label ID: ' . $shippingLabel->getId() . '] ' . $errorText;
    }
}
