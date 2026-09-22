<?php
/**
 * DI-construction isolation contract for the QuoteManagement placeOrder
 * guard (issue #20 / BUG-QFR2AY).
 *
 * The guard plugin is registered GLOBALly on QuoteManagement::placeOrder(),
 * so the ObjectManager builds it for EVERY payment method's placement. Its
 * di.xml wiring is pinned here: method discrimination must be a scalar
 * method code (no facade constructed to discriminate), and the attempt
 * repository must be wired as a Proxy so the real repository — and its
 * ResourceConnection graph — is constructed only on the MoMo grant
 * validation path, never for another payment method's placement.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Di;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttemptRepository;
use Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard;

/**
 * Pins the construction-isolation wiring of
 * Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard in etc/di.xml.
 */
class PlaceOrderGuardDiIsolationTest extends TestCase
{
    /**
     * Module di.xml path, resolved from this test's repo-relative location.
     */
    private const MODULE_ETC_DI_XML = __DIR__ . '/../../../etc/di.xml';

    /**
     * The guard's DI arguments must be construction-isolated: method
     * discrimination uses the scalar code (no facade argument), and the
     * attempt repository is wired as a Proxy. Any object argument other
     * than the proxy would re-create the cross-payment construction
     * coupling issue #20 fixes.
     *
     * @return void
     */
    public function testGuardDiArgumentsAreConstructionIsolated(): void
    {
        $arguments = $this->guardArguments();

        self::assertArrayNotHasKey(
            'method',
            $arguments,
            'The guard must not construct the MoMoFacade Adapter just to compare a method code.'
        );
        self::assertArrayHasKey('methodCode', $arguments);
        self::assertSame('string', $arguments['methodCode']['type']);
        self::assertSame('momo_payment', $arguments['methodCode']['value']);
        self::assertArrayHasKey('attemptRepository', $arguments);
        self::assertSame('object', $arguments['attemptRepository']['type']);
        self::assertSame(PaymentAttemptRepository::class . '\Proxy', $arguments['attemptRepository']['value']);
    }

    /**
     * The scalar method code must equal the MoMoFacade code — the string is
     * the guard's stand-in for what the facade's getCode() would return, so
     * both sources must agree.
     *
     * @return void
     */
    public function testMethodCodeMatchesFacadeCode(): void
    {
        $arguments = $this->guardArguments();
        $facades = $this->facadeArguments();

        self::assertNotEmpty($facades, 'MoMoFacade virtualType must exist (guard methodCode cross-check).');
        self::assertSame('momo_payment', $facades['code']['value']);
        self::assertSame(
            $arguments['methodCode']['value'],
            $facades['code']['value'],
            'Guard methodCode scalar must equal the MoMoFacade code argument.'
        );
    }

    /**
     * The Proxy subject (the concrete repository) implements
     * PaymentAttemptRepositoryInterface — the generated proxy extends it,
     * so the guard's interface type-hint stays satisfied once
     * setup:di:compile has generated the proxy class.
     *
     * @return void
     */
    public function testProxySubjectSatisfiesGuardTypeHint(): void
    {
        self::assertTrue(
            is_a(PaymentAttemptRepository::class, PaymentAttemptRepositoryInterface::class, true),
            'Proxy subject must implement PaymentAttemptRepositoryInterface.'
        );
    }

    /**
     * Guard <type> arguments from the module di.xml: name => [type, value].
     *
     * @return array<string, array{type: string, value: string}>
     */
    private function guardArguments(): array
    {
        return $this->typeArguments(CartManagementPlaceOrderGuard::class);
    }

    /**
     * MoMoFacade virtualType arguments: name => [type, value].
     *
     * @return array<string, array{type: string, value: string}>
     */
    private function facadeArguments(): array
    {
        // The facade is declared as <virtualType>, not <type> — same argument shape.
        return $this->typeArguments('MoMoFacade', 'virtualType');
    }

    /**
     * Extract <$elementName name="$typeName"><arguments>…</arguments> as
     * name => [type, value]. The xsi:type attribute is read through the
     * document's declared xsi prefix; values are trimmed.
     *
     * @param string $typeName
     * @param string $elementName config element to search (type|virtualType)
     * @return array<string, array{type: string, value: string}>
     */
    private function typeArguments(string $typeName, string $elementName = 'type'): array
    {
        $xml = simplexml_load_file(self::MODULE_ETC_DI_XML);
        self::assertNotFalse($xml, 'Module di.xml must parse.');

        $types = $xml->xpath(sprintf('/config/%s[@name="%s"]', $elementName, $typeName)) ?: [];
        self::assertNotEmpty($types, sprintf('di.xml must configure %s %s.', $elementName, $typeName));

        $arguments = [];
        foreach ($types[0]->arguments->argument ?? [] as $argument) {
            $xsiType = (string)($argument->attributes('xsi', true)['type'] ?? '');
            $arguments[(string)$argument['name']] = [
                'type' => $xsiType,
                'value' => trim((string)$argument),
            ];
        }

        return $arguments;
    }
}
