<?php
namespace Tiktok\Tiktok\Model\Catalog\Export;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class FileService
{
    /**
     * FileService construct
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * Retrieve file path
     *
     * @param string $filename
     * @param string $websiteId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getExportFilePath(string $filename, string $websiteId): string
    {
        $store = $this->storeManager->getStoreByWebsiteId($websiteId);
        if ($store) {
            $storeId = array_shift($store);
            $storeModel = $this->storeManager->getStore((int)$storeId);
            return $storeModel->getBaseUrl(UrlInterface::URL_TYPE_WEB)
                . 'pub/export/' . $websiteId . '_' . $filename;
        }

        return '';
    }
}
