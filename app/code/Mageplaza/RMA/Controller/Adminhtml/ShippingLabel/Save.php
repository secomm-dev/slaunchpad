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
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ShippingLabelFactory;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\ShippingLabel
 */
class Save extends ShippingLabel
{
    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var Image
     */
    protected $_imageHelper;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param DateTime $date
     * @param ShippingLabelFactory $shippingLabelFactory
     * @param ShippingLabelResource $shippingLabelResource
     * @param Image $imageHelper
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        DateTime $date,
        ShippingLabelFactory $shippingLabelFactory,
        ShippingLabelResource $shippingLabelResource,
        Image $imageHelper
    ) {
        $this->date = $date;
        $this->_imageHelper = $imageHelper;

        parent::__construct(
            $context,
            $coreRegistry,
            $shippingLabelFactory,
            $shippingLabelResource
        );
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data = $this->getRequest()->getPost('shipping_label')) {
            /** @var \Mageplaza\RMA\Model\ShippingLabel $shippingLabel */
            $shippingLabel = $this->initShippingLabel();
            /** get Shipping Label conditions */
            $ruleData = $this->getRequest()->getPost('rule');
            $shippingLabel->loadPost($ruleData);
            $this->prepareData($shippingLabel, $data);

            $this->_eventManager->dispatch('mageplaza_rma_shipping_label_prepare_save', [
                'shipping_label' => $shippingLabel,
                'request' => $this->getRequest()
            ]);

            try {
                $this->_shippingLabelResource->save($shippingLabel);
                $this->messageManager->addSuccessMessage(__('The shipping label has been saved.'));
                $this->_getSession()->setData('mageplaza_rma_shipping_label_data', false);

                if ($this->getRequest()->getParam('back')) {
                    $resultRedirect->setPath('*/*/edit', ['id' => $shippingLabel->getId(), '_current' => true]);
                } else {
                    $resultRedirect->setPath('*/*/');
                }

                return $resultRedirect;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the Shipping Label.')
                );
            }

            $this->_getSession()->setData('mageplaza_rma_shipping_label_data', $data);

            $resultRedirect->setPath('*/*/edit', ['id' => $shippingLabel->getId(), '_current' => true]);

            return $resultRedirect;
        }

        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }

    /**
     * @param \Mageplaza\RMA\Model\ShippingLabel $shippingLabel
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($shippingLabel, $data)
    {
        try {
            $this->_imageHelper->uploadImage(
                $data,
                'image',
                Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL,
                $shippingLabel->getImage()
            );
        } catch (Exception $e) {
            $data['image'] = isset($data['image']['value']) ? $data['image']['value'] : '';
        }

        if ($shippingLabel->getCreatedAt() === null) {
            $data['created_at'] = $this->date->date();
        }
        $data['updated_at'] = $this->date->date();

        $shippingLabel->addData($data);

        return $this;
    }
}
