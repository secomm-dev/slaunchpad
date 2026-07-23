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
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\SocialLogin\Model\SocialFactory as SocialModel;

/**
 * Class ProcessData
 *
 * @package Mageplaza\SocialLoginPro\Controller\Adminhtml\Social
 */
class ProcessData extends Action
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
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * ProcessData constructor.
     *
     * @param Session $authSession
     * @param SocialModel $socialFactory
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Session $authSession,
        SocialModel $socialFactory,
        Context $context,
        PageFactory $pageFactory
    ) {
        $this->_authSession      = $authSession;
        $this->_socialModel      = $socialFactory;
        $this->resultPageFactory = $pageFactory;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     * @throws Exception
     */
    public function execute()
    {
        $params = $this->_request->getParams();

        if ($params['id']) {
            try {
                $social = $this->_socialModel->create();
                $data   = [
                    'type'    => $params['type'],
                    'user_id' => $params['id'],
                    'status'  => $social::STATUS_PROCESS
                ];
                $social->addData($data);
                $social->save();
            } catch (Exception $e) {
                return $this->getResponse()->representJson(AbstractData::jsonEncode(['errors' => $e->getMessage()]));
            }
        }

        return $this->resultPageFactory->create();
    }

    /**
     * Check if user has permissions to access this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }
}
