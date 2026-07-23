<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright  Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Plugin\Config;

use Magento\Config\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class ConfigPlugin
 * @package Mageplaza\Smtp\Plugin\Config
 */
class ConfigPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        RequestInterface $request,
        ManagerInterface $messageManager
    ) {
        $this->request        = $request;
        $this->messageManager = $messageManager;
    }

    /**
     * Modify SMTP config before save
     *
     * @param Config $subject
     *
     * @return array
     */
    public function beforeSave(Config $subject)
    {
        $section = $subject->getSection();

        // Only modify SMTP section
        if ($section === 'smtp') {
            $this->modifySmtpConfig($subject);
        }

        return [];
    }

    /**
     * Modify SMTP configuration
     */
    private function modifySmtpConfig($subject)
    {
        $group = $subject->getGroups();
        if (isset($group['configuration_option']['fields']['protocol']['value'])) {
            $protocol = $group['configuration_option']['fields']['protocol']['value'];
            $port     = &$group['configuration_option']['fields']['port']['value'];
            // Auto-set port based on protocol
            if ($protocol === 'tls' && $port === '465') {// 465 only for SSL
                $port = '587';
                $subject->setGroups($group);
                $this->messageManager->addNoticeMessage(__('Port automatically set to 587 for TLS protocol.'));
            }
        }
    }
}
