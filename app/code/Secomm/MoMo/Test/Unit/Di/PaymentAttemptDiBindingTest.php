<?php
/**
 * DI-binding regression for the MoMo payment-attempt path (MOMO-01-HF1).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Di;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttemptFactory;
use Secomm\MoMo\Model\PaymentAttemptRepository;
use Secomm\MoMo\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Secomm\MoMo\Model\ResourceModel\PaymentAttemptResource;
use Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard;
use Secomm\MoMo\Service\OrderPlacementAuthorization;

/**
 * Regression for issue #13 (MOMO-01-HF1): MOMO-01 shipped the global
 * QuoteManagement placeOrder guard without an ObjectManager preference for
 * PaymentAttemptRepositoryInterface, so ANY placeOrder call — including
 * non-MoMo methods like ZaloPay — fatals with "Cannot instantiate interface"
 * at plugin instantiation. `setup:di:compile` does not catch a missing
 * constructor preference (runtime-only resolution), so the binding and the
 * real construction path are pinned here deterministically.
 */
class PaymentAttemptDiBindingTest extends TestCase
{
    /**
     * Module di.xml path, resolved from this test's directory.
     */
    private const MODULE_ETC_DI_XML = __DIR__ . '/../../../etc/di.xml';

    /**
     * The module di.xml must bind PaymentAttemptRepositoryInterface to the
     * concrete repository — the exact binding whose absence blocked every
     * non-MoMo order.
     *
     * @return void
     */
    public function testRepositoryInterfaceIsBoundToConcreteRepository(): void
    {
        $preferences = [];
        $xml = simplexml_load_file(self::MODULE_ETC_DI_XML);
        foreach ($xml->xpath('/config/preference') ?: [] as $preference) {
            $preferences[(string)$preference['for']] = (string)$preference['type'];
        }

        self::assertArrayHasKey(
            PaymentAttemptRepositoryInterface::class,
            $preferences,
            'Missing ObjectManager preference for PaymentAttemptRepositoryInterface (issue #13 regression).'
        );

        $type = $preferences[PaymentAttemptRepositoryInterface::class];
        self::assertSame(PaymentAttemptRepository::class, $type);
        self::assertTrue(
            is_a($type, PaymentAttemptRepositoryInterface::class, true),
            'Preference target must implement PaymentAttemptRepositoryInterface.'
        );
    }

    /**
     * The real repository must be constructible from its full dependency
     * graph (module-owned classes carry no hidden unresolvable dependency).
     *
     * @return void
     */
    public function testRepositoryDependencyGraphIsConstructible(): void
    {
        $repository = $this->createRepository();

        self::assertInstanceOf(PaymentAttemptRepositoryInterface::class, $repository);
    }

    /**
     * The guard builds against the REAL repository graph (the ObjectManager
     * wiring shape) and stays a no-op for a non-MoMo quote — the ZaloPay
     * place-order path must remain untouched Magento behaviour.
     *
     * @return void
     */
    public function testGuardConstructsWithRealRepositoryAndIsNoOpForNonMoMo(): void
    {
        $quote = $this->quote('checkmo');
        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $quoteRepository->method('get')->willReturn($quote);

        $guard = new CartManagementPlaceOrderGuard(
            $quoteRepository,
            $this->createRepository(),
            $this->method(),
            new OrderPlacementAuthorization(),
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );

        $this->assertNull($guard->beforePlaceOrder(
            $this->createMock(CartManagementInterface::class),
            42
        ));
    }

    /**
     * A real PaymentAttemptRepository (mocks only at I/O boundaries).
     *
     * @return PaymentAttemptRepository
     */
    private function createRepository(): PaymentAttemptRepository
    {
        return new PaymentAttemptRepository(
            new PaymentAttemptFactory($this->createMock(ObjectManagerInterface::class)),
            new PaymentAttemptCollectionFactory($this->createMock(ObjectManagerInterface::class)),
            $this->createMock(ResourceConnection::class),
            $this->createMock(DateTime::class),
            $this->createMock(PaymentAttemptResource::class)
        );
    }

    /**
     * MoMo facade double (method code as configured).
     *
     * @return MethodInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function method(): MethodInterface
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('momo_payment');

        return $method;
    }

    /**
     * Quote mock with the given payment method.
     *
     * @param string $methodCode
     * @return Quote&\PHPUnit\Framework\MockObject\MockObject
     */
    private function quote(string $methodCode): Quote
    {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getMethod')->willReturn($methodCode);
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        return $quote;
    }
}
