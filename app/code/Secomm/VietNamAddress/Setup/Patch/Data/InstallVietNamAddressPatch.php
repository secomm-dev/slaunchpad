<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\VietNamAddress\Setup\Patch\Data;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Adapter;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Secomm\AddressDropdown\Model\Import\AddressDropdown;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes.
 */
class InstallVietNamAddressPatch implements DataPatchInterface
{
    /**
     * @var Dir
     */
    protected Dir $moduleDir;

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var Import
     */
    private Import $import;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    private State $appState;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Import $import
     * @param Dir $moduleDir
     * @param Filesystem $filesystem
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        Import                   $import,
        Dir                      $moduleDir,
        Filesystem               $filesystem,
        LoggerInterface               $logger,
        ModuleDataSetupInterface $moduleDataSetup,
        State $appState
    )
    {
        $this->appState = $appState;
        $this->import = $import;
        $this->moduleDir = $moduleDir;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Do Upgrade.
     *
     * @return void
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set → ignore
        }

        // Full path to the source file
        $vnAddressFile = 'app/code/Secomm/VietNamAddress/Files/VN_Address_2Level.csv';

        $this->import->setData([
            'entity' => AddressDropdown::ENTITY_CODE,
            'behavior' => Import::BEHAVIOR_APPEND,
            Import::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_STOP_ON_ERROR,
            Import::FIELD_NAME_ALLOWED_ERROR_COUNT => 10,
            Import::FIELD_NAME_IMG_FILE_DIR => '',
            Import::FIELD_FIELD_SEPARATOR => Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR,
            Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR,
            Import::FIELD_EMPTY_ATTRIBUTE_VALUE_CONSTANT => Import::DEFAULT_EMPTY_ATTRIBUTE_VALUE_CONSTANT,
            'import_file' => $vnAddressFile
        ]);

        try {
            $source = Adapter::findAdapterFor(
                $vnAddressFile,
                $this->filesystem->getDirectoryWrite(DirectoryList::ROOT),
                $this->import->getData(Import::FIELD_FIELD_SEPARATOR)
            );
            $this->import->validateSource($source);
            $this->import->importSource();
            $this->import->invalidateIndex();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
