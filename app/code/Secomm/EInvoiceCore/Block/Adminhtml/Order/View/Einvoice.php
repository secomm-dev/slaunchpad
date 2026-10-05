<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use IntlDateFormatter;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Secomm\EInvoiceCore\ViewModel\Order\Einvoice as EinvoiceViewModel;
use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Admin order view tab for electronic invoice actions and status.
 */
class Einvoice extends Template implements TabInterface
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param EinvoiceViewModel $viewModel
     * @param FormKey $formKey
     * @param Json $json
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly EinvoiceViewModel $viewModel,
        private readonly FormKey $formKeyProvider,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Current admin form key.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKeyProvider->getFormKey();
    }

    /**
     * View model with panel business rules.
     *
     * @return EinvoiceViewModel
     */
    public function getViewModel(): EinvoiceViewModel
    {
        return $this->viewModel;
    }

    /**
     * Current order from registry.
     *
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof Order ? $order : null;
    }

    /**
     * Admin URL for manual issue action.
     *
     * @return string
     */
    public function getIssueUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/issue', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    /**
     * Admin URL for refreshing invoice status.
     *
     * @return string
     */
    public function getRefreshStatusUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/refreshstatus', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    /**
     * Admin URL for downloading an issued invoice file.
     *
     * @param string $type
     * @return string
     */
    public function getDownloadUrl(string $type): string
    {
        return $this->getUrl('secomm_einvoice/order/download', [
            'order_id' => $this->getOrder()?->getEntityId(),
            'type' => $type,
        ]);
    }

    /**
     * Admin URL for cancelling an issued invoice.
     *
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/cancelinvoice', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    /**
     * Admin URL for sending invoice email via MeInvoice.
     *
     * @return string
     */
    public function getSendEmailUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/sendemail', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    public function getPreviewUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/preview', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    public function getViewPublishedUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/viewpublished', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    public function getReplaceUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/replace', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    public function getCommercialDiscountUrl(): string
    {
        return $this->getUrl('secomm_einvoice/order/commercialdiscount', [
            'order_id' => $this->getOrder()?->getEntityId(),
        ]);
    }

    public function getInvoiceStatusLabel(?int $orderId): ?string
    {
        return $orderId !== null ? $this->viewModel->getInvoiceStatusLabel($orderId) : null;
    }

    /**
     * Default receiver email from order billing or customer account.
     *
     * @return string
     */
    public function getDefaultReceiverEmail(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '';
        }

        $billing = $order->getBillingAddress();

        return (string) ($billing?->getEmail() ?: $order->getCustomerEmail());
    }

    /**
     * Default receiver display name from billing address.
     *
     * @return string
     */
    public function getDefaultReceiverName(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '';
        }

        $billing = $order->getBillingAddress();
        $name = trim((string) ($billing?->getFirstname() . ' ' . $billing?->getLastname()));

        return $name !== '' ? $name : (string) __('Customer');
    }

    /**
     * Whether publish payload had IsSendEmail=true (from issue request log).
     *
     * @param IssueLog|null $log
     * @return bool
     */
    public function hadSendEmailOnPublish(?IssueLog $log): bool
    {
        if ($log === null) {
            return false;
        }

        $raw = (string) $log->getData('request_payload');
        if ($raw === '') {
            return false;
        }

        try {
            $payload = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return is_array($payload) && !empty($payload['IsSendEmail']);
    }

    /**
     * @inheritdoc
     */
    public function getTabLabel()
    {
        return __('EInvoice');
    }

    /**
     * @inheritdoc
     */
    public function getTabTitle()
    {
        return __('Electronic Invoice');
    }

    /**
     * @inheritdoc
     */
    public function canShowTab(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && $this->viewModel->canShow($order->getStoreId() !== null ? (int) $order->getStoreId() : null);
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * CSS modifier for status badge styling.
     *
     * @param string|null $status
     * @return string
     */
    public function getStatusModifier(?string $status): string
    {
        return match ($status) {
            IssueLog::STATUS_SUCCESS => 'success',
            IssueLog::STATUS_FAILED => 'failed',
            IssueLog::STATUS_CANCELLED => 'cancelled',
            IssueLog::STATUS_PROCESSING => 'processing',
            default => 'pending',
        };
    }

    /**
     * Human-readable status label for display.
     *
     * @param string|null $status
     * @return string
     */
    public function getStatusLabel(?string $status): string
    {
        $label = match ($status) {
            IssueLog::STATUS_PENDING => __('Pending'),
            IssueLog::STATUS_PROCESSING => __('Processing'),
            IssueLog::STATUS_SUCCESS => __('Success'),
            IssueLog::STATUS_FAILED => __('Failed'),
            IssueLog::STATUS_CANCELLED => __('Cancelled'),
            default => $status !== null && $status !== '' ? ucfirst($status) : __('Not issued'),
        };

        return (string) $label;
    }

    /**
     * Format log timestamp for admin display.
     *
     * @param string|null $value
     * @return string
     */
    public function formatLogDatetime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->_localeDate->formatDate(
            new \DateTime($value),
            IntlDateFormatter::MEDIUM,
            true
        );
    }

    /**
     * Human-readable label for issue log reference type (MeInvoice ReferenceType).
     */
    public function getReferenceTypeLabel(int|string|null $referenceType): string
    {
        return IssueLog::getReferenceTypeLabel($referenceType);
    }

    /**
     * Human-readable label for a stored action code.
     *
     * @param string|null $action
     * @return string
     */
    public function getActionLabel(?string $action): string
    {
        return IssueLog::getActionLabel($action);
    }

    /**
     * Order id for accordion localStorage key.
     */
    public function getAccordionOrderId(): ?int
    {
        $order = $this->getOrder();

        return $order !== null && $order->getEntityId() ? (int) $order->getEntityId() : null;
    }

    /**
     * Default open/closed state per accordion section when localStorage is empty.
     *
     * @return array<string, bool>
     */
    public function getAccordionDefaults(): array
    {
        $order = $this->getOrder();
        if ($order === null) {
            return ['details' => true];
        }

        $orderId = (int) $order->getEntityId();
        $storeId = (int) $order->getStoreId();
        $canIssue = $this->viewModel->canIssue($orderId, $storeId);
        $hasIssued = $this->viewModel->hasIssuedInvoice($orderId);

        return [
            'details' => true,
            'issue' => $canIssue,
            'documents' => $hasIssued,
            'email' => false,
            'commercial_discount' => false,
            'replacement' => false,
            'cancel' => false,
        ];
    }

    /**
     * JSON for data-mage-init accordion widget.
     */
    public function getAccordionInitJson(): string
    {
        return $this->json->serialize([
            'Secomm_EInvoiceCore/js/order/einvoice-accordion' => [
                'orderId' => $this->getAccordionOrderId(),
                'defaults' => $this->getAccordionDefaults(),
            ],
        ]);
    }
}
