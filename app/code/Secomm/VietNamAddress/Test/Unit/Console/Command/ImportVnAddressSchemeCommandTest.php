<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Console\Command\ImportVnAddressSchemeCommand;
use Secomm\VietNamAddress\Model\Import\VnAddressSchemeImporter;
use Secomm\VietNamAddress\Model\Import\VnImportReport;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnReferenceSchemeImporter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * TASK-F9XJ5G — CLI gating for the scheme import command: --reference-only is mutually
 * exclusive with --swap/--rebuild (Case 4), scheme values must be the canonical
 * identities (legacy values rejected with a hint), and reference-only routes to the
 * reference importer (import / dry-run) instead of the runtime importer.
 */
class ImportVnAddressSchemeCommandTest extends TestCase
{
    private VnAddressSchemeImporter&MockObject $importer;
    private VnReferenceSchemeImporter&MockObject $referenceImporter;

    protected function setUp(): void
    {
        $this->importer = $this->createMock(VnAddressSchemeImporter::class);
        $this->referenceImporter = $this->createMock(VnReferenceSchemeImporter::class);
    }

    /**
     * Case 4 — --reference-only --swap must be rejected before any importer runs.
     */
    public function testRejectsReferenceOnlyCombinedWithSwap(): void
    {
        $this->importer->expects($this->never())->method('import');
        $this->referenceImporter->expects($this->never())->method('import');

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_PRE_2025, '--reference-only' => true, '--swap' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('cannot be combined with --swap', $output);
    }

    /**
     * Case 4 — --reference-only --rebuild must be rejected before any importer runs.
     */
    public function testRejectsReferenceOnlyCombinedWithRebuild(): void
    {
        $this->importer->expects($this->never())->method('import');
        $this->referenceImporter->expects($this->never())->method('import');

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_PRE_2025, '--reference-only' => true, '--rebuild' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('cannot be combined with --swap', $output);
    }

    /**
     * Case 1/5 — a reference-only run routes to the reference importer (never the runtime one).
     */
    public function testReferenceOnlyRoutesToReferenceImporter(): void
    {
        $this->importer->expects($this->never())->method('import');
        $this->importer->expects($this->never())->method('dryRun');
        $this->referenceImporter->expects($this->once())->method('import')->with(
            VnSchemes::VN_ADMIN_PRE_2025
        )->willReturn($this->referenceReport(VnSchemes::STATUS_HISTORICAL));

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_PRE_2025, '--reference-only' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('reference import complete', $output);
        $this->assertStringContainsString('runtime untouched', $output);
        $this->assertStringContainsString('registry status: ' . VnSchemes::STATUS_HISTORICAL, $output);
        $this->assertStringContainsString('11357 rows upserted', $output);
    }

    /**
     * --reference-only --dry-run: validate only, still routed to the reference importer.
     */
    public function testReferenceOnlyDryRunRoutesToReferenceDryRun(): void
    {
        $this->importer->expects($this->never())->method('dryRun');
        $report = $this->referenceReport(VnSchemes::STATUS_HISTORICAL);
        $report->dryRun = true;
        $this->referenceImporter->expects($this->once())->method('dryRun')->with(
            VnSchemes::VN_ADMIN_PRE_2025
        )->willReturn($report);

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_PRE_2025, '--reference-only' => true, '--dry-run' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('validated (dry run)', $output);
        $this->assertStringNotContainsString('rows upserted', $output);
    }

    /**
     * The current scheme may be reference-imported too: the report surfaces the kept status.
     */
    public function testReferenceOnlyOnCurrentSchemeReportsKeptStatus(): void
    {
        $this->referenceImporter->expects($this->once())->method('import')->with(
            VnSchemes::VN_ADMIN_2025
        )->willReturn($this->referenceReport(VnSchemes::STATUS_CURRENT));

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_2025, '--reference-only' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('CURRENT (kept', $output);
    }

    /**
     * Legacy pre-DEC-003 values are rejected with a pointer to the canonical identity.
     */
    public function testLegacySchemeValueRejectedWithCanonicalHint(): void
    {
        $this->importer->expects($this->never())->method('import');
        $this->referenceImporter->expects($this->never())->method('import');

        [$exit, $output] = $this->runCommand(['--scheme' => 'vn_legacy', '--reference-only' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('canonical scheme code', $output);
        $this->assertStringContainsString(VnSchemes::VN_ADMIN_PRE_2025, $output);
    }

    /**
     * A reference validation failure surfaces the error list and fails the command.
     */
    public function testReferenceValidationFailureFailsCommand(): void
    {
        $this->referenceImporter->expects($this->once())->method('import')->willThrowException(
            new VnImportValidationException(new Phrase('VN dataset failed validation.'), ['Line 3: empty code.'])
        );

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_PRE_2025, '--reference-only' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
        $this->assertStringContainsString('VN dataset failed validation.', $output);
        $this->assertStringContainsString('Line 3: empty code.', $output);
    }

    /**
     * Regression guard — the runtime path still routes to the runtime importer unchanged.
     */
    public function testRuntimeImportStillRoutesToRuntimeImporter(): void
    {
        $this->referenceImporter->expects($this->never())->method('import');
        $report = new VnImportReport();
        $report->scheme = VnSchemes::VN_ADMIN_2025;
        $this->importer->expects($this->once())->method('import')->with(
            VnSchemes::VN_ADMIN_2025,
            false,
            false
        )->willReturn($report);

        [$exit, $output] = $this->runCommand(['--scheme' => VnSchemes::VN_ADMIN_2025]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertStringContainsString('import complete', $output);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{0: int, 1: string} [exit code, rendered output]
     */
    private function runCommand(array $options): array
    {
        $command = new ImportVnAddressSchemeCommand($this->importer, $this->referenceImporter);
        $output = new BufferedOutput();
        $exit = $command->run(new ArrayInput($options, $command->getDefinition()), $output);

        return [$exit, $output->fetch()];
    }

    private function referenceReport(string $registryStatus): VnImportReport
    {
        $report = new VnImportReport();
        $report->scheme = VnSchemes::VN_ADMIN_PRE_2025;
        $report->referenceOnly = true;
        $report->regionRowsValidated = 63;
        $report->unitRowsValidated = 11294;
        $report->unitsSnapshoted = 11357;
        $report->registryStatus = $registryStatus;

        return $report;
    }
}
