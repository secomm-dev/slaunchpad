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

namespace Mirasvit\SeoContent\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Api\Repository\RewriteRepositoryInterface;
use Mirasvit\SeoContent\Model\ResourceModel\Rewrite\Collection as RewriteCollection;

class RewriteService
{
    private $rewriteRepository;

    private $storeManager;

    private $request;

    private $rewriteCollection;

    public function __construct(
        RewriteRepositoryInterface $rewriteRepository,
        StoreManagerInterface      $storeManager,
        RequestInterface           $request
    ) {
        $this->rewriteRepository = $rewriteRepository;
        $this->storeManager      = $storeManager;
        $this->request           = $request;
    }

    public function getRewrite(?string $url = null, $store = null): ?RewriteInterface
    {
        if ($url == null) {
            $url = $this->request->getRequestUri();
        }

        $store = $this->storeManager->getStore($store);

        $fullActionName = $this->request->getFullActionName();

        $storeCode = $store->getCode();

        $collection = $this->getRewriteCollection($store);

        foreach ($collection as $rewrite) {
            $rewriteUrl = $rewrite->getUrl();

            if (strpos(ltrim($url, '/'), $storeCode . '/') === 0
                && strpos($rewriteUrl, '*') !== 0
            ) {
                if (strpos(ltrim($rewriteUrl, '/'), $storeCode . '/') !== 0) {
                    $rewriteUrl = '/' . $storeCode . '/' . ltrim($rewriteUrl, '/');
                } elseif (strpos(ltrim($rewriteUrl, '/'), $storeCode . '/') === 0) {
                    $rewriteUrl = '/' . ltrim($rewriteUrl, '/');
                }
            }

            if (ltrim($url, '/') == ltrim($rewriteUrl, '/')) {
                $rewriteUrl = $url;
            }

            if ($this->isFollowPattern($url, $rewriteUrl) ||
                $this->isFollowPattern($fullActionName, $rewriteUrl)) {
                return $rewrite;
            }
        }

        return null;
    }

    private function isFollowPattern(string $url, string $pattern): bool
    {
        $url     = strtolower($url);
        $pattern = strtolower($pattern);

        $parts = explode('*', $pattern);
        $index = 0;

        $shouldBeFirst = true;

        foreach ($parts as $part) {
            if ($part == '') {
                $shouldBeFirst = false;
                continue;
            }

            $index = strpos($url, $part, $index);

            if ($index === false) {
                return false;
            }

            if ($shouldBeFirst && $index > 0) {
                return false;
            }

            $shouldBeFirst = false;
            $index         += strlen($part);
        }

        if (count($parts) == 1) {
            return $url == $pattern;
        }

        $last = end($parts);
        if ($last == '') {
            return true;
        }

        if (strrpos($url, $last) === false) {
            return false;
        }

        if (strlen($url) - strlen($last) - strrpos($url, $last) > 0) {
            return false;
        }

        return true;
    }

    public function getRewriteCollection(StoreInterface $store): RewriteCollection
    {
        if (!isset($this->rewriteCollection[$store->getId()])) {
            $collection = $this->rewriteRepository->getCollection();
            $collection->addFieldToFilter(RewriteInterface::IS_ACTIVE, true)
                ->addStoreFilter($store)
                ->setOrder(RewriteInterface::SORT_ORDER, 'desc');

            $this->rewriteCollection[$store->getId()] = $collection;
        }

        return $this->rewriteCollection[$store->getId()];
    }
}
