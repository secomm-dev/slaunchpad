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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;
use Mageplaza\ThankYouPage\Helper\Data;

/**
 * Class Router
 * @package Mageplaza\ThankYouPage\Controller
 */
class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    protected $actionFactory;

    /**
     * @var Data
     */
    protected $_helper;

    /**
     * Router constructor.
     *
     * @param ActionFactory $actionFactory
     * @param Data $helperData
     */
    public function __construct(
        ActionFactory $actionFactory,
        Data $helperData
    ) {
        $this->actionFactory = $actionFactory;
        $this->_helper       = $helperData;
    }

    /**
     * @param RequestInterface $request
     *
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        if (! $this->_helper->isEnabled()) {
            return null;
        }

        $identifier = trim($request->getPathInfo(), '/');
        $routePath  = explode('/', $identifier);
        $routeSize  = count($routePath);

        if (! $routeSize || ($routeSize > 3)) {
            return null;
        }

        $request->setModuleName('mpthankyoupage')->setAlias(
            Url::REWRITE_REQUEST_PATH_ALIAS,
            trim($request->getPathInfo(), '/')
        );

        if ($routeSize === 1
            && $this->_helper->isNewsletterSuccessPageEnable()
            && $routePath[0] === $this->_helper->getNewsletterSuccessPageRoute()) {
            $request->setControllerName('subscribe')
                ->setActionName('index')
                ->setPathInfo('/mpthankyoupage/subscribe/index');
        } else {
            return null;
        }

        return $this->actionFactory->create('Magento\Framework\App\Action\Forward');
    }
}
