<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Locale\ResolverInterface;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Psr\Log\LoggerInterface;

class DownloadReferencePack extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected FileFactory $fileFactory,
        protected LocationMappingResource $resource,
        protected ResolverInterface $localeResolver,
        protected LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'zip');

        try {
            $zip = new \ZipArchive();
            $zipFileName = 'ghn_address_mapping_reference_pack.zip';

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception(__('Cannot create temporary Zip file.'));
            }

            // 1. Generate Magento Locations Reference CSV dynamically based on active locale
            $magentoCsvContent = $this->generateMagentoLocationsCsv();
            $zip->addFromString('1_magento_locations_reference.csv', $magentoCsvContent);

            // 2. Generate GHN Locations Reference CSV
            $ghnCsvContent = $this->generateGhnLocationsCsv();
            $zip->addFromString('2_ghn_locations_reference.csv', $ghnCsvContent);

            // 3. Generate Mapping Template CSV
            $templateCsvContent = "country_id,region_id,city_id,ghn_province_id,ghn_district_id,ghn_ward_code\n";
            $templateCsvContent .= "VN,1185,1,202,1442,20110\n";
            $zip->addFromString('3_mapping_template.csv', $templateCsvContent);

            $zip->close();

            $zipContent = file_get_contents($zipPath);

            return $this->fileFactory->create(
                $zipFileName,
                $zipContent,
                DirectoryList::VAR_DIR,
                'application/zip'
            );
        } catch (\Exception $e) {
            $this->logger->error('GHN Address Mapper: DownloadReferencePack error', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Could not download reference pack. Please check the log for details.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/index');
        } finally {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    /**
     * Escape CSV cell value to prevent formula injection in spreadsheet applications.
     */
    private function escapeCsvCell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function generateMagentoLocationsCsv(): string
    {
        $currentLocale = $this->localeResolver->getLocale();
        $rows = $this->resource->getMagentoLocationsReference($currentLocale);
        $csv = "country_id,region_id,region_name,city_id,city_name\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', $this->escapeCsvCell((string)$val)) . '"';
            }, [
                $row['country_id'],
                $row['region_id'],
                $row['region_name'],
                $row['city_id'],
                $row['city_name']
            ])) . "\n";
        }
        return $csv;
    }

    private function generateGhnLocationsCsv(): string
    {
        $rows = $this->resource->getGhnLocationsReference();
        $csv = "ghn_province_id,ghn_province_name,ghn_district_id,ghn_district_name,ghn_ward_code,ghn_ward_name\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', $this->escapeCsvCell((string)$val)) . '"';
            }, [
                $row['province_id'],
                $row['province_name'],
                $row['district_id'],
                $row['district_name'],
                $row['ward_code'],
                $row['ward_name']
            ])) . "\n";
        }
        return $csv;
    }
}
