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



namespace Mirasvit\Seo\Controller\Adminhtml\Duplicateinfo;

use Magento\Framework\Controller\ResultFactory;

class Index extends \Mirasvit\Seo\Controller\Adminhtml\Duplicateinfo
{
    /**
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
         $seoSectionUrl = $this->_url->getUrl(
                            'adminhtml/system_config/edit',
                            ['section' => 'seo']
                        );

        $this->messageManager->addNotice(__('Create a unique url key for all of your categories listed on this table. If the table is empty, this means that you do not have duplicate keys and can push the "<a href="%1" target="_blank">Remove parent category path</a>" button to change category urls.', $seoSectionUrl));

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $resultPage->getConfig()->getTitle()->prepend(__('Category duplicate urls'));
        $this->_initAction();

        // createBlock + setChild on the result page's own layout. Avoids the deprecated
        // AbstractAction::_addContent(), which targets Magento\Framework\App\View's
        // separate layout and breaks under Mage-OS 3.1 / PHP 8.4 lazy object loading.
        // See mage-os/mageos-magento2#284.
        $layout = $resultPage->getLayout();
        /** @var \Magento\Framework\View\Element\AbstractBlock $block */
        $block = $layout->createBlock('\Mirasvit\Seo\Block\Adminhtml\Duplicateinfo');
        $layout->setChild('content', $block->getNameInLayout(), '');

        return $resultPage;
    }
}
