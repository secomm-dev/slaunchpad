<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Helper;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Gateway\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;

/**
 * Unit test for Authorization key1/key2 decryption logic.
 *
 * Verifies that keys stored encrypted in core_config_data (Encrypted backend_model)
 * are transparently decrypted before MAC/signature use, while plain values and
 * empty values are handled safely without throwing.
 */
class AuthorizationTest extends TestCase
{
    /**
     * @var ConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var EncryptorInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $encryptor;

    /**
     * @var Authorization
     */
    private Authorization $authorization;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->authorization = new Authorization($this->config, $this->encryptor);
    }

    /**
     * Encrypted value is decrypted before use.
     *
     * @return void
     */
    public function testGetKey1DecryptsEncryptedValue(): void
    {
        $encrypted = '0:3:base64cipher';
        $plain = 'aQ9qBN6jrnRPPHXpElCSAtH71KVKSeqW';

        $this->config->method('getValue')->with(AbstractDataBuilder::KEY_1)->willReturn($encrypted);
        $this->encryptor->expects($this->once())
            ->method('decrypt')
            ->with($encrypted)
            ->willReturn($plain);

        $this->assertSame($plain, $this->authorization->getKey1());
    }

    /**
     * Plain (already-decrypted) value falls back gracefully when decrypt fails.
     *
     * @return void
     */
    public function testGetKey2ReturnsOriginalValueWhenDecryptFails(): void
    {
        $plain = 'oFYAJw6hHoGcAP3n9GhG4lLEPNuH5RlI';

        $this->config->method('getValue')->with(AbstractDataBuilder::KEY_2)->willReturn($plain);
        $this->encryptor->expects($this->once())
            ->method('decrypt')
            ->with($plain)
            ->willThrowException(new \Exception('Not encrypted'));

        // Must not throw — returns the plain value as-is so signature still works.
        $this->assertSame($plain, $this->authorization->getKey2());
    }

    /**
     * Empty value is returned without attempting decryption.
     *
     * @return void
     */
    public function testGetKey1SkipsDecryptForEmptyValue(): void
    {
        $this->config->method('getValue')->with(AbstractDataBuilder::KEY_1)->willReturn('');
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertSame('', $this->authorization->getKey1());
    }
}
