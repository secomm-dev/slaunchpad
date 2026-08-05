<?php
/**
 * Magezon
 *
 * This source file is subject to the Magezon Software License, which is available at https://www.magezon.com/license
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to https://www.magezon.com for more information.
 *
 * @category  Magezon
 * @package   Magezon_Core
 * @copyright Copyright (C) 2019 Magezon (https://www.magezon.com)
 */

namespace Magezon\Core\Helper;

use Magento\Backend\Model\Auth\Session as AuthSession;

class User extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var AuthSession
     */
    protected $authSession;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        AuthSession $authSession,
    ) {
        parent::__construct($context);
        $this->authSession  = $authSession;
    }

    public function getCurrentUserExtra() {
        $currentUser = $this->authSession->getUser();
        return (array) $currentUser->getExtra();
    }
}