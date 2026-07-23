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

namespace Mageplaza\SocialLoginPro\Controller\Adminhtml\Social;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\SocialLogin\Model\Social;
use Mageplaza\SocialLogin\Model\SocialFactory as SocialModel;

/**
 * Class Disconnect
 *
 * @package Mageplaza\SocialLoginPro\Controller\Adminhtml\Social
 */
class Disconnect extends Action
{
    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var SocialModel
     */
    protected $_socialModel;

    /**
     * Disconnect constructor.
     *
     * @param Session $authSession
     * @param SocialModel $socialFactory
     * @param Context $context
     */
    public function __construct(
        Session $authSession,
        SocialModel $socialFactory,
        Context $context
    ) {
        $this->_authSession = $authSession;
        $this->_socialModel = $socialFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $type = $this->_request->getParam('type');
        if (!$this->_authSession->isLoggedIn()) {
            return $this->_redirect('*');
        }
        $userId     = $this->_authSession->getUser()->getId();
        $collection = $this->_socialModel->create()->getCollection();

        try {
            /**@var Social $user */
            $user = $collection->addFieldToFilter('type', $type)->addFieldToFilter('user_id', $userId)->getFirstItem();
            if ($user->getSocialCustomerId()) {
                $user->delete();
            }
        } catch (Exception $e) {
            return $this->getResponse()->representJson(AbstractData::jsonEncode(['errors' => $e->getMessage()]));
        }

        return;
    }
}
