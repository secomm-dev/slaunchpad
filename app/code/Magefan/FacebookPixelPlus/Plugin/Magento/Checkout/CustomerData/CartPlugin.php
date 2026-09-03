<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Plugin\Magento\Checkout\CustomerData;

use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\SessionManagerInterface;
use Magefan\FacebookPixelPlus\Plugin\Magento\CustomerDataPlugin;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session as Session;
use Magento\Framework\App\RequestInterface;

class CartPlugin extends CustomerDataPlugin
{
    public function __construct(
        RequestInterface                       $request,
        Session                                $session,
        SessionManagerInterface                $sessionManager,
        Config $config
    ) {
        parent::__construct($request, $session, $sessionManager, $config);
    }
}
