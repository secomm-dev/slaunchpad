<?php
namespace Magezon\Core\Controller\Adminhtml\Users;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;

class Save extends Action
{
    protected $authSession;

    /**
     * @param Context $context
     * @param AuthSession $authSession
     */
    public function __construct(
        Context $context,
        AuthSession $authSession
    ) {
        parent::__construct($context);
        $this->authSession = $authSession;
    }

    public function execute()
    {
       die('ABC');
    }
}