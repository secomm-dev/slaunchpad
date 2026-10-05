<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Issuer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Validates MeInvoice publish response per tailieuAPI §1.4 / §7.
 */
class PublishResponseValidator
{
    public function __construct(
        private readonly Json $json
    ) {
    }

    /**
     * Fail when top-level success is false or publishInvoiceResult items contain ErrorCode.
     *
     * @param array<string, mixed> $response
     * @throws LocalizedException
     */
    public function assertPublishSuccess(array $response): void
    {
        if (!(bool) ($response['success'] ?? false)) {
            throw new LocalizedException(__($this->formatTopLevelError($response)));
        }

        $nestedError = $this->findNestedPublishError($response);
        if ($nestedError !== null) {
            throw new LocalizedException(__($nestedError));
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    public function findNestedPublishError(array $response): ?string
    {
        foreach ($this->normalizePublishResults($response) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $errorCode = (string) ($item['ErrorCode'] ?? '');
            if ($errorCode !== '' && $errorCode !== '0') {
                $description = (string) ($item['DescriptionErrorCode'] ?? $item['ErrorMessage'] ?? '');
                if ($description !== '') {
                    return (string) __('MeInvoice publish item %1 failed: %2 (%3)', $index + 1, $description, $errorCode);
                }

                return (string) __('MeInvoice publish item %1 failed with ErrorCode: %2', $index + 1, $errorCode);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, mixed>
     */
    private function normalizePublishResults(array $response): array
    {
        $raw = $response['publishInvoiceResult'] ?? [];
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = $this->json->unserialize($raw);
            } catch (\InvalidArgumentException) {
                return [];
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [];
        }

        if ($raw !== [] && !isset($raw[0]) && (isset($raw['ErrorCode']) || isset($raw['TransactionID']))) {
            return [$raw];
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function formatTopLevelError(array $response): string
    {
        if (isset($response['descriptionErrorCode']) && is_string($response['descriptionErrorCode'])) {
            return $response['descriptionErrorCode'];
        }
        if (isset($response['errorCode']) && is_string($response['errorCode']) && $response['errorCode'] !== '') {
            return $response['errorCode'];
        }
        if (isset($response['errors']) && is_array($response['errors']) && $response['errors'] !== []) {
            return implode(', ', array_map(static fn ($value): string => (string) $value, $response['errors']));
        }

        return (string) __('MeInvoice API returned success=false.');
    }
}
