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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Plugin\Frontend;

use Magento\Framework\App\RequestInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Service\MatchService;
use Magento\Framework\Registry;

/**
 * @see \Magento\Framework\App\Router\Base::match()
 */
class MakeRequestBeforeRouteMatchPlugin
{
    private $params = null;

    private $configProvider;

    private $matchService;

    private $request;

    /**
     * @var Registry
     */
    private $registry;

    public function __construct(
        MatchService $matchService,
        ConfigProvider $configProvider,
        RequestInterface $request,
        Registry $registry
    ) {
        $this->matchService   = $matchService;
        $this->configProvider = $configProvider;
        $this->request        = $request;
        $this->registry       = $registry;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * 
     * Apply friendly filters
     *
     * @param object           $subject
     * @param RequestInterface $request
     *
     * @return void
     */
    public function beforeMatch($subject, RequestInterface $request): void
    {
        if ($this->isExcluded($request)) {
            return;
        }
        
        if (!$this->configProvider->canProceed()) {
            return;
        }

        if ($request->getActionName() &&  $request->getActionName() != 'landing' ) {
            // request already processed (found rewrite)
            return;
        }

        $params = $this->matchService->getParams();

        if ($params && $params['match']) {

            if (!empty($params['landing_page_route_data'])) {
                $request->setRouteName($params['landing_page_route_data']['page_url'])
                    ->setModuleName('landing')
                    ->setControllerName('landing')
                    ->setActionName('view')
                    ->setParam('landing', $params['landing_page_route_data']['page_id'])
                    ->setParams($params['params']);
            }

            if (!empty($params['brand_page_route_data'])) {
                $request->setRouteName($params['brand_page_route_data']['brandUrlKey'])
                    ->setModuleName('brand')
                    ->setControllerName('brand')
                    ->setActionName('view')
                    ->setParam('attribute_option_id', $params['brand_page_route_data']['brandId'])
                    ->setParams($params['params']);
            }

            if ($params['all_products_route']) {
                $request->setRouteName($params['all_products_route'])
                    ->setModuleName('all_products_page')
                    ->setControllerName('index')
                    ->setActionName('index')
                    ->setParams($params['params']);

                $this->registry->register('is_all_products_url', true, true);
            }
            
            if (
                isset($params['category_page_route_data']['category_id'])
                && $params['category_page_route_data']['category_id']
            ) {
                $request->setRouteName('catalog')
                    ->setModuleName('catalog')
                    ->setControllerName('category')
                    ->setActionName('view')
                    ->setParam('id', $params['category_page_route_data']['category_id'])
                    ->setParams($params['params']);
            }

            $this->params = $params['params'];
        }
    }

    /**
     * @param object $subject
     * @param object $result
     *
     * @return object
     */
    public function afterMatch($subject, $result)
    {
        //restore params (match can overwrite params with variables)
        if ($this->params) {
            $this->request->setParams($this->params);
        }

        return $result;
    }

    private function isExcluded(RequestInterface $request): bool
    {
        $exludedPathInfo = [
            // compatibility with amasty_quickorder
            'amasty_quickorder',
            'customer/section/load',
            // fix for unexpected response for product view pages
            // when product URL is similar to category URL with applied filters
            '/product/view/'
        ];

        foreach ($exludedPathInfo as $pathInfo) {
            if (
                strpos($request->getPathInfo(), $pathInfo) !== false
                || strpos($request->getOriginalPathInfo(), $pathInfo) !== false
            ) {
                return true;
            }
        }

        return false;
    }
}
