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



namespace Mirasvit\Seo\Plugin\Frontend;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Controller\Router;
use Mirasvit\Seo\Model\Config;
use Mirasvit\Seo\Service\TrailingSlashService;
use Psr\Log\LoggerInterface;

/**
 * @see \Magento\UrlRewrite\Controller\Router::match();
 */
class UrlRewriteRouterApplyTrailingSlashPlugin
{
    private $config;

    private $storeManager;

    private $trailingSlashService;

    private $logger;

    public function __construct(
        Config                $config,
        StoreManagerInterface $storeManager,
        TrailingSlashService  $trailingSlashService,
        LoggerInterface       $logger
    ) {
        $this->config               = $config;
        $this->storeManager         = $storeManager;
        $this->trailingSlashService = $trailingSlashService;
        $this->logger               = $logger;
    }

    public function aroundMatch(Router $subject, callable $proceed, RequestInterface $request)
    {
        if (strpos($request->getFullActionName(), 'checkout') !== false
            || $this->config->getTrailingSlash() == Config::TRAILING_SLASH_DISABLE
        ) {
            return $proceed($request);
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();

            $requestPath = ltrim($request->getPathInfo(), '/');

            $this->trailingSlashService->processRequestPath($requestPath, $storeId);
        } catch (Exception $e) {
            $this->logger->error('Error in Trailing Slash Router Plugin: ' . $e->getMessage(), [
                'path_info' => $request->getPathInfo(),
                'exception' => $e
            ]);
        }

        return $proceed($request);
    }
}
