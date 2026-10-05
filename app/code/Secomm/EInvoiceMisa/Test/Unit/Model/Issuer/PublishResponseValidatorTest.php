<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Issuer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Issuer\PublishResponseValidator;

class PublishResponseValidatorTest extends TestCase
{
    private PublishResponseValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PublishResponseValidator(new Json());
    }

    public function testPassesWhenNoNestedError(): void
    {
        $this->validator->assertPublishSuccess([
            'success' => true,
            'publishInvoiceResult' => [
                ['TransactionID' => 'tx-1', 'ErrorCode' => ''],
            ],
        ]);
        $this->addToAssertionCount(1);
    }

    public function testFailsWhenNestedErrorCodePresent(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('ErrorCode');

        $this->validator->assertPublishSuccess([
            'success' => true,
            'publishInvoiceResult' => json_encode([
                ['TransactionID' => '', 'ErrorCode' => 'InvalidInvoiceData'],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function testFailsWhenTopLevelSuccessFalse(): void
    {
        $this->expectException(LocalizedException::class);

        $this->validator->assertPublishSuccess([
            'success' => false,
            'errorCode' => 'TokenExpired',
        ]);
    }
}
