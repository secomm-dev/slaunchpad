<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Coverage;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — RESET TO DEFAULTS (directive §8): removes the explicit DEFAULT-scope
 * coverage values of one registered target; the target stays registered and returns to
 * the documented runtime defaults (missing destination_scope → ALL). WEBSITE/STORE rows
 * are intentionally kept — when any remain, an explicit warning names them because those
 * stores keep their persisted values. The carrier module itself is never touched.
 * POST-only.
 */
class Reset extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::carrier_coverage_manage';

    private CarrierCoverageConfigAdapter $configAdapter;

    private CoverageTargetRegistry $targetRegistry;

    public function __construct(
        Context $context,
        CarrierCoverageConfigAdapter $configAdapter,
        CoverageTargetRegistry $targetRegistry
    ) {
        parent::__construct($context);
        $this->configAdapter = $configAdapter;
        $this->targetRegistry = $targetRegistry;
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $redirect = $this->resultRedirectFactory->create();
        $identity = $this->identityFromRequest();
        if (!$request->isPost() || $identity === null || !$this->targetRegistry->has($identity)) {
            $redirect->setPath('*/*/index');

            return $redirect;
        }

        $label = $this->targetRegistry->getLabel($identity);
        if (!$this->configAdapter->hasExplicitConfig($identity)) {
            $this->messageManager->addNoticeMessage(
                __('Shipping coverage for "%1" is not explicitly configured — it already uses the documented defaults.', $label)
            );
            $redirect->setPath('*/*/index');

            return $redirect;
        }

        $removed = $this->configAdapter->reset($identity);
        $this->messageManager->addSuccessMessage(
            __('Shipping coverage for "%1" was reset to defaults (%2 configuration value(s) removed) — the target remains registered.', $label, $removed)
        );
        $scopedRows = $this->configAdapter->nonDefaultScopeRows($identity);
        if ($scopedRows !== []) {
            $this->messageManager->addWarningMessage(
                __('Scoped overrides for "%1" remain in effect and were NOT removed: %2.', $label, implode(', ', $scopedRows))
            );
        }
        $redirect->setPath('*/*/index');

        return $redirect;
    }

    private function identityFromRequest(): ?CoverageTargetIdentity
    {
        $type = strtoupper(trim((string) $this->getRequest()->getParam('target_type', CoverageTargetType::CARRIER)));
        $code = trim((string) $this->getRequest()->getParam('target_code'));
        if ($code === '') {
            return null;
        }
        try {
            $identity = CoverageTargetIdentity::create($type, $code);
        } catch (LocalizedException) {
            $this->messageManager->addErrorMessage(__('Invalid coverage target type "%1".', $type));

            return null;
        }
        try {
            $this->targetRegistry->get($identity);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This target is not registered for shipping coverage.'));

            return null;
        }

        return $identity;
    }
}
