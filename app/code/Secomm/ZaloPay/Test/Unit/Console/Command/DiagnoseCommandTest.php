<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Console\Command\DiagnoseCommand;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\RefundQuerySubjectBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollection;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * UNIT - zalopay:diagnose (TASK-MCHN2T feature A).
 *
 * Read-only contract: configuration report (credential PRESENCE only -
 * never values), non-zero exit on invalid/incomplete configuration,
 * mutual exclusion of the query options, and status queries that go
 * through the canonical gateway commands with a whitelisted response
 * projection (no secrets, MACs or signatures in any output).
 */
class DiagnoseCommandTest extends TestCase
{
    private const APP_TRANS_ID = '250918_1000_42';

    private const M_REFUND_ID = '260916_1000_777';

    private ConfigInterface|MockObject $config;

    private CommandPoolInterface|MockObject $commandPool;

    private RefundQueryCommand|MockObject $refundQueryCommand;

    private RefundQuerySubjectBuilder|MockObject $refundQuerySubjectBuilder;

    private RefundCollectionFactory|MockObject $refundCollectionFactory;

    private ScopeConfigInterface|MockObject $scopeConfig;

    private DiagnoseCommand $command;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->refundQueryCommand = $this->createMock(RefundQueryCommand::class);
        $this->refundQuerySubjectBuilder = $this->createMock(RefundQuerySubjectBuilder::class);
        $this->refundCollectionFactory = $this->createMock(RefundCollectionFactory::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->command = new DiagnoseCommand(
            $this->config,
            $this->commandPool,
            $this->refundQueryCommand,
            $this->refundQuerySubjectBuilder,
            $this->refundCollectionFactory,
            $this->scopeConfig
        );
    }

    /**
     * AC1/AC4: a complete LIVE configuration reports healthy, exits 0 and
     * never prints a credential value.
     */
    public function testCompleteConfigReportsHealthyAndExitsZero(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('LIVE', $output);
        $this->assertStringContainsString('Enabled: yes', $output);
        $this->assertStringContainsString('key1: set', $output);
        $this->assertStringContainsString('key2: set', $output);
        $this->assertStringContainsString('zalopay/payment/ipn', $output);
        // Correction round 1: the report must name the CANONICAL return
        // route of the current flow (zalopay/payment/returnaction, per
        // OrderAdditionalInformationDataBuilder), never a stale path.
        $this->assertStringContainsString('zalopay/payment/returnaction', $output);
        $this->assertDoesNotMatchRegularExpression(
            '#zalopay/payment/return(?!action)#',
            $output
        );
        // AC4 - presence only, never values.
        $this->assertStringNotContainsString('KEY1-SECRET-VALUE', $output);
        $this->assertStringNotContainsString('KEY2-SECRET-VALUE', $output);
    }

    /**
     * AC1: a missing required credential is a non-zero exit naming the
     * missing item.
     */
    public function testMissingRequiredCredentialExitsNonZeroAndNamesIt(): void
    {
        $this->stubCompleteConfig(['key2' => '']);
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('key2', $output);
        $this->assertStringContainsString('missing', $output);
    }

    /**
     * AC1: the disabled method is a non-zero exit.
     */
    public function testInactiveMethodExitsNonZero(): void
    {
        $this->stubCompleteConfig(['active' => 0]);
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('Enabled: no', $output);
    }

    /**
     * Correction round 1: `app_user` is provider-required (ZaloPay v2 create
     * contract; ZaloAppInfoDataBuilder always emits it) - an empty app_user
     * must fail the config health check and be named, never reported as
     * healthy.
     */
    public function testMissingAppUserExitsNonZeroAndNamesIt(): void
    {
        $this->stubCompleteConfig(['app_user' => '']);
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('app_user', $output);
        $this->assertStringContainsString('missing', $output);

        // The JSON projection carries the same verdict for automation
        // (same config stub still in effect).
        [$exitJson, $jsonOutput] = $this->runCommand(['--json' => true]);
        $this->assertSame(Cli::RETURN_FAILURE, $exitJson);
        $decoded = json_decode($jsonOutput, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($decoded['configuration_complete']);
        $this->assertContains('app_user', $decoded['missing_required_credentials']);
    }

    /**
     * AC4 (JSON): the report is one machine-readable document that still
     * never carries a credential value.
     */
    public function testJsonReportIsMachineReadableAndSecretFree(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand(['--json' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('LIVE', $decoded['mode']);
        $this->assertTrue($decoded['active']);
        $this->assertFalse($decoded['debug_logging']);
        $this->assertSame(
            ['app_id' => 'set', 'key1' => 'set', 'key2' => 'set', 'app_user' => 'set'],
            $decoded['credentials']
        );
        $this->assertArrayHasKey('endpoints', $decoded);
        // Secrets of any kind never reach the output.
        $this->assertStringNotContainsString('KEY1-SECRET-VALUE', $output);
        $this->assertStringNotContainsString('KEY2-SECRET-VALUE', $output);
        $this->assertStringNotContainsString('APP-ID-VALUE', $output);
    }

    /**
     * AC3: the query options are mutually exclusive.
     */
    public function testQueryOptionsAreMutuallyExclusive(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        [$exit, $output] = $this->runCommand(
            ['--query-payment' => self::APP_TRANS_ID, '--query-refund' => self::M_REFUND_ID]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('mutually exclusive', $output);
    }

    /**
     * AC2: --query-payment goes through the canonical `query_transaction`
     * command pool entry; only whitelisted response fields are printed.
     */
    public function testQueryPaymentUsesCanonicalCommandAndPrintsWhitelistOnly(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $queryCommand = $this->createMock(CommandInterface::class);
        $this->commandPool->expects($this->once())->method('get')
            ->with('query_transaction')->willReturn($queryCommand);
        $queryCommand->expects($this->once())->method('execute')
            ->with([AbstractResponseValidator::TRANSACTION_ID => self::APP_TRANS_ID])
            ->willReturn(
                [
                    'return_code' => 1,
                    'return_message' => 'success',
                    'zp_trans_id' => 'ZP-777',
                    // Provider responses can carry MAC/signature echoes - they
                    // must never reach the operator output.
                    'mac' => 'TOPSECRET-MAC',
                    'key2' => 'TOPSECRET-KEY2',
                ]
            );

        [$exit, $output] = $this->runCommand(['--query-payment' => self::APP_TRANS_ID]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('zp_trans_id: ZP-777', $output);
        $this->assertStringNotContainsString('TOPSECRET-MAC', $output);
        $this->assertStringNotContainsString('TOPSECRET-KEY2', $output);
    }

    /**
     * AC2 (JSON): the payment query projection is whitelisted in JSON too.
     */
    public function testQueryPaymentJsonProjectionIsWhitelisted(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $queryCommand = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('query_transaction')->willReturn($queryCommand);
        $queryCommand->method('execute')->willReturn(
            ['return_code' => 1, 'return_message' => 'success', 'zp_trans_id' => 'ZP-777', 'mac' => 'TOPSECRET-MAC']
        );

        [$exit, $output] = $this->runCommand(['--query-payment' => self::APP_TRANS_ID, '--json' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('payment', $decoded['query']);
        $this->assertSame(self::APP_TRANS_ID, $decoded['app_trans_id']);
        $this->assertSame(
            ['return_code' => 1, 'return_message' => 'success', 'zp_trans_id' => 'ZP-777'],
            $decoded['response'],
            'Only whitelisted fields may be projected (mac/key2 etc. never).'
        );
    }

    /**
     * AC2: a failing provider query is a non-zero exit without any secret.
     */
    public function testQueryPaymentFailureExitsNonZero(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $queryCommand = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->willReturn($queryCommand);
        $queryCommand->method('execute')->willThrowException(new \RuntimeException('gateway unreachable'));

        [$exit, $output] = $this->runCommand(['--query-payment' => self::APP_TRANS_ID]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('Payment query failed', $output);
    }

    /**
     * A query is refused outright when the configuration is incomplete -
     * the command pool is never touched.
     */
    public function testQueryPaymentRefusesIncompleteConfig(): void
    {
        $this->stubCompleteConfig(['key1' => '']);
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');
        $this->commandPool->expects($this->never())->method('get');

        [$exit, $output] = $this->runCommand(['--query-payment' => self::APP_TRANS_ID]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('Configuration is incomplete', $output);
    }

    /**
     * AC3: --query-refund resolves the LOCAL row by m_refund_id, rebuilds
     * the subject through the shared builder and queries through the
     * canonical RefundQueryCommand - the row itself is never mutated.
     */
    public function testQueryRefundResolvesLocalRowAndQueriesProvider(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $refund = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $refund->method('getId')->willReturn(5);

        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($refund);
        $this->refundCollectionFactory->method('create')->willReturn($collection);

        $subject = ['app_id' => '1000', 'm_refund_id' => self::M_REFUND_ID, 'timestamp' => 1800000000000, 'mac' => 'RESIGNED'];
        $this->refundQuerySubjectBuilder->expects($this->once())->method('build')
            ->with($this->identicalTo($refund))->willReturn($subject);
        $this->refundQueryCommand->expects($this->once())->method('getRefundQuery')
            ->with($subject)
            ->willReturn(
                [
                    'return_code' => 1,
                    'return_message' => 'Refund successful.',
                    'm_refund_id' => self::M_REFUND_ID,
                    'mac' => 'TOPSECRET-MAC',
                ]
            );

        [$exit, $output] = $this->runCommand(['--query-refund' => self::M_REFUND_ID]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('Refund successful.', $output);
        $this->assertStringNotContainsString('TOPSECRET-MAC', $output);
    }

    /**
     * AC3: an unknown local refund identity is a non-zero exit and never
     * reaches the provider.
     */
    public function testQueryRefundUnknownIdentityExitsNonZero(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $refund = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $refund->method('getId')->willReturn(null);

        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($refund);
        $this->refundCollectionFactory->method('create')->willReturn($collection);

        $this->refundQueryCommand->expects($this->never())->method('getRefundQuery');

        [$exit, $output] = $this->runCommand(['--query-refund' => self::M_REFUND_ID]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('No local refund row matches', $output);
    }

    /**
     * AC3: a provider transport failure is a non-zero exit.
     */
    public function testQueryRefundProviderFailureExitsNonZero(): void
    {
        $this->stubCompleteConfig();
        $this->scopeConfig->method('getValue')->willReturn('https://store.example.com/');

        $refund = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $refund->method('getId')->willReturn(5);

        $collection = $this->createMock(RefundCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($refund);
        $this->refundCollectionFactory->method('create')->willReturn($collection);

        $this->refundQuerySubjectBuilder->method('build')->willReturn(['m_refund_id' => self::M_REFUND_ID]);
        $this->refundQueryCommand->method('getRefundQuery')->willThrowException(
            new RefundTransportException(__('Zalopay: Refund status could not be confirmed.'))
        );

        [$exit, $output] = $this->runCommand(['--query-refund' => self::M_REFUND_ID]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('Refund query failed', $output);
    }

    /**
     * @param array $overrides Field/value overrides for the config stub.
     */
    private function stubCompleteConfig(array $overrides = []): void
    {
        $values = array_merge(
            [
                'sandbox_flag' => 0,
                'active' => 1,
                'debug' => 0,
                'app_id' => 'APP-ID-VALUE',
                'key1' => 'KEY1-SECRET-VALUE',
                'key2' => 'KEY2-SECRET-VALUE',
                'app_user' => 'zlp-user',
            ],
            $overrides
        );
        $this->config->method('getValue')->willReturnCallback(
            static fn (string $field): mixed => $values[$field] ?? null
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $input): array
    {
        $input = new ArrayInput($input);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        return [$this->command->run($input, $output), $output->fetch()];
    }
}
