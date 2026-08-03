<?php
namespace Magezon\Core\Controller\Adminhtml\Users\Me;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magezon\Core\Model\UserConfigProcessor;

class Detail extends Action
{
    /**
     * @var AuthSession
     */
    protected $authSession;
    
    /**
     * @var UserConfigProcessor
     */
    protected $userConfigProcessor;

    /**
     * @param Context $context
     * @param AuthSession $authSession
     * @param UserConfigProcessor $userConfigProcessor
     */
    public function __construct(
        Context $context,
        AuthSession $authSession,
        UserConfigProcessor $userConfigProcessor
    ) {
        parent::__construct($context);
        $this->authSession = $authSession;
        $this->userConfigProcessor = $userConfigProcessor;
    }

    public function execute()
    {
        $post = $this->getRequest()->getPostValue();
        $extra = (array) $this->authSession->getUser()->getExtra();
        $config = isset($extra['persisted_preferences']) ? $extra['persisted_preferences'] : [];
        $config = $this->userConfigProcessor->processReadConfig($config);

        $this->getResponse()->representJson(
            $this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode([
                'meta' => [
                    'persisted_preferences' => $config
                ]
            ])
        );
        return;
    }
}