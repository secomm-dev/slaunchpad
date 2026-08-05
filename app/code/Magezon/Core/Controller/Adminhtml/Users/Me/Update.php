<?php
namespace Magezon\Core\Controller\Adminhtml\Users\Me;

use Magento\Framework\App\Cache\TypeListInterface;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magezon\Core\Model\RequestProcessor;
use Magezon\Core\Model\UserConfigProcessor;
use Magezon\Core\Helper\Data as DataHelper;

class Update extends Action
{
    /**
     * @var AuthSession
     */
    protected $authSession;

    /**
     * @var RequestProcessor
     */
    protected $requestProcessor;

    /**
     * @var UserConfigProcessor
     */
    protected $userConfigProcessor;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    protected $cacheTypeList;

    /**
     * @param Context $context
     * @param AuthSession $authSession
     * @param RequestProcessor $requestProcessor
     * @param UserConfigProcessor $userConfigProcessor
     */
    public function __construct(
        Context $context,
        AuthSession $authSession,
        AuthSession $cacheTypeList,
        RequestProcessor $requestProcessor,
        UserConfigProcessor $userConfigProcessor,
        DataHelper $dataHelper
    ) {
        parent::__construct($context);
        $this->authSession = $authSession;
        $this->cacheTypeList = $cacheTypeList;
        $this->requestProcessor = $requestProcessor;
        $this->userConfigProcessor = $userConfigProcessor;
        $this->dataHelper = $dataHelper;
    }

    public function execute()
    {
        $currentUser = $this->authSession->getUser();
        $extra = (array) $currentUser->getExtra();
        $extra['persisted_preferences'] = $this->userConfigProcessor->processUpdateConfig($this->dataHelper->parseFormData($this->requestProcessor->getPost()['meta']['persisted_preferences']));
        $currentUser->saveExtra($extra);

        $this->cacheTypeList->cleanType('config');

        $this->getResponse()->representJson(
            $this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode([
                'meta' => $extra
            ])
        );
        return;
    }
}