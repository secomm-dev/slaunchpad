<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Controller\Adminhtml\RedirectImportExport;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Core\Helper\Io;
use Mirasvit\Seo\Controller\Adminhtml\RedirectImportExport;
use Mirasvit\Seo\Model\RedirectFactory;

class Import extends RedirectImportExport
{
    private $filesystemHelper;

    public function __construct(
        ResourceConnection    $resource,
        Filesystem            $filesystem,
        UploaderFactory       $fileUploaderFactory,
        Context               $context,
        StoreManagerInterface $storeManager,
        FileFactory           $fileFactory,
        RedirectFactory       $redirectFactory,
        Io                    $filesystemHelper
    ) {
        $this->filesystemHelper = $filesystemHelper;

        parent::__construct(
            $resource,
            $filesystem,
            $fileUploaderFactory,
            $context,
            $storeManager,
            $fileFactory,
            $redirectFactory
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function execute()
    {
        $uploader = $this->fileUploaderFactory->create(['fileId' => 'import_redirect_file']);
        $uploader->setAllowedExtensions(['csv']);
        $uploader->setAllowRenameFiles(true);

        $path = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath('import');

        if (!$this->filesystemHelper->dirExists($path)) {
            $this->filesystemHelper->mkdir($path, 0777);
        }

        $fullPath = '';

        try {
            $result   = $uploader->save($path);
            $fullPath = $result['path'] . '/' . $result['file'];

            $items = $this->filesystemHelper->getCsvItems($fullPath);

            $existingStoreIds = [0];
            $stores           = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $existingStoreIds[] = $store->getId();
            }

            $resource        = $this->resource;
            $writeConnection = $resource->getConnection('core_write');
            $table           = $resource->getTableName('mst_seo_redirect');
            $tableB          = $resource->getTableName('mst_seo_redirect_store');
            $i               = 0;
            foreach ($items as $item) {
                if (!isset($item['url_from']) || !isset($item['url_to'])) {
                    continue;
                }
                if (trim($item['url_from']) === trim($item['url_to'])) {
                    continue;
                }
                $item = new DataObject($item);
                $query = "REPLACE {$table} SET
                    redirect_id = '" . $item->getRedirectId() . "',
                    url_from = '" . addslashes((string)$item->getUrlFrom()) . "',
                    url_to = '" . addslashes((string)$item->getUrlTo()) . "',
                    redirect_type = '" . addslashes((string)$item->getRedirectType()) . "',
                    is_redirect_only_error_page = '" . addslashes((string)$item->getIsRedirectOnlyErrorPage()) . "',
                    comments = '" . addslashes((string)$item->getComments()) . "',
                    is_active = '" . addslashes((string)$item->getIsActive()) . "';
                     ";

                $writeConnection->query($query);

                $lastInsertId = $item->getRedirectId() ?: $writeConnection->lastInsertId();
                $storeIds     = ($item->getStoreId()) ? explode('/', (string)$item->getStoreId()) : [0];
                $storeIds     = array_intersect($storeIds, $existingStoreIds);

                foreach ($storeIds as $storeId) {
                    $query = "REPLACE {$tableB} SET
                        store_id = " . $storeId . ",
                        redirect_id = " . $lastInsertId . ";";

                    $writeConnection->query($query);
                }

                ++$i;
            }

            $this->messageManager->addSuccessMessage(__('%1 records were inserted or updated', $i));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        if ($this->filesystemHelper->fileExists($fullPath)) {
            $this->filesystemHelper->unlink($fullPath);
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
