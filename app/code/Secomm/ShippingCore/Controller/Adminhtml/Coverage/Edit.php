<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Coverage;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — Shipping Coverage create/edit page for ONE registered target (directive
 * §6/§7: Configure and Edit open the same form; the form decides its mode by whether an
 * explicit coverage config exists). Unknown/unregistered targets are refused (the
 * registry is the opt-in surface; a target without coverage UI keeps its runtime config
 * untouched) and the reserved METHOD type is refused with its own explicit message. The
 * resolved target is registered in the backend registry for the form buttons.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::carrier_coverage_manage';

    public const REGISTRY_KEY = 'secomm_shippingcore_coverage_target';

    private PageFactory $resultPageFactory;

    private CoverageTargetRegistry $targetRegistry;

    private CarrierCoverageConfigAdapter $configAdapter;

    private Registry $registry;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CoverageTargetRegistry $targetRegistry,
        CarrierCoverageConfigAdapter $configAdapter,
        Registry $registry
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->targetRegistry = $targetRegistry;
        $this->configAdapter = $configAdapter;
        $this->registry = $registry;
    }

    /**
     * @return Page|Redirect|ResultInterface
     */
    public function execute()
    {
        $result = $this->resolveTarget();
        if ($result['error']) {
            $redirect = $this->resultRedirectFactory->create();
            $redirect->setPath('*/*/index');

            return $redirect;
        }

        $target = $result['target'];
        $configured = $target !== null && $this->configAdapter->hasExplicitConfig($target->getIdentity());
        if ($target !== null) {
            $this->registry->register(self::REGISTRY_KEY, $target);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_ShippingCore::carrier_coverage');
        $resultPage->getConfig()->getTitle()->prepend($configured
            ? __('Shipping Coverage — %1', $target->getLabel())
            : __('New Coverage'));

        return $resultPage;
    }

    /**
     * Resolution outcome: a registered target (edit), no target (create mode), or an
     * error state (redirect + queued message).
     *
     * @return array{target: CoverageTargetInterface|null, error: bool}
     */
    private function resolveTarget(): array
    {
        $type = strtoupper(trim((string) $this->getRequest()->getParam('target_type', CoverageTargetType::CARRIER)));
        $code = trim((string) $this->getRequest()->getParam('target_code'));

        if ($code === '') {
            // Create mode — the form offers the not-yet-configured registered targets.
            return ['target' => null, 'error' => false];
        }
        if ($type === CoverageTargetType::METHOD) {
            $this->messageManager->addErrorMessage(__(
                'Method-level coverage is reserved for a future release — only carrier coverage can be configured in P1.'
            ));

            return ['target' => null, 'error' => true];
        }
        try {
            $identity = CoverageTargetIdentity::create($type, $code);
        } catch (LocalizedException) {
            $this->messageManager->addErrorMessage(__('Invalid coverage target type "%1".', $type));

            return ['target' => null, 'error' => true];
        }
        try {
            return ['target' => $this->targetRegistry->get($identity), 'error' => false];
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This target is not registered for shipping coverage.'));

            return ['target' => null, 'error' => true];
        }
    }
}
