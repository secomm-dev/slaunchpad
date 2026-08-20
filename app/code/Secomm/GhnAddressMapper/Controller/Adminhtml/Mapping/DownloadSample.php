<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class DownloadSample extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $content = "country_id,region_id,city_id,ghn_province_id,ghn_district_id,ghn_ward_code\n";
        $content .= "VN,1185,1,202,1442,20110\n";
        $content .= "VN,1185,2,202,1442,20101\n";
        $content .= "VN,1185,3,202,1442,20102\n";
        $content .= "VN,1185,4,202,1442,20109\n";
        $content .= "VN,1185,5,202,1442,20106\n";

        return $this->fileFactory->create(
            'sample_ghn_address_mapping.csv',
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
