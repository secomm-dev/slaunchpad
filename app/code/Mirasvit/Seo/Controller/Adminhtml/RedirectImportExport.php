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

namespace Mirasvit\Seo\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\RedirectFactory;

abstract class RedirectImportExport extends Action
{
    protected $resource;
    protected $filesystem;
    protected $context;
    protected $fileUploaderFactory;
    protected $storeManager;
    protected $fileFactory;
    protected $redirectFactory;

    public function __construct(
        ResourceConnection    $resource,
        Filesystem            $filesystem,
        UploaderFactory       $fileUploaderFactory,
        Context               $context,
        StoreManagerInterface $storeManager,
        FileFactory           $fileFactory,
        RedirectFactory       $redirectFactory
    ) {
        $this->resource            = $resource;
        $this->filesystem          = $filesystem;
        $this->context             = $context;
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->storeManager        = $storeManager;
        $this->fileFactory         = $fileFactory;
        $this->redirectFactory     = $redirectFactory;

        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->context->getAuthorization()->isAllowed('Mirasvit_Seo::seo_redirect_import_export');
    }

    protected function _initAction(): RedirectImportExport
    {
        $this->_setActiveMenu('Mirasvit_Seo::seo_redirect');

        return $this;
    }
}
