<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Setup\Patch\Data;

use Magento\Framework\Component\ComponentRegistrarInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdmin2025SchemePatch;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdminPre2025ReferencePatch;
use Secomm\VietNamAddress\Setup\Patch\Data\ImportVnAdminPre2025To2025MappingPatch;

/**
 * TASK-NDSZ7V — the mapping seed patch delegates to the existing VnMappingImporter with the
 * reviewed canonical baseline file from the module's Files/ directory, depends on the PRE_2025
 * reference patch (orphan validation needs both unit datasets), and fails setup loudly on a
 * broken dataset.
 */
class ImportVnAdminPre2025To2025MappingPatchTest extends TestCase
{
    private VnMappingImporter&MockObject $mappingImporter;
    private ComponentRegistrarInterface&MockObject $componentRegistrar;

    private ImportVnAdminPre2025To2025MappingPatch $patch;

    protected function setUp(): void
    {
        $this->mappingImporter = $this->createMock(VnMappingImporter::class);
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->componentRegistrar->method('getPath')->willReturn('/magento/app/code/Secomm/VietNamAddress');
        $this->patch = new ImportVnAdminPre2025To2025MappingPatch($this->mappingImporter, $this->componentRegistrar);
    }

    public function testApplyImportsTheReviewedBaselineThroughTheExistingImporter(): void
    {
        $this->componentRegistrar->expects($this->once())->method('getPath')->with(
            'module',
            'Secomm_VietNamAddress'
        );
        $this->mappingImporter->expects($this->once())->method('import')->with(
            '/magento/app/code/Secomm/VietNamAddress/Files/VN_ADMIN_PRE_2025_TO_2025_mapping.csv',
            false
        );

        $this->patch->apply();
    }

    public function testDependsOnTheReferencePatchChain(): void
    {
        $this->assertSame([ImportVnAdminPre2025ReferencePatch::class], $this->patch::getDependencies());
        // Transitive chain sanity: reference patch already depends on the 2025 bootstrap.
        $this->assertSame([ImportVnAdmin2025SchemePatch::class], ImportVnAdminPre2025ReferencePatch::getDependencies());
        $this->assertSame([], $this->patch->getAliases());
    }

    public function testValidationFailurePropagatesToFailSetupLoudly(): void
    {
        $this->mappingImporter->method('import')->willThrowException(
            new VnImportValidationException(
                new \Magento\Framework\Phrase('Mapping validation failed with 1 error(s); nothing was written.'),
                ['Line 7: orphan target code "VNA25-GHOST" not in unit table for VN_ADMIN_2025.']
            )
        );

        $this->expectException(VnImportValidationException::class);
        $this->patch->apply();
    }
}
