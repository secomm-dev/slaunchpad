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

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Controller\Adminhtml\Rewrite\Import;
use Mirasvit\SeoContent\Ui\Rewrite\Source\AddToSitemapSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\DescriptionPositionSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\MetaRobotsSource;

class Download extends Import
{
    const SAMPLE_FILES_MODULE = 'Mirasvit_SeoContent';

    private $fileFactory;
    private $componentRegistrar;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        FileFactory               $fileFactory,
        ComponentRegistrar        $componentRegistrar,
        ResourceConnection        $resource,
        Filesystem                $filesystem,
        UploaderFactory           $fileUploaderFactory,
        Context                   $context,
        StoreManagerInterface     $storeManager,
        MetaRobotsSource          $metaRobotsSource,
        DescriptionPositionSource $descriptionPositionSource,
        AddToSitemapSource        $addToSitemapSource
    ) {
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

        $this->fileFactory        = $fileFactory;
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $fileName = $this->getRequest()->getParam('file') . '.csv';
        $moduleDir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::SAMPLE_FILES_MODULE);
        $fileAbsolutePath = realpath($moduleDir . '/media/') . '/' . $fileName;

        if (!file_exists($fileAbsolutePath)) {
            $this->messageManager->addErrorMessage(__('There is no sample file for this entity.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/index');
            return $resultRedirect;
        }

        return $this->fileFactory->create(
            $fileName,
            file_get_contents($fileAbsolutePath),
            DirectoryList::VAR_DIR
        );
    }
}
