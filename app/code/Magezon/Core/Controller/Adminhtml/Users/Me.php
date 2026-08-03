<?php
namespace Magezon\Core\Controller\Adminhtml\Users;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;

class Me extends Action
{
    /**
     * @var AuthSession
     */
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
        $request = $this->getRequest();

        if ($request->isGet()) {
            $this->_forward('me_detail');
            return;
        } else if ($request->isPut()) {
            $this->_forward('me_update');
            return;
        }
        
        $this->_forward('noroute');
        return;
    }
}