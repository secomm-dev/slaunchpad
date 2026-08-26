<?php

declare(strict_types=1);

namespace Secomm\VietQr\Model\VietQr;

/**
 * Value object holding the result of a VietQR API call.
 *
 * Contains the generated QR code string and the raw API response data.
 */
class QrResult
{
    public function __construct(
        private readonly string $qrCode,
        private readonly array $rawData = []
    ) {
    }

    /**
     * @return string
     */
    public function getQrCode(): string
    {
        return $this->qrCode;
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
