<?php
/**
 * Unit test for the shared purchase-query resultCode classifier (MOMO-04).
 *
 * @author    Secomm Team
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Service\PurchaseQueryClassifier;
use Secomm\MoMo\Service\PurchaseQueryOutcome;

/**
 * Pins the fail-safe classification contract shared by the browser Return
 * path (ReturnProcessor) and the proactive recovery worker (PaymentRecovery):
 * only provider-documented allowlists may conclude, and unknown defaults to
 * AMBIGUOUS — never FAILED.
 */
class PurchaseQueryClassifierTest extends TestCase
{
    private PurchaseQueryClassifier $classifier;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->classifier = new PurchaseQueryClassifier();
    }

    /**
     * Every documented PAID / PENDING / FINAL-FAILURE code classifies into
     * its exact category, carrying the parsed code.
     *
     * @dataProvider documentedOutcomeProvider
     * @param int $resultCode
     * @param string $expectedCategory
     * @return void
     */
    public function testDocumentedCodesClassifyExactly(int $resultCode, string $expectedCategory): void
    {
        $outcome = $this->classifier->classify($resultCode);

        $this->assertSame($expectedCategory, $outcome->getCategory());
        $this->assertSame($resultCode, $outcome->getResultCode());
        $this->assertNull($outcome->getReason());
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function documentedOutcomeProvider(): array
    {
        $finalFailures = [
            98, 99,
            1001, 1002, 1003, 1004, 1005, 1006, 1007, 1017, 1026,
            2019, 4001, 4002, 4100,
        ];
        $cases = [];
        foreach ([0, 9000] as $code) {
            $cases['paid code ' . $code] = [$code, PurchaseQueryOutcome::PAID];
        }
        foreach ([1000, 7000, 7002] as $code) {
            $cases['pending code ' . $code] = [$code, PurchaseQueryOutcome::PENDING];
        }
        foreach ($finalFailures as $code) {
            $cases['final failure code ' . $code] = [$code, PurchaseQueryOutcome::FINAL_FAILURE];
        }

        return $cases;
    }

    /**
     * Every documented request/system code is AMBIGUOUS with the
     * request-system reason — never a failure.
     *
     * @dataProvider requestSystemCodeProvider
     * @param int $resultCode
     * @return void
     */
    public function testRequestSystemCodesAreAmbiguous(int $resultCode): void
    {
        $outcome = $this->classifier->classify($resultCode);

        $this->assertSame(PurchaseQueryOutcome::AMBIGUOUS, $outcome->getCategory());
        $this->assertSame(PurchaseQueryOutcome::REASON_REQUEST_SYSTEM, $outcome->getReason());
        $this->assertSame($resultCode, $outcome->getResultCode());
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function requestSystemCodeProvider(): array
    {
        $cases = [];
        foreach ([10, 11, 12, 13, 20, 21, 22, 40, 41, 42, 43, 45, 47] as $code) {
            $cases['request/system code ' . $code] = [$code];
        }

        return $cases;
    }

    /**
     * AC5/AC6: a missing or unparseable resultCode is ambiguous with NO
     * parsed code — it can never drive a payment-state decision.
     *
     * @dataProvider unparseableProvider
     * @param mixed $rawResultCode
     * @return void
     */
    public function testMissingOrUnparseableResultCodeIsAmbiguous(mixed $rawResultCode): void
    {
        $outcome = $this->classifier->classify($rawResultCode);

        $this->assertSame(PurchaseQueryOutcome::AMBIGUOUS, $outcome->getCategory());
        $this->assertSame(PurchaseQueryOutcome::REASON_UNPARSEABLE, $outcome->getReason());
        $this->assertNull($outcome->getResultCode());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unparseableProvider(): array
    {
        return [
            'missing (null)' => [null],
            'empty string' => [''],
            'whitespace only' => ['   '],
            'alpha garbage' => ['abc'],
            'mixed digits and letters' => ['12abc'],
            'trailing punctuation' => ['1001,'],
            'array payload' => [[1001]],
            'bool false payload' => [false],
        ];
    }

    /**
     * AC7: any parseable code outside the documented allowlists defaults to
     * AMBIGUOUS/unmapped — NEVER FAILED (fail-safe money state). String
     * numerics classify identically to ints.
     *
     * @return void
     */
    public function testUnknownCodesDefaultToAmbiguousUnmapped(): void
    {
        foreach ([424242, -1, 7, 700, 9001, -1001] as $unmapped) {
            $outcome = $this->classifier->classify($unmapped);

            $this->assertSame(PurchaseQueryOutcome::AMBIGUOUS, $outcome->getCategory());
            $this->assertSame(PurchaseQueryOutcome::REASON_UNMAPPED, $outcome->getReason());
            $this->assertSame($unmapped, $outcome->getResultCode());
        }
    }

    /**
     * String numerics (a defensive-JSON shape) parse identically to ints.
     *
     * @return void
     */
    public function testStringNumericsClassifyLikeInts(): void
    {
        $this->assertSame(PurchaseQueryOutcome::PAID, $this->classifier->classify('9000')->getCategory());
        $this->assertSame(PurchaseQueryOutcome::PENDING, $this->classifier->classify('7002')->getCategory());
        $this->assertSame(
            PurchaseQueryOutcome::FINAL_FAILURE,
            $this->classifier->classify('1001')->getCategory()
        );
        $this->assertSame(
            PurchaseQueryOutcome::REASON_REQUEST_SYSTEM,
            $this->classifier->classify(' 42 ')->getReason()
        );
    }
}
