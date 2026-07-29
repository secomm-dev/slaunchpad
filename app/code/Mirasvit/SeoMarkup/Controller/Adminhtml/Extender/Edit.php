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
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;

class Edit extends Action implements HttpGetActionInterface
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

    public function execute(): ResultInterface
    {
        $extenderId = $this->getRequest()->getParam(ExtenderInterface::REQUEST_PARAM_ID);
        $extenderId = is_numeric($extenderId) ? (int)$extenderId : null;

        if (is_null($extenderId)) {
            return $this->resultRedirectFactory->create()->setPath('*/*/new');
        }

        // restoring the form of a new entity after an error
        if (0 === $extenderId) {
            return $this->resultRedirectFactory->create()
                ->setPath(sprintf('*/*/new/%s/%s', ExtenderInterface::REQUEST_PARAM_ID, $extenderId));
        }

        $title = (string)__('Edit rich snippet extender');
        try {
            $this->extenderRepository->get($extenderId);
            /** @var Page $result */
            $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
            $result->setActiveMenu(self::ADMIN_RESOURCE)->addBreadcrumb($title, $title);
            $result->getConfig()->getTitle()->prepend($title);
        } catch (NoSuchEntityException $exception) {    // @SuppressWarnings(PHPMD.UnusedFormalParameter)
            $result = $this->resultRedirectFactory->create();
            $this->messageManager
                ->addErrorMessage(__('Rich snippet extender with ID "%1" does not exist.', $extenderId));
            $result->setPath('*/*');
        }

        return $result;
    }
}
