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

namespace Mirasvit\SeoContent\Controller\Adminhtml\Rewrite;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Ui\Rewrite\Source\AddToSitemapSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\DescriptionPositionSource;
use Mirasvit\SeoContent\Ui\Rewrite\Source\MetaRobotsSource;

abstract class Import extends Action
{
    protected $resource;
    protected $filesystem;
    protected $context;
    protected $resultFactory;
    protected $fileUploaderFactory;
    protected $storeManager;
    protected $metaRobotsSource;
    protected $descriptionPositionSource;
    protected $addToSitemapSource;

    public function __construct(
        ResourceConnection        $resource,
        Filesystem                $filesystem,
        UploaderFactory           $fileUploaderFactory,
        Context                   $context,
        StoreManagerInterface     $storeManager,
        MetaRobotsSource          $metaRobotsSource,
        DescriptionPositionSource $descriptionPositionSource,
        AddToSitemapSource        $addToSitemapSource
    ) {
        $this->resource                  = $resource;
        $this->filesystem                = $filesystem;
        $this->context                   = $context;
        $this->fileUploaderFactory       = $fileUploaderFactory;
        $this->resultFactory             = $context->getResultFactory();
        $this->storeManager              = $storeManager;
        $this->metaRobotsSource          = $metaRobotsSource;
        $this->descriptionPositionSource = $descriptionPositionSource;
        $this->addToSitemapSource        = $addToSitemapSource;

        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->context->getAuthorization()
            ->isAllowed('Mirasvit_SeoContent::seo_content_rewrite_import_export');
    }

    protected function _initAction(): Import
    {
        $this->_setActiveMenu('Mirasvit_SeoContent::seo_content_rewrite');

        return $this;
    }
}
