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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Core\Controller\Adminhtml\Lc;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Module\FullModuleList;
use Mirasvit\Core\Model\LicenseFactory;


class Refresh extends Action
{
    private $licenseFactory;

    private $fullModuleList;

    public function __construct(
        LicenseFactory $licenseFactory,
        FullModuleList $fullModuleList,
        Context        $context
    ) {
        $this->licenseFactory = $licenseFactory;
        $this->fullModuleList = $fullModuleList;

        parent::__construct($context);
    }

    public function execute()
    {
        foreach ($this->fullModuleList->getAll() as $moduleData) {
            if (substr($moduleData['name'], 0, strlen('Mirasvit_')) !== 'Mirasvit_') {
                continue;
            }

            $l = $this->licenseFactory->create();
            $l->load($moduleData['name']);

            $l->clear();
        }

        $this->_redirect($this->_redirect->getRefererUrl());
    }
}