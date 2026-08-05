<?php
namespace Magezon\Core\Controller\Adminhtml\Users\Me;

use Magento\Backend\App\Action\Context;

class Index extends \Magento\Backend\App\Action
{
    public function execute()
    {
        $request = $this->getRequest();

        if ($request->isPut()) {
            $this->_forward('save');
        }
    }
}