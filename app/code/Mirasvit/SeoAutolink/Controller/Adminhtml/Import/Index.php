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

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\ResultFactory;
use Mirasvit\SeoAutolink\Controller\Adminhtml\Import;

class Index extends Import
{
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $this->_initAction();

        $resultPage->getConfig()->getTitle()->prepend(__('Cross-links'));
        $resultPage->getConfig()->getTitle()->prepend(__('Import/Export links'));

        // createBlock + setChild on the result page's own layout. Avoids the deprecated
        // AbstractAction::_addContent(), which targets Magento\Framework\App\View's
        // separate layout and breaks under Mage-OS 3.1 / PHP 8.4 lazy object loading.
        // See mage-os/mageos-magento2#284.
        $layout = $resultPage->getLayout();
        /** @var \Magento\Framework\View\Element\AbstractBlock $block */
        $block = $layout->createBlock('\Mirasvit\SeoAutolink\Block\Adminhtml\Import\Edit');
        $layout->setChild('content', $block->getNameInLayout(), '');

        return $resultPage;
    }
}
