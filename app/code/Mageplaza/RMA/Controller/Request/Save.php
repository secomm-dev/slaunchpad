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

namespace Mageplaza\RMA\Controller\Request;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Mail\Template\TransportBuilder;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Request
 */
class Save extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * @var EncryptorInterface
     */
    protected $_encrypt;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var OrderResource
     */
    protected $_orderResource;

    /**
     * @var HelperImage
     */
    protected $_helperImage;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param DateTime $dateTime
     * @param EncryptorInterface $encrypt
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param OrderFactory $orderFactory
     * @param OrderResource $orderResource
     * @param HelperImage $helperImage
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        DateTime $dateTime,
        EncryptorInterface $encrypt,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        OrderFactory $orderFactory,
        OrderResource $orderResource,
        HelperImage $helperImage,
        HelperData $helperData,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_dateTime = $dateTime;
        $this->_encrypt = $encrypt;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_orderFactory = $orderFactory;
        $this->_orderResource = $orderResource;
        $this->_helperImage = $helperImage;
        $this->_helperData = $helperData;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     * @throws LocalizedException
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data = $this->getRequest()->getPost('request')) {
            /** @var Order $order */
            $order = $this->_orderFactory->create();
            $this->_orderResource->load($order, $data['order_id']);
            $billingAddress = $order->getBillingAddress();
            $customerEmail = $billingAddress ? $billingAddress->getEmail() : '';
            if (!isset($data['customer_email']) || $data['customer_email'] !== $customerEmail) {
                $this->messageManager->addErrorMessage(__('Your request information is not valid.'));

                return $this->redirectRequestIndexPage($resultRedirect);
            }

            $orderItems = $order->getItemsCollection();
            if (isset($data['all_products'])) {
                foreach ($orderItems as $item) {
                    /** @var OrderItem $item */
                    if ($item->getParentItem()) {
                        continue;
                    }
                    if ($this->_helperData->getAvailableQtyToReturn($item) > 0
                        && $this->_helperData->canReturnProduct($item->getProductId(), $order)) {
                        $data['products'][$item->getId()] = [
                            'product_id' => $item->getProductId(),
                            'item_id' => $item->getId(),
                            'selected' => '1',
                            'name' => $item->getName(),
                            'sku' => $item->getSku(),
                            'price' => $item->getPrice(),
                            'qty_rma' => $this->_helperData->getAvailableQtyToReturn($item),
                            'price_returned' => $this->_helperData
                                    ->getAvailableQtyToReturn($item) * $item->getPrice(),
                            'reason' => isset($data['all_products']['reason'])
                                ? $data['all_products']['reason'] : '',
                            'solution' => isset($data['all_products']['solution'])
                                ? $data['all_products']['solution'] : '',
                            'additional_fields' => isset($data['all_products']['additional_fields'])
                                ? $data['all_products']['additional_fields'] : [],
                        ];
                    }
                }
            }
            foreach ($orderItems as $item) {
                /** @var OrderItem $item */
                if ($item->getParentItem()) {
                    continue;
                }
                $requestItem = $data['products'];
                if (isset($requestItem[$item->getId()])
                    && (($requestItem[$item->getId()]['qty_rma'] > $this->_helperData->getAvailableQtyToReturn($item))
                        || !$requestItem[$item->getId()]['qty_rma'])) {
                    $this->messageManager->addErrorMessage(__('Your request item quantity is not valid.'));

                    return $this->redirectRequestIndexPage($resultRedirect);
                }
            }
            /** Upload files */
            if (isset($data['files']) && count($data['files'])) {
                $data['files'] = HelperData::jsonEncode($this->_helperImage->processImagesGallery($data['files']));
            }
            /** @var Request $request */
            $request = $this->_requestFactory->create();
            $this->prepareData($request, $data);
            $this->_eventManager->dispatch('mageplaza_rma_request_before_save', [
                'rma_request' => $request,
                'request' => $this->getRequest()
            ]);

            try {
                $this->_requestResource->save($request);
                $this->messageManager->addSuccessMessage(__('You have submitted the return request.'));
                $this->notifyToAdmin($request);
                if ($this->_helperData->isLoggedIn()) {
                    $resultRedirect->setPath('mprma/*/view', ['request_id' => $request->getId()]);
                } else {
                    $protectKey = $request->getId() . '_' . strtotime($request->getUpdatedAt());
                    $resultRedirect->setPath('mprma/*/view', [
                        'request_id' => $request->getId(),
                        'guest_key' => $this->_encrypt->hash($protectKey)
                    ]);
                }

                return $resultRedirect;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while submitting the Request.')
                );
            }
        }

        $resultRedirect->setPath('mprma/*/');

        return $resultRedirect;
    }

    /**
     * @param Request $request
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($request, $data)
    {
        if ($request->getCreatedAt() === null) {
            $data['created_at'] = $this->_dateTime->date();
        }
        $data['updated_at'] = $this->_dateTime->date();
        if (isset($data['products'])) {
            $data['products'] = HelperData::jsonEncode($data['products']);
        }
        if (!isset($data['status_id'])) {
            $data['status_id'] = $this->_helperData->getRequestConfig('default_status');
        }

        $request->addData($data);

        return $this;
    }

    /**
     * @param Redirect $resultRedirect
     *
     * @return mixed
     */
    protected function redirectRequestIndexPage($resultRedirect)
    {
        $resultRedirect->setPath('mprma/*/index');
        if ($this->_helperData->isLoggedIn()) {
            $resultRedirect->setPath(
                'mprma/*/index',
                ['customer_id' => $this->_helperData->getCustomerId()]
            );
        }

        return $resultRedirect;
    }

    /**
     * @param Request $request
     *
     * @throws NoSuchEntityException
     */
    public function notifyToAdmin($request)
    {
        $adminEmails = $this->_helperData->getEmailConfig('admin_emails');

        if ($adminEmails) {
            $adminEmails   = preg_replace('/\s+/', '', explode(',', $adminEmails));
            $currentStore  = $this->storeManager->getStore();
            $sender        = $this->_helperData->getEmailConfig('sender');
            $emailTemplate = $this->_helperData->getEmailConfig('request_admin_template');

            foreach ($adminEmails as $adminEmail) {
                try {
                    $vars = [
                        'request_increment_id' => $request->getIncrementId(),
                        'request_content' => $request->getComment(),
                        'date' => $request->getCreatedAt()
                    ];
                    $emailInfo = [
                        'current_store_id' => $currentStore->getId(),
                        'to_email' => $adminEmail,
                        'email_template' => $emailTemplate,
                        'sender' => $sender
                    ];
                    $this->_helperData->sendMail($request, $emailInfo, $vars);
                } catch (Exception $e) {
                    $this->logger->critical($e);
                }
            }
        }
    }
}
