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

namespace Mirasvit\SeoMarkup\Controller\Adminhtml\Extender;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;

class Delete extends Action implements HttpPostActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Mirasvit_SeoAutolink::seomarkup_extender';

    private $extenderRepository;

    public function __construct(
        Context            $context,
        ExtenderRepository $extenderRepository
    ) {
        parent::__construct($context);

        $this->extenderRepository = $extenderRepository;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $extenderId = $this->getRequest()->getParam(ExtenderInterface::REQUEST_PARAM_ID);
        if (!is_numeric($extenderId)) {
            $this->messageManager->addErrorMessage(__('Wrong request.'));

            return $resultRedirect->setPath('*/*');
        }

        try {
            $this->extenderRepository->deleteById((int)$extenderId);
            $this->messageManager->addSuccessMessage(__('Rich snippet extender has been deleted.'));
        } catch (CouldNotDeleteException $exception) {    // @SuppressWarnings(PHPMD.UnusedFormalParameter)
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        $resultRedirect->setPath('*/*');

        return $resultRedirect;
    }
}
