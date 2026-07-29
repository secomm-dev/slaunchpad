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

namespace Mirasvit\SeoAutolink\Controller\Adminhtml\Import;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Core\Helper\Io;
use Mirasvit\Seo\Model\RedirectFactory;
use Mirasvit\SeoAutolink\Controller\Adminhtml\Import;

class Save extends Import
{
    private $targets = ['_self', '_blank', '_parent', '_top'];

    private $filesystemHelper;

    public function __construct(
        ResourceConnection    $resource,
        Filesystem            $filesystem,
        UploaderFactory       $fileUploaderFactory,
        Context               $context,
        StoreManagerInterface $storeManager,
        Io                    $filesystemHelper
    ) {
        $this->filesystemHelper = $filesystemHelper;

        parent::__construct(
            $resource,
            $filesystem,
            $fileUploaderFactory,
            $context,
            $storeManager
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function execute()
    {
        $uploader = $this->fileUploaderFactory->create(['fileId' => 'import_file']);
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
            $tableLink       = $resource->getTableName('mst_seoautolink_link');
            $tableLinkStore  = $resource->getTableName('mst_seoautolink_link_store');
            $i               = 0;
            foreach ($items as $item) {
                if (!isset($item['keyword'])) {
                    continue;
                }

                $item = new DataObject($item);

                $linkType = in_array($item->getLinkType(), ['url', 'product', 'category'])
                    ? $item->getLinkType()
                    : 'url';

                if ($linkType === 'url' && !$item->getUrl()) {
                    continue;
                }

                $target = $item->getUrlTarget() && in_array($item->getUrlTarget(), $this->targets)
                    ? $item->getUrlTarget()
                    : '_self';

                $query = "REPLACE {$tableLink} SET
		            link_id = '" . (int)$item->getLinkId() . "',
                    keyword = '" . addslashes((string)$item->getKeyword()) . "',
                    url = '" . addslashes((string)$item->getUrl()) . "',
                    url_title = '" . addslashes((string)$item->getUrlTitle()) . "',
                    url_target = '" . $target . "',
                    is_nofollow = '" . (int)($item->getIsNofollow()) . "',
                    max_replacements = '" . (int)($item->getMaxReplacements()) . "',
                    sort_order = '" . (int)($item->getSortOrder()) . "',
                    occurence = '" . (int)($item->getOccurence()) . "',
                    is_active = '" . (int)($item->getIsActive()) . "',
                    link_type = '" . $linkType . "',
                    entity_id = " . ($item->getEntityId() ? (int)$item->getEntityId() : 'NULL') . ",
                    created_at = '" . (date('Y-m-d H:i:s')) . "',
                    updated_at = '" . (date('Y-m-d H:i:s')) . "';
                ";

                $writeConnection->query($query);

                $lastInsertId = $item->getLinkId() ?: $writeConnection->lastInsertId();
                $storeIds     = ($item->getStoreId()) ? explode('/', (string)$item->getStoreId()) : [0];
                $storeIds     = array_intersect($storeIds, $existingStoreIds);

                if (!count($storeIds) || in_array(0, $storeIds)) {
                    $storeIds = [0];
                }

                foreach ($storeIds as $storeId) {
                    $query = "REPLACE {$tableLinkStore} SET
                            store_id = '" . $storeId . "',
                            link_id = " . $lastInsertId . ";";

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
