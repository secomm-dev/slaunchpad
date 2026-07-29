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
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Mirasvit\SeoContent\Model\ResourceModel\Rewrite\CollectionFactory;

class Export extends Action
{
    public const EXPORT_FILENAME = 'seo_content_rewrite.csv';

    private $filesystem;

    private $fileFactory;

    private $collectionFactory;

    public function __construct(
        Context           $context,
        Filesystem        $filesystem,
        FileFactory       $fileFactory,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);

        $this->filesystem        = $filesystem;
        $this->fileFactory       = $fileFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $stream    = $directory->openFile(self::EXPORT_FILENAME, 'w+');
            $stream->lock();

            $items = $this->collectionFactory->create()->getItems();

            if (count($items)) {
                $stream->writeCsv(array_keys(reset($items)->getData()));
            }

            foreach ($items as $item) {
                $stream->writeCsv($item->getData());
            }
        } catch (FileSystemException $exception) {
            $this->messageManager->addErrorMessage((string)__('Unable to create export file.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            return $this->fileFactory->create(
                self::EXPORT_FILENAME,
                ['type' => 'filename', 'value' => self::EXPORT_FILENAME],
                DirectoryList::VAR_DIR
            );
        } catch (Exception $exception) {
            $this->messageManager->addErrorMessage((string)__('Unable to send export file.'));

            return $resultRedirect->setPath('*/*/');
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Mirasvit_SeoContent::seo_content_rewrite');
    }

    protected function _initAction(): Export
    {
        $this->_setActiveMenu('Mirasvit_Seo::seo');

        return $this;
    }
}
