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

namespace Mirasvit\SeoAutolink\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;

abstract class Import extends Action
{
    protected $resource;
    protected $filesystem;
    protected $context;
    protected $fileUploaderFactory;
    protected $storeManager;

    public function __construct(
        ResourceConnection    $resource,
        Filesystem            $filesystem,
        UploaderFactory       $fileUploaderFactory,
        Context               $context,
        StoreManagerInterface $storeManager
    ) {
        $this->resource            = $resource;
        $this->filesystem          = $filesystem;
        $this->context             = $context;
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->storeManager        = $storeManager;

        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->context->getAuthorization()
            ->isAllowed('Mirasvit_SeoAutolink::seoautolink_autolinks_seoautolinkimport');
    }

    protected function _initAction(): Import
    {
        $this->_setActiveMenu('Mirasvit_SeoAutolink::seoautolink_link');

        return $this;
    }
}
