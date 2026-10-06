<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\ViewModel\Order;

use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Secomm\EInvoiceCore\Model\TemplateOptionsProviderPool;
use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * View model for electronic invoice panel on admin order view.
 */
class Einvoice
{
    /**
     * @param Config $config
     * @param IssueInvoiceService $issueInvoiceService
     * @param TemplateOptionsProviderPool $templateOptionsProviderPool
     */
    public function __construct(
        private readonly Config $config,
        private readonly IssueInvoiceService $issueInvoiceService,
        private readonly TemplateOptionsProviderPool $templateOptionsProviderPool
    ) {
    }

    /**
     * Whether the panel should render for the given store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function canShow(?int $storeId): bool
    {
        return $storeId !== null && $this->config->isEnabled($storeId);
    }

    /**
     * Whether Admin can trigger a new issue attempt.
     *
     * @param int|null $orderId
     * @param int|null $storeId
     * @return bool
     */
    public function canIssue(?int $orderId, ?int $storeId): bool
    {
        if ($orderId === null || !$this->canShow($storeId)) {
            return false;
        }

        $log = $this->issueInvoiceService->getLatestLogForOrder($orderId);
        if ($log === null) {
            return true;
        }

        return $log->getStatus() !== IssueLog::STATUS_SUCCESS;
    }

    /**
     * Whether Admin can cancel an issued invoice.
     *
     * @param int|null $orderId
     * @param int|null $storeId
     * @return bool
     */
    public function canCancel(?int $orderId, ?int $storeId): bool
    {
        if ($orderId === null || !$this->canShow($storeId)) {
            return false;
        }

        return $this->issueInvoiceService->getIssuedLogForOrder($orderId) !== null;
    }

    /**
     * Whether the latest invoice has provider documents to fetch.
     *
     * @param int|null $orderId
     * @return bool
     */
    public function hasIssuedInvoice(?int $orderId): bool
    {
        return $this->issueInvoiceService->getIssuedLogForOrder($orderId) !== null;
    }

    /**
     * Whether the Commercial discount (CKTM) accordion may be shown on the order tab.
     */
    public function canIssueCommercialDiscount(?int $orderId, ?int $storeId): bool
    {
        if ($orderId === null || !$this->canShow($storeId)) {
            return false;
        }

        return $this->config->isCommercialDiscountEnabled($storeId)
            && $this->hasIssuedInvoice($orderId);
    }

    /**
     * Template options for the manual issue dropdown.
     *
     * @param int|null $storeId
     * @return array<int, array{value: string, label: string}>
     */
    public function getTemplateOptions(?int $storeId): array
    {
        $provider = $this->templateOptionsProviderPool->get($storeId);
        if ($provider === null) {
            return [];
        }

        return $provider->getOptions($storeId);
    }

    /**
     * Default/configured template id for pre-selection.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDefaultTemplateId(?int $storeId): string
    {
        $provider = $this->templateOptionsProviderPool->get($storeId);
        if ($provider === null) {
            return '';
        }

        return $provider->getDefaultId($storeId);
    }

    /**
     * Latest log row for display.
     *
     * @param int|null $orderId
     * @return IssueLog|null
     */
    public function getLatestLog(?int $orderId): ?IssueLog
    {
        if ($orderId === null) {
            return null;
        }

        return $this->issueInvoiceService->getLatestLogForOrder($orderId);
    }

    /**
     * Issued invoice log (refresh/download/cancel target), if any.
     */
    public function getIssuedLog(?int $orderId): ?IssueLog
    {
        if ($orderId === null) {
            return null;
        }

        return $this->issueInvoiceService->getIssuedLogForOrder($orderId);
    }

    /**
     * Log shown in Invoice details (issued log when present, else latest attempt).
     */
    public function getOrderTabLog(?int $orderId): ?IssueLog
    {
        if ($orderId === null) {
            return null;
        }

        return $this->issueInvoiceService->getOrderTabLog($orderId);
    }

    public function getInvoiceStatusLabel(?int $orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }

        return $this->issueInvoiceService->getInvoiceStatusLabel($orderId);
    }
}
