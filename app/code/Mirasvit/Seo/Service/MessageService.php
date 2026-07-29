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



namespace Mirasvit\Seo\Service;

use Mirasvit\Seo\Api\Service\MessageInterface as SeoMessageInterface;
use Magento\Framework\Message\ManagerInterface;

class MessageService implements SeoMessageInterface
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
    }

    /**
     * {@inheritdoc}
     */
    public function addNoticeWithUrl($notice, $url)
    {
        $this->messageManager->addComplexNoticeMessage(
            SeoMessageInterface::NOTICE_URL_IDENTIFIER,
            [
                'seo_notice' => $notice,
                'seo_url' => $url
            ]
        );
    }

    //Template Section

    /**
     * {@inheritdoc}
     */
    public function addProductTemplateNotice()
    {
        $notice = (string)__('When the "Use meta tags from products if they are not empty" option is set to "Yes", it will affect this template. Read more ');
        $url = 'https://docs.mirasvit.com/module-seo/current/seo/settings';
        $this->addNoticeWithUrl($notice, $url);
    }

    /**
     * {@inheritdoc}
     */
    public function addCategoryTemplateNotice()
    {
        $notice = (string)__('When the "Use meta tags from categories if they are not empty" option is set to "Yes", it will affect this template. Read more ');
        $url = 'https://docs.mirasvit.com/module-seo/current/seo/settings';
        $this->addNoticeWithUrl($notice, $url);
    }
}
