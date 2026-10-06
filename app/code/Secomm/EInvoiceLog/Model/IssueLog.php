<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\EInvoiceLog\Model\ResourceModel\IssueLog as IssueLogResource;

/**
 * Issue attempt log entity.
 */
class IssueLog extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTION_ISSUE = 'issue';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_REFRESH_STATUS = 'refresh_status';
    public const ACTION_ADJUSTMENT = 'adjustment';
    public const ACTION_SEND_EMAIL = 'send_email';

    /**
     * Last API action code => admin label (single source for order tab + errors).
     *
     * @return array<string, \Magento\Framework\Phrase>
     */
    public static function getActionLabels(): array
    {
        return [
            self::ACTION_ISSUE => __('Issue invoice'),
            self::ACTION_CANCEL => __('Cancel invoice'),
            self::ACTION_REFRESH_STATUS => __('Refresh status'),
            self::ACTION_ADJUSTMENT => __('Adjustment invoice'),
            self::ACTION_SEND_EMAIL => __('Send email'),
        ];
    }

    /**
     * Resolve admin label for a stored last_action / action_errors key.
     */
    public static function getActionLabel(?string $action): string
    {
        if ($action === null || $action === '') {
            return (string) __('Unknown');
        }

        $labels = self::getActionLabels();

        return isset($labels[$action])
            ? (string) $labels[$action]
            : ucfirst(str_replace('_', ' ', $action));
    }

    public const REFERENCE_TYPE_ORIGINAL = 0;
    public const REFERENCE_TYPE_REPLACE = 1;
    public const REFERENCE_TYPE_ADJUSTMENT = 2;
    public const REFERENCE_TYPE_COMMERCIAL_DISCOUNT = 5;

    /**
     * MeInvoice ReferenceType value => admin label (single source for grid + order tab).
     *
     * @return array<int, \Magento\Framework\Phrase>
     */
    public static function getReferenceTypeLabels(): array
    {
        return [
            self::REFERENCE_TYPE_ORIGINAL => __('Original'),
            self::REFERENCE_TYPE_REPLACE => __('Replacement'),
            self::REFERENCE_TYPE_ADJUSTMENT => __('Adjustment'),
            self::REFERENCE_TYPE_COMMERCIAL_DISCOUNT => __('Commercial discount'),
        ];
    }

    /**
     * Resolve admin label for a stored reference_type (defaults to Original).
     */
    public static function getReferenceTypeLabel(int|string|null $referenceType): string
    {
        $type = $referenceType !== null && $referenceType !== '' ? (int) $referenceType : self::REFERENCE_TYPE_ORIGINAL;
        $labels = self::getReferenceTypeLabels();

        return isset($labels[$type]) ? (string) $labels[$type] : (string) $type;
    }

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Json $json
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly Json $json,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(IssueLogResource::class);
    }

    /**
     * Return log status code.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    /**
     * Set log status code.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Return provider error message when status is failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        $value = $this->getData('error_message');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Set provider error message.
     *
     * @param string|null $message
     * @return $this
     */
    public function setErrorMessage(?string $message): self
    {
        return $this->setData('error_message', $message);
    }

    /**
     * Return external invoice reference from the provider.
     *
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        $value = $this->getData('external_id');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Return MeInvoice transaction id.
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        $value = $this->getData('transaction_id');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Return invoice request RefID.
     *
     * @return string|null
     */
    public function getRefId(): ?string
    {
        $value = $this->getData('ref_id');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Return MeInvoice assigned invoice number (InvNo).
     */
    public function getInvNo(): ?string
    {
        $value = $this->getData('inv_no');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Last recorded API action on this log row.
     *
     * @return string|null
     */
    public function getLastAction(): ?string
    {
        $value = $this->getData('last_action');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Per-action error messages (action code => message).
     *
     * @return array<string, string>
     */
    public function getActionErrors(): array
    {
        $raw = $this->getData('action_errors');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            try {
                $decoded = $this->json->unserialize((string) $raw);
            } catch (\InvalidArgumentException) {
                return [];
            }
        }

        if (!is_array($decoded)) {
            return [];
        }

        $errors = [];
        foreach ($decoded as $action => $message) {
            if (!is_string($action) || !is_string($message) || $message === '') {
                continue;
            }
            $errors[$action] = $message;
        }

        return $errors;
    }

    /**
     * Store or clear the error for a specific action and sync error_message.
     *
     * @param string $action Action code (issue, cancel, etc.).
     * @param string|null $message Provider error text or null to clear.
     * @return $this
     */
    public function setActionError(string $action, ?string $message): self
    {
        $errors = $this->getActionErrors();
        if ($message === null || $message === '') {
            unset($errors[$action]);
        } else {
            $errors[$action] = $message;
        }

        $this->setData(
            'action_errors',
            $errors === [] ? null : $this->json->serialize($errors)
        );

        return $this->syncPrimaryErrorMessage($action, $message);
    }

    /**
     * Mark which action ran most recently.
     *
     * @param string $action Action code.
     * @param string|null $at Optional ISO datetime for last_action_at.
     * @return $this
     */
    public function setLastAction(string $action, ?string $at = null): self
    {
        $this->setData('last_action', $action);
        if ($at !== null) {
            $this->setData('last_action_at', $at);
        }

        return $this;
    }

    /**
     * Keep error_message aligned with the latest failed action for grids and legacy UI.
     */
    private function syncPrimaryErrorMessage(string $lastTouchedAction, ?string $message): self
    {
        if ($message !== null && $message !== '') {
            return $this->setData('error_message', $message)
                ->setData('last_action', $lastTouchedAction);
        }

        $errors = $this->getActionErrors();
        if ($errors === []) {
            return $this->setData('error_message', null);
        }

        $lastAction = $this->getLastAction();
        if ($lastAction !== null && isset($errors[$lastAction])) {
            return $this->setData('error_message', $errors[$lastAction]);
        }

        $first = reset($errors);

        return $this->setData('error_message', $first !== false ? (string) $first : null);
    }
}
