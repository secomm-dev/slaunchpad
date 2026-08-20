<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Export extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected FileFactory $fileFactory,
        protected CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();

        $header = [
            'country_id',
            'region_id',
            'city_id',
            'ghn_province_id',
            'ghn_district_id',
            'ghn_ward_code'
        ];

        $data = [$header];
        foreach ($collection as $mapping) {
            $data[] = [
                $mapping->getCountryId(),
                $mapping->getRegionId(),
                $mapping->getCityId(),
                $mapping->getGhnProvinceId(),
                $mapping->getGhnDistrictId(),
                $mapping->getGhnWardCode()
            ];
        }

        $content = '';
        $escapeCsvFormula = function (string $value): string {
            if (preg_match('/^[=+\-@\t\r]/', $value)) {
                return "'" . $value;
            }
            return $value;
        };
        foreach ($data as $row) {
            $content .= implode(',', array_map(function ($val) use ($escapeCsvFormula) {
                return '"' . str_replace('"', '""', $escapeCsvFormula((string)$val)) . '"';
            }, $row)) . "\n";
        }

        return $this->fileFactory->create(
            'ghn_address_mapping.csv',
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
