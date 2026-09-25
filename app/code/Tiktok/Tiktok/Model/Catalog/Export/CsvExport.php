<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Catalog\Export\Mapper\CsvProductAttributeMapperFactory;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\CsvProductDataProviderFactory;
use Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CsvExport
{
    /**
     * File handler
     *
     * @var resource
     */
    protected $fileHandle;

    /**
     * @var string
     */
    protected string $writePath;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    protected TiktokLogger $logger;

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager
     */
    private SyncFlagManager $syncFlagManager;

    /**
     * @var \Tiktok\Tiktok\Model\ScopeManagerBuilder
     */
    private ScopeManagerBuilder $scopeManagerBuilder;

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\Mapper\CsvProductAttributeMapperFactory
     */
    private CsvProductAttributeMapperFactory $attributeMapperFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\Provider\CsvProductDataProviderFactory
     */
    private CsvProductDataProviderFactory $dataProviderFactory;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Mapper\CsvProductAttributeMapperFactory $attributeMapperFactory
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Sync\SyncFlagManager $syncFlagManager
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\CsvProductDataProviderFactory $dataProviderFactory
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     */
    public function __construct(
        Filesystem $filesystem,
        TiktokLogger $logger,
        CsvProductAttributeMapperFactory $attributeMapperFactory,
        SyncFlagManager $syncFlagManager,
        CsvProductDataProviderFactory $dataProviderFactory,
        ScopeManagerBuilder $scopeManagerBuilder,
        private readonly File $fileDriver
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->attributeMapperFactory = $attributeMapperFactory;
        $this->syncFlagManager = $syncFlagManager;
        $this->dataProviderFactory = $dataProviderFactory;
        $this->scopeManagerBuilder = $scopeManagerBuilder;
    }

    /**
     * Exports the product data page by page into the CSV file.
     *
     * @param int $websiteId
     * @param string $fileName
     *
     * @return bool
     * @throws LocalizedException
     */
    public function export(int $websiteId, string $fileName = 'tiktok.csv'): bool
    {
        $scopeManager = $this->scopeManagerBuilder->create($websiteId);
        $attributeMapper = $this->attributeMapperFactory->create(['scopeManager' => $scopeManager]);
        $attributeToSelect = $attributeMapper->getAttributesToSelect();
        $dataProvider = $this->dataProviderFactory->create(
            [
                'attributesToSelect' => $attributeToSelect,
                'websiteId' => $websiteId,
                'pageSize' => 2000
            ]
        );
        $this->initializeWriter($attributeMapper->getMappedHeaders(), $websiteId . '_' . $fileName);
        try {
            // Fetch product data page by page using the attribute mapper
            while ($products = $dataProvider->getNextBatch()) {
                $productRows = $attributeMapper->mapPage($products);
                foreach ($productRows as $dataRow) {
                    if ($dataRow) {
                        $this->fileDriver->filePutCsv($this->fileHandle, $dataRow);
                    }
                }
            }
            $this->logger->info('Product export complete');
            $this->syncFlagManager->updateLastSyncTime($websiteId);
            $skippedProducts = $attributeMapper->getSkippedProducts();
            if ($skippedProducts > 0) {
                $this->logger->info("Skipped $skippedProducts products due to missing required data");
            }
            return true;
        } catch (Exception $e) {
            $this->logger->critical('Error during export: ' . $e->getMessage());
            throw new LocalizedException(__('An error occurred during export: ' . $e->getMessage()));
        } finally {
            if ($this->fileHandle) {
                $this->fileDriver->fileClose($this->fileHandle);
            }
        }
    }

    /**
     * Initializes the file writer and creates the CSV file with headers.
     *
     * @param mixed $attributes
     * @param string $fileName
     *
     * @throws LocalizedException
     */
    private function initializeWriter(mixed $attributes, string $fileName = 'tiktok.csv'): void
    {
        try {
            $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);

            // Ensure the export directory exists
            $this->createExportDirectory($varDirectory);

            $filePath = $varDirectory->getAbsolutePath('export/' . $fileName);
            $this->logger->info('Beginning product export ' . $filePath);

            // Open the file for writing
            $this->fileHandle = $this->fileDriver->fileOpen($filePath, 'w');
            if (!$this->fileHandle) {
                throw new LocalizedException(__('Cannot open file for writing: ' . $filePath));
            }

            // Write the headers to the CSV file
            $headers = array_values($attributes);
            $this->fileDriver->filePutCsv($this->fileHandle, $headers);
            $this->writePath = $filePath;
        } catch (Exception $e) {
            $this->logger->critical('Error initializing writer: ' . $e->getMessage());
            throw new LocalizedException(__('Error initializing writer: ' . $e->getMessage()));
        }
    }

    /**
     * Ensures that the export directory exists.
     *
     * @param WriteInterface $varDirectory
     *
     * @throws FileSystemException
     */
    private function createExportDirectory(WriteInterface $varDirectory): void
    {
        if (!$varDirectory->isExist('export')) {
            $varDirectory->create('export');
        }
    }
}
