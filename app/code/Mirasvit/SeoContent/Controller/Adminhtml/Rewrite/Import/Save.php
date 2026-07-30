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

namespace Mirasvit\SeoContent\Controller\Adminhtml\Rewrite\Import;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Core\Helper\Io;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Controller\Adminhtml\Rewrite\Import;
use Mirasvit\SeoContent\Ui\Rewrite\Source\AddToSitemapSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\DescriptionPositionSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\MetaRobotsSource;

class Save extends Import
{
    private $filesystemHelper;

    public function __construct(
        ResourceConnection        $resource,
        Filesystem                $filesystem,
        UploaderFactory           $fileUploaderFactory,
        Context                   $context,
        StoreManagerInterface     $storeManager,
        MetaRobotsSource          $metaRobotsSource,
        DescriptionPositionSource $descriptionPositionSource,
        AddToSitemapSource        $addToSitemapSource,
        Io                        $filesystemHelper
    ) {
        $this->filesystemHelper = $filesystemHelper;

        parent::__construct(
            $resource,
            $filesystem,
            $fileUploaderFactory,
            $context,
            $storeManager,
            $metaRobotsSource,
            $descriptionPositionSource,
            $addToSitemapSource
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

            $recordNum = 0;
            if (count($items)) {
                $resource        = $this->resource;
                $writeConnection = $resource->getConnection('core_write');
                $table           = $resource->getTableName(RewriteInterface::TABLE_NAME);

                $isActive = [0, 1];

                $useInBreadcrumbs = [0, 1];

                $storeIds = [];
                foreach ($this->storeManager->getStores() as $store) {
                    $storeIds[] = $store->getId();
                }

                $addToSitemaps = [];
                foreach ($this->addToSitemapSource->toOptionArray() as $addToSitemap) {
                    $addToSitemaps[] = $addToSitemap['value'];
                }

                $metaRobots = [];
                foreach ($this->metaRobotsSource->toOptionArray() as $metaRobot) {
                    $metaRobots[] = $metaRobot['value'];
                }

                $descriptionPositions = [];
                foreach ($this->descriptionPositionSource->toOptionArray() as $descriptionPosition) {
                    $descriptionPositions[] = $descriptionPosition['value'];
                }

                foreach ($items as $item) {
                    if (!isset($item['url'])) {
                        continue;
                    }

                    $item['is_active'] = isset($item['is_active']) && in_array($item['is_active'], $isActive)
                        ? $item['is_active']
                        : 0;

                    $item['use_in_breadcrumbs'] = isset($item['use_in_breadcrumbs']) && in_array($item['use_in_breadcrumbs'], $useInBreadcrumbs)
                        ? $item['use_in_breadcrumbs']
                        : 0;

                    $item['store_ids'] = isset($item['store_ids']) && in_array($item['store_ids'], $storeIds)
                        ? $item['store_ids']
                        : 0;

                    $item['add_to_sitemap'] = isset($item['add_to_sitemap']) && in_array($item['add_to_sitemap'], $addToSitemaps)
                        ? $item['add_to_sitemap']
                        : 0;

                    $item['description_position'] = isset($item['description_position']) && in_array($item['description_position'], $descriptionPositions)
                        ? $item['description_position']
                        : 0;

                    $item['meta_robots'] = isset($item['meta_robots']) && in_array($item['meta_robots'], $metaRobots)
                        ? $item['meta_robots']
                        : '-';

                    $item['is_autogenerated'] = 0;

                    $writeConnection->insertOnDuplicate($table, $item);

                    $recordNum++;
                }
            }

            $this->messageManager->addSuccessMessage(__('%1 records were inserted or updated', $recordNum));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        if ($this->filesystemHelper->fileExists($fullPath)) {
            $this->filesystemHelper->unlink($fullPath);
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
