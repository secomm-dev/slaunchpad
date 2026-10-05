<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Certificate;

/**
 * HSM digital certificate returned by MeInvoice get-certificates API.
 */
class HsmCertificate
{
    public function __construct(
        private readonly string $certificateSn,
        private readonly string $userName,
        private readonly string $authOrganizeName,
        private readonly string $effectiveTime,
        private readonly string $expirationTime
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromApiRow(array $row): ?self
    {
        $sn = (string) ($row['CertificateSN'] ?? '');
        if ($sn === '') {
            return null;
        }

        return new self(
            $sn,
            (string) ($row['UserName'] ?? ''),
            (string) ($row['AuthOrganizeName'] ?? ''),
            (string) ($row['EffectiveTime'] ?? ''),
            (string) ($row['ExpirationTime'] ?? '')
        );
    }

    public function getCertificateSn(): string
    {
        return $this->certificateSn;
    }

    public function getLabel(): string
    {
        $parts = array_filter([
            $this->authOrganizeName,
            $this->userName,
            $this->certificateSn,
        ]);

        return implode(' — ', $parts);
    }
}
