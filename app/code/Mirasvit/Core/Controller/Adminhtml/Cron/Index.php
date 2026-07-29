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



namespace Mirasvit\Core\Controller\Adminhtml\Cron;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action
{

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // Return a result page - the mstcore_cron_index layout handle renders the grid.
        // Replaces the deprecated Magento\Framework\App\View loadLayout()/renderLayout(),
        // which is unsafe under Mage-OS 3.1 / PHP 8.4 lazy object loading.
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
