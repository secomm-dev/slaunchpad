<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Setup\Patch\Data;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\VnReferenceSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdmin2025SchemePatch;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdminPre2025ReferencePatch;

/**
 * TASK-F9XJ5G — the setup:upgrade patch delegates to the reference-only importer for
 * VN_ADMIN_PRE_2025 (never the runtime importer), depends on the 2025 bootstrap, and
 * lets importer failures propagate so a broken historical dataset fails setup loudly.
 */
class ImportVnAdminPre2025ReferencePatchTest extends TestCase
{
    private VnReferenceSchemeImporter&MockObject $referenceImporter;

    private ImportVnAdminPre2025ReferencePatch $patch;

    protected function setUp(): void
    {
        $this->referenceImporter = $this->createMock(VnReferenceSchemeImporter::class);
        $this->patch = new ImportVnAdminPre2025ReferencePatch($this->referenceImporter);
    }

    public function testApplyImportsPre2025ThroughReferenceImporter(): void
    {
        $this->referenceImporter->expects($this->once())->method('import')->with(
            VnSchemes::VN_ADMIN_PRE_2025
        );

        $this->patch->apply();
    }

    public function testPatchCannotTouchTheRuntimeImporter(): void
    {
        // Structural guard: the constructor must not accept the runtime scheme importer —
        // the reference patch has no code path to hierarchy import, config or membership.
        $parameters = (new \ReflectionClass($this->patch))->getConstructor()?->getParameters() ?? [];
        $types = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getType()->getName(),
            $parameters
        );
        $this->assertSame([VnReferenceSchemeImporter::class], $types);
    }

    public function testDependsOnThe2025BootstrapPatch(): void
    {
        $this->assertSame([ImportVnAdmin2025SchemePatch::class], $this->patch::getDependencies());
        $this->assertSame([], $this->patch->getAliases());
    }

    public function testValidationFailurePropagatesToFailSetupLoudly(): void
    {
        $this->referenceImporter->method('import')->willThrowException(
            new \Secomm\VietNamAddress\Model\Import\VnImportValidationException(
                new \Magento\Framework\Phrase('VN dataset failed validation.'),
                ['Line 3: empty code.']
            )
        );

        $this->expectException(\Secomm\VietNamAddress\Model\Import\VnImportValidationException::class);
        $this->patch->apply();
    }
}
