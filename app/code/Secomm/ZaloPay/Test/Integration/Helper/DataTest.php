<?php
/**
 * Copyright (c) 2023. Secomm All rights reserved
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Integration\Helper;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Helper\Data;

/**
 * @magentoDbIsolation enabled
 */
class DataTest extends TestCase
{

    /**
     * @var ConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configMock;

    /**
     * @var Data
     */
    private $data;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        // Create a mock object for ConfigInterface using PHPUnit's getMockForAbstractClass
        $this->configMock = $this->getMockForAbstractClass(ConfigInterface::class);

        // Obtain the Object Manager and create an instance of the Data class
        $objectManager = Bootstrap::getObjectManager();
        $this->data = $objectManager->create(Data::class);
    }

    /**
     * Test the removeSpecialChars method with various test cases.
     *
     * @dataProvider specialCharsDataProvider
     * @param string|null $input
     * @param string $expectedResult
     */
    public function testRemoveSpecialChars(?string $input, string $expectedResult): void
    {
        $actualResult = $this->data->removeSpecialChars($input);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Data provider for testRemoveSpecialChars method.
     *
     * @return array
     */
    public function specialCharsDataProvider(): array
    {
        return [
            ['test123', 'test123'],           // Alphanumeric string
            ['test 123', 'test 123'],         // String with space
            ['!@#$%^&(test 123', 'test 123'], // String with special characters at the beginning
            ['!@#$%^&(test 123&**())_<MNB', 'test 123MNB'], // String with various special characters
            ['', ''],                         // Empty string
            [null, ''],                       // Null input
        ];
    }
}
