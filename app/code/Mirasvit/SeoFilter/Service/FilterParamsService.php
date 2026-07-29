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

namespace Mirasvit\SeoFilter\Service;

use Magento\Framework\App\RequestInterface;

class FilterParamsService
{
    private const SYSTEM_PARAMS = [
        'isAjax', 'is_ajax', 'is_scroll', 'scrollAjax', 'ajax', 'mode', '_', 'form_key', 'uenc',
        'p', 'page', 'product_list_order', 'product_list_dir', 'product_list_mode', 'product_list_limit',
        'q', 'cat', 'price', 'dir', 'order', 'limit', 'toolbar_state'
    ];

    private const ROUTE_PARAMS = ['controller', 'action', 'module', 'id', 'category', 'entity_id'];

    private $request;

    /** @var array|null */
    private $cachedFilterParams = null;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function getFilterParams(): array
    {
        if ($this->cachedFilterParams !== null) {
            return $this->cachedFilterParams;
        }

        $excludeParams = array_merge(self::SYSTEM_PARAMS, self::ROUTE_PARAMS);

        $params = $this->request->getParams();
        $params = array_diff_key($params, array_flip($excludeParams));

        $params = array_filter($params, function ($value, $key) {
            return preg_match('/^[a-z][a-z0-9_]*$/i', strval($key)) && $value !== '' && $value !== null;
        }, ARRAY_FILTER_USE_BOTH);

        $this->cachedFilterParams = $params;

        return $this->cachedFilterParams;
    }
}
