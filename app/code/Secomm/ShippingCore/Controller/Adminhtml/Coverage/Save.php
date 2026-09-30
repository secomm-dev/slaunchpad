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
 * FEAT-QA23PZ / TASK-WY6WP5 — persist the Shipping Coverage form through
 * CarrierCoverageConfigAdapter (validate → write the existing `carriers/<code>/...`
 * paths → clean config cache). Create requests carry `is_create=1`: when an explicit
 * config already exists for the same type+code the write is REJECTED (directive §6 —
 * one explicit config per target; the storage itself is an upsert so duplicates can
 * never accumulate either way). Invalid submissions are REJECTED with the validator
 * message, never silently altered. POST-only.
 */
class Save extends Action implements HttpPostActionInterface
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

        try {
            $this->assertNotDuplicateCreate($identity, (string) $request->getParam('is_create'));
            $zones = $this->configAdapter->save(
                $identity,
                (string) $request->getParam('destination_scope'),
                (array) $request->getParam('allowed_zone_codes', []),
                (string) $request->getParam('rate_source_mode'),
                (string) $request->getParam('address_resolution_policy')
            );
            $this->messageManager->addSuccessMessage(
                __('Shipping coverage for "%1" has been saved (%2 zone(s)).', $this->targetRegistry->getLabel($identity), count($zones))
            );
        } catch (NoSuchEntityException $exception) {
            // MUST precede the LocalizedException catch — NoSuchEntity extends it.
            $this->messageManager->addErrorMessage(__('This target is not registered for shipping coverage.'));
        } catch (LocalizedException $validationException) {
            $this->messageManager->addErrorMessage($validationException->getMessage());
        }
        $redirect->setPath('*/*/index');

        return $redirect;
    }

    /**
     * Duplicate guard for the create flow (directive §6F): a create submission for a
     * target that already has an explicit config must fail without writing. Edit
     * submissions (`is_create=0`) always target the one existing config and are allowed.
     *
     * @throws LocalizedException when a create collides with an existing config
     */
    private function assertNotDuplicateCreate(CoverageTargetIdentity $identity, string $isCreate): void
    {
        if ($isCreate === '1' && $this->configAdapter->hasExplicitConfig($identity)) {
            throw new LocalizedException(__(
                'An explicit coverage configuration already exists for "%1". Edit the existing configuration instead.',
                $this->targetRegistry->getLabel($identity)
            ));
        }
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
        if ($identity->type() === CoverageTargetType::METHOD) {
            $this->messageManager->addErrorMessage(__(
                'Method-level coverage is reserved for a future release — only carrier coverage can be configured in P1.'
            ));

            return null;
        }

        return $identity;
    }
}
