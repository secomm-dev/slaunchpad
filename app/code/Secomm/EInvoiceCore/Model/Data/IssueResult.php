<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Data;

use Magento\Framework\DataObject;
use Secomm\EInvoiceCore\Api\Data\IssueResultInterface;

class IssueResult extends DataObject implements IssueResultInterface
{
    /**
     * @inheritdoc
     */
    public function isSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * @inheritdoc
     */
    public function setSuccess(bool $success): IssueResultInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * @inheritdoc
     */
    public function getExternalId(): ?string
    {
        $value = $this->getData(self::EXTERNAL_ID);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setExternalId(?string $externalId): IssueResultInterface
    {
        return $this->setData(self::EXTERNAL_ID, $externalId);
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return (string) $this->getData(self::MESSAGE);
    }

    /**
     * @inheritdoc
     */
    public function setMessage(string $message): IssueResultInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritdoc
     */
    public function getRawResponse(): array
    {
        $raw = $this->getData(self::RAW_RESPONSE);
        return is_array($raw) ? $raw : [];
    }

    /**
     * @inheritdoc
     */
    public function setRawResponse(array $rawResponse): IssueResultInterface
    {
        return $this->setData(self::RAW_RESPONSE, $rawResponse);
    }
}
