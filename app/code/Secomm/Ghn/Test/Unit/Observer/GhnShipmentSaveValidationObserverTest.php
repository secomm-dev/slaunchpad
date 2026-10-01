<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateParcelValidator;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\Ghn\Model\Shipment\GhnPhysicalParcelInterpreter;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;
use Secomm\Ghn\Observer\GhnShipmentSaveValidationObserver;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;

/**
 * TASK-W5BW4F layer 1 — a FRESH shipment save with packages that would deterministically fail
 * the GHN create is blocked with the admin-facing message; re-saves of an existing shipment
 * and non-GHN carriers pass through untouched. TASK-S52DGA: the generic offline intent stands
 * the gate down, and an offline-eligible block stashes the eligibility + points at the offline
 * path (only the frozen token list does).
 */
class GhnShipmentSaveValidationObserverTest extends TestCase
{
    private HttpRequest&MockObject $request;

    private LoggerInterface&MockObject $psrLogger;

    private FulfillmentModeResolver&MockObject $fulfillmentModeResolver;

    private OfflineEligibilitySession&MockObject $eligibilitySession;

    private GhnCreateParcelValidator $validator;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->fulfillmentModeResolver = $this->createMock(FulfillmentModeResolver::class);
        $this->eligibilitySession = $this->createMock(OfflineEligibilitySession::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('kgs');
        $limitConfig = $this->createMock(Config::class);
        $limitConfig->method('getMaxLengthCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $limitConfig->method('getMaxWidthCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $limitConfig->method('getMaxHeightCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $this->validator = new GhnCreateParcelValidator(
            new GhnPhysicalParcelInterpreter(new GhnPhysicalLimit($limitConfig)),
            new StoreWeightConverter($scopeConfig)
        );
    }

    private function observer(?GhnCreateParcelValidator $validator = null): GhnShipmentSaveValidationObserver
    {
        return new GhnShipmentSaveValidationObserver(
            $validator ?? $this->validator,
            $this->request,
            new GhnLogger($this->psrLogger),
            $this->fulfillmentModeResolver,
            $this->eligibilitySession
        );
    }

    public function testFreshGhnSaveWithOverLimitPackageIsBlocked(): void
    {
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);

        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('above the 200 cm length limit');

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
    }

    public function testFreshGhnSaveWithEmptyRowIsBlocked(): void
    {
        $this->givenPostedRows([['weight' => 1.5, 'length' => 30, 'width' => 0, 'height' => 10]]);

        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('package #1 has an empty weight or dimension');

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
    }

    public function testFreshGhnSaveWithoutPostedRowsIsBlockedWithTheCanonicalMessage(): void
    {
        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage(
            'GHN create: no confirmed package weight/dimensions on the shipment.'
        );

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
    }

    public function testFreshGhnSaveWithValidPackagesPasses(): void
    {
        $this->givenPostedRows([['weight' => 48.6, 'length' => 60, 'width' => 50, 'height' => 40]]);

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testExistingShipmentResaveIsNeverBlocked(): void
    {
        // A re-save (comment/track/snapshot re-save) carries no gate: the over-limit rows are
        // ignored because the create attempt for an existing shipment is post-commit + loud.
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 42)]));
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testNonGhnCarrierIsNeverBlocked(): void
    {
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('flatrate_flatrate', 21, 0)]));
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testMissingShipmentObjectIsIgnored(): void
    {
        $this->observer()->execute(new Observer([]));
        $this->addToAssertionCount(1); // no exception = pass
    }

    // ---------- TASK-S52DGA (offline escape) ----------

    public function testOfflineIntentSkipsValidation(): void
    {
        // The offline save records facts without submitting to GHN — constraint-violating
        // packages are expected and must NOT block the save.
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);
        $this->fulfillmentModeResolver->method('isOfflineIntent')->willReturn(true);
        $this->eligibilitySession->expects($this->never())->method('stash');

        $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testEligibleBlockStashesEligibilityAndPointsAtTheOfflinePath(): void
    {
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);
        $this->eligibilitySession->expects($this->once())->method('stash')->with(
            21,
            GhnCreateValidationException::REASON_INVALID_PARCEL,
            $this->callback(fn (string $message): bool => str_contains($message, 'above the 200 cm length limit'))
        );

        try {
            $this->observer()->execute(new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)]));
            self::fail('Expected the gate to block the save.');
        } catch (GhnCreateValidationException $exception) {
            self::assertSame(
                GhnCreateValidationException::REASON_INVALID_PARCEL,
                $exception->getReasonToken()
            );
            self::assertStringContainsString(
                'Use "Create Offline Shipment"',
                (string) $exception->getMessage()
            );
            self::assertStringContainsString('above the 200 cm length limit', (string) $exception->getMessage());
        }
    }

    public function testNonEligibleTokenNeverStashesAndKeepsTheOriginalMessage(): void
    {
        $this->givenPostedRows([['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300]]);
        $original = new GhnCreateValidationException('NOT_IN_THE_P1_LIST', __('Some future deterministic reason.'));
        $validator = $this->createMock(GhnCreateParcelValidator::class);
        $validator->method('fromPostedRows')->willThrowException($original);
        $this->eligibilitySession->expects($this->never())->method('stash');

        try {
            $this->observer($validator)->execute(
                new Observer(['shipment' => $this->shipment('secomm_ghn_secomm_ghn', 21, 0)])
            );
            self::fail('Expected the gate to block the save.');
        } catch (GhnCreateValidationException $exception) {
            self::assertSame($original, $exception); // rethrown untouched — no hint, no new wrapper
        }
    }

    // ---------- helpers ----------

    private function givenPostedRows(array $rows): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getParam')->with('shipment')->willReturn(['physical_packages' => $rows]);
    }

    private function shipment(string $shippingMethod, int $orderId, int $entityId): Shipment&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getEntityId')->willReturn($orderId);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn($entityId);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getStoreId')->willReturn(1);

        return $shipment;
    }
}
