<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Import\VnMappingValidator;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Test\Unit\Model\VnAddressUnitDataHelper;

/**
 * TASK-J9AVGK — mapping contract validation: orphans, same-scheme edges, bad relation
 * types, duplicates, and the informational reverse-ambiguity report.
 */
class VnMappingValidatorTest extends TestCase
{
    private VnAddressUnitProviderInterface&MockObject $unitProvider;

    private VnMappingValidator $validator;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            function (string $scheme, string $code): ?\Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface {
                $known = [
                    VnSchemes::VN_ADMIN_PRE_2025 . '|VNAP25-A',
                    VnSchemes::VN_ADMIN_PRE_2025 . '|VNAP25-B',
                    VnSchemes::VN_ADMIN_2025 . '|VNA25-C',
                    VnSchemes::VN_ADMIN_2025 . '|VNA25-D',
                ];

                return in_array($scheme . '|' . $code, $known, true)
                    ? VnAddressUnitDataHelper::unit($scheme, $code)
                    : null;
            }
        );
        $this->validator = new VnMappingValidator($this->unitProvider);
    }

    public function testAcceptsValidMappingAndReportsReverseAmbiguityAsWarningOnly(): void
    {
        ['errors' => $errors, 'warnings' => $warnings] = $this->validator->validate([
            $this->row(2, 'VNAP25-A', 'VNA25-C'),
            $this->row(3, 'VNAP25-B', 'VNA25-C'),
        ]);

        $this->assertSame([], $errors);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Reverse ambiguity', $warnings[0]);
        $this->assertStringContainsString('VNAP25-A, VNAP25-B', $warnings[0]);
    }

    public function testRejectsOrphanCodes(): void
    {
        ['errors' => $errors] = $this->validator->validate([
            $this->row(2, 'VNAP25-GHOST', 'VNA25-C'),
        ]);

        $this->assertStringContainsString('orphan source code "VNAP25-GHOST"', implode('; ', $errors));
    }

    public function testRejectsSameSchemeEdge(): void
    {
        ['errors' => $errors] = $this->validator->validate([
            $this->row(2, 'VNA25-C', 'VNA25-D', VnSchemes::VN_ADMIN_2025, VnSchemes::VN_ADMIN_2025),
        ]);

        $this->assertStringContainsString('identical', implode('; ', $errors));
    }

    public function testRejectsBadRelationType(): void
    {
        ['errors' => $errors] = $this->validator->validate([
            $this->row(2, 'VNAP25-A', 'VNA25-C', null, null, 'SUPERSEDED_BY'),
        ]);

        $this->assertStringContainsString('relation_type "SUPERSEDED_BY" invalid', implode('; ', $errors));
    }

    public function testRejectsDuplicateEdge(): void
    {
        ['errors' => $errors] = $this->validator->validate([
            $this->row(2, 'VNAP25-A', 'VNA25-C'),
            $this->row(3, 'VNAP25-A', 'VNA25-C'),
        ]);

        $this->assertStringContainsString('duplicate edge', implode('; ', $errors));
    }

    public function testRejectsUnknownScheme(): void
    {
        ['errors' => $errors] = $this->validator->validate([
            $this->row(2, 'VNAP25-A', 'VNA25-C', 'vn_legacy'),
        ]);

        $this->assertStringContainsString('unknown source_scheme "vn_legacy"', implode('; ', $errors));
    }

    /**
     * @param array<string, string|int> $overrides
     * @return array<string, string|int>
     */
    private function row(int $line, string $sourceCode, string $targetCode, ?string $sourceScheme = null, ?string $targetScheme = null, ?string $relation = null): array
    {
        return [
            'line' => $line,
            'source_scheme' => $sourceScheme ?? VnSchemes::VN_ADMIN_PRE_2025,
            'source_code' => $sourceCode,
            'target_scheme' => $targetScheme ?? VnSchemes::VN_ADMIN_2025,
            'target_code' => $targetCode,
            'relation_type' => $relation ?? 'MERGED_INTO',
        ];
    }
}
