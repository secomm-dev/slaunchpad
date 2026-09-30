<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Controller\Adminhtml\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use JsonException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Cron\Model\Schedule;
use Tiktok\Tiktok\Logger\TiktokLogger;

class OnBoardingCallBack extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Tiktok_Tiktok::general';

    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = ['onboardingcallback'];

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder
     */
    private TiktokApiClientBuilder $tiktokApiClientBuilder;

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager
     */
    private SyncFlagManager $syncFlagManager;

    /**
     * @var \Magento\Cron\Model\ScheduleFactory
     */
    private ScheduleFactory $scheduleFactory;

    /**
     * Init dependencies
     *
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $resultRedirectFactory
     * @param TiktokApiClientBuilder $tiktokApiClientBuilder
     * @param SyncFlagManager $syncFlagManager
     * @param ScheduleFactory $scheduleFactory
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        RedirectFactory $resultRedirectFactory,
        TiktokApiClientBuilder $tiktokApiClientBuilder,
        SyncFlagManager $syncFlagManager,
        ScheduleFactory $scheduleFactory,
        private readonly TiktokLogger $logger
    ) {
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->tiktokApiClientBuilder = $tiktokApiClientBuilder;
        $this->syncFlagManager = $syncFlagManager;
        $this->scheduleFactory = $scheduleFactory;
        parent::__construct($context);
    }

    /**
     * Execute Link Account
     *
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws JsonException
     */
    public function execute()
    {
        $authCode = $this->getRequest()->getParam('auth_code');
        $code = $this->getRequest()->getParam('code');
        $website = (int) $this->getRequest()->getParam('state');
        $resultRedirect = $this->resultRedirectFactory->create();
        $tiktokApiClient = $this->tiktokApiClientBuilder->create($website);
        $scopeManager = $tiktokApiClient->getScopeManager();

        if ($scopeManager->getAuthCode() && $scopeManager->getCode()) {
            $this->messageManager
                ->addErrorMessage(
                    __(
                        'Your TikTok account is already linked.
                        To link a new account, please disconnect the existing one first.'
                    )
                );
            $resultRedirect->setPath('admin/system_config/edit', ['section' => 'tiktok', 'website' => $website]);
            return $resultRedirect;
        }

        if ($authCode && $code) {
            $accessTokenCreated = $tiktokApiClient->createAccessToken($authCode);
            if (!$accessTokenCreated) {
                $this->messageManager->addErrorMessage(
                    __('Failed to get access token. Please relink your account')
                );
                $resultRedirect->setPath(
                    'admin/system_config/edit',
                    ['section' => 'tiktok', 'website' => $website]
                );
                return $resultRedirect;
            }
            $this->messageManager->addSuccessMessage(__('Your TikTok account has been successfully linked.'));
            $tiktokApiClient->getProfile();
            $this->syncFlagManager->setCatalogSyncFlag($website);
            $this->scheduleOneTimeJob();
        } else {
            $this->messageManager->addErrorMessage(__('Failed to link your TikTok account. Please try again.'));
        }
        $resultRedirect->setPath('admin/system_config/edit', ['section' => 'tiktok', 'website' => $website]);
        return $resultRedirect;
    }

    /**
     * Schedule job to sync partner data
     *
     * @return void
     */
    private function scheduleOneTimeJob(): void
    {
        try {
            $schedule = $this->scheduleFactory->create();
            $schedule->setJobCode('tiktok_partner_data_sync');
            $schedule->setStatus(Schedule::STATUS_PENDING);
            $schedule->setScheduledAt(date('Y-m-d H:i:s', strtotime('today 23:55')));
            $schedule->save();
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (\Exception $e) {
            $this->logger->error('Error creating one time partner data sync job. ' . $e->getMessage());
        }
    }
}
