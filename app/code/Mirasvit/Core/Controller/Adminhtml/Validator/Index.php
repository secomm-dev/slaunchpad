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



namespace Mirasvit\Core\Controller\Adminhtml\Validator;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutInterface;
use Mirasvit\Core\Block\Adminhtml\Validator;

class Index extends Action
{
    /**
     * Authorization level of a basic admin session.
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mirasvit_Core::validator';

    private $layout;

    public function __construct(Context $context, LayoutInterface $layout)
    {
        parent::__construct($context);

        $this->layout = $layout;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        // Create the block via the injected layout instead of the deprecated
        // Magento\Framework\App\View ($this->_view), which is unsafe under
        // Mage-OS 3.1 / PHP 8.4 lazy object loading.
        /** @var \Mirasvit\Core\Block\Adminhtml\Validator $validator */
        $validator = $this->layout->createBlock(Validator::class);

        $resultJson->setData([
            'content'  => $validator->toHtml(),
            'isPassed' => $validator->isPassed(),
        ]);

        return $resultJson;
    }
}
