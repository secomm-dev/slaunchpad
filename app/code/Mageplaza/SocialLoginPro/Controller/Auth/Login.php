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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */
namespace Mageplaza\SocialLoginPro\Controller\Auth;

use Exception;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Mageplaza\SocialLogin\Helper\Social as SocialHelper;
use Mageplaza\SocialLogin\Model\Social;

/**
 * Class Login
 *
 * @package Mageplaza\SocialLoginPro\Controller\Auth
 */
class Login extends Action
{
    /**
     * @type SocialHelper
     */
    protected $apiHelper;

    /**
     * @type Social
     */
    protected $apiObject;

    /**
     * @var Url
     */
    public $_backendUrl;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * Login constructor.
     *
     * @param Context $context
     * @param SocialHelper $apiHelper
     * @param Social $apiObject
     * @param RawFactory $resultRawFactory
     * @param Url $backendUrl
     */
    public function __construct(
        Context $context,
        SocialHelper $apiHelper,
        Social $apiObject,
        RawFactory $resultRawFactory,
        Url $backendUrl
    ) {
        $this->apiHelper        = $apiHelper;
        $this->apiObject        = $apiObject;
        $this->resultRawFactory = $resultRawFactory;
        $this->_backendUrl      = $backendUrl;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Raw|Redirect|ResultInterface|void
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $type   = $this->apiHelper->setType($params['type']);

        if (!$type) {
            $this->_forward('noroute');

            return;
        }

        try {
            $userProfile = $this->apiObject->getUserProfile($type);

            if (isset($params['connect'])) {
                $socialObject = $this->getSocialCollection($type, Social::STATUS_PROCESS);
                if ($this->isConnectExist($userProfile->identifier)) {
                    $socialObject->delete();
                    $this->setBodyResponse(sprintf(
                        __('This %s account is already linked to 1 other admin account. Please try again.'),
                        $type
                    ));

                    return;
                }

                if ($socialObject->getUserId()) {
                    $this->apiObject->updateAuthCustomer(
                        $socialObject->getSocialCustomerId(),
                        $userProfile->identifier
                    );
                }
            } else {
                $socialObject = $this->getSocialCollection(
                    $type,
                    Social::STATUS_CONNECT,
                    $userProfile->identifier
                );

                if (empty($socialObject->getData())) {
                    $url = $this->_backendUrl->getUrl('adminhtml/auth/login', ['denied' => true]);
                } else {
                    $this->apiObject->updateStatus(
                        $socialObject->getSocialCustomerId(),
                        Social::STATUS_LOGIN
                    );
                    $url = $this->_backendUrl->getUrl(
                        'adminhtml/auth/login',
                        [
                            'type' => $type,
                            'id'   => base64_encode($userProfile->identifier)
                        ]
                    );
                }

                return $this->resultRedirectFactory->create()->setUrl($url);
            }
        } catch (Exception $e) {
            $this->setBodyResponse($e->getMessage());

            return;
        }

        return $this->_appendJs();
    }

    /**
     * @param $message
     */
    protected function setBodyResponse($message)
    {
        $content = '<html><head></head><body>';
        $content .= '<div class="message message-error">' . __('Ooophs, we got an error: %1', $message) . '</div>';
        $content .= <<<Style
<style type="text/css">
    .message{
        background: #fffbbb;
        border: none;
        border-radius: 0;
        color: #333333;
        font-size: 1.4rem;
        margin: 0 0 10px;
        padding: 1.8rem 4rem 1.8rem 1.8rem;
        position: relative;
        text-shadow: none;
    }
    .message-error{
        background:#ffcccc;
    }
</style>
Style;
        $content .= '</body></html>';
        $this->getResponse()->setBody($content);
    }

    /**
     * @return Raw
     */
    public function _appendJs()
    {
        /**
         * @var Raw $resultRaw
         */
        $resultRaw = $this->resultRawFactory->create();

        return $resultRaw->setContents(
            '<script>
                window.opener.location.reload(true);
                window.close();
            </script>'
        );
    }

    /**
     * @param $type
     * @param $status
     * @param null $identifier
     *
     * @return DataObject
     */
    public function getSocialCollection($type, $status, $identifier = null)
    {
        $collection = $this->apiObject->getCollection()
            ->addFieldToSelect('user_id')
            ->addFieldToSelect('social_customer_id')
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('status', $status);

        if ($identifier === null) {
            $collection->addFieldToFilter('social_id', ['null' => true]);
        }

        return $collection->getFirstItem();
    }

    /**
     * @param $identifier
     *
     * @return bool
     */
    public function isConnectExist($identifier)
    {
        $collection = $this->apiObject->getCollection()
            ->addFieldToFilter('user_id', ['notnull' => true])
            ->addFieldToFilter('social_id', $identifier)
            ->getFirstItem()->getData();

        if ($collection) {
            return true;
        }

        return false;
    }
}
