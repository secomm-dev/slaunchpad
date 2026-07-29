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

namespace Mirasvit\SeoToolbar\Plugin\Framework\Controller\Result;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\LayoutInterface;
use Mirasvit\SeoToolbar\Model\Config;

class AppendToolbarPlugin
{
    private $request;

    private $response;

    private $layout;

    private $config;

    public function __construct(
        RequestInterface  $request,
        ResponseInterface $response,
        LayoutInterface   $layout,
        Config            $config
    ) {
        $this->request  = $request;
        $this->response = $response;
        $this->layout   = $layout;
        $this->config   = $config;
    }

    /**
     * @param \Magento\Framework\Controller\ResultInterface $subject
     * @param object                                        $result
     *
     * @return object
     */
    public function afterRenderResult($subject, $result)
    {
        if ($this->config->isToolbarActive()) {
            if ($this->isExcludedUrl()) {
                return $result;
            }

            if ($this->request->getActionName() == 'download') {
                return $result;
            }

            if ($this->response->getStatusCode() !== 200) {
                return $result;
            }

            if ($this->isAjax()) {
                return $result;
            }

            if ($toolbar = $this->layout->createBlock(\Mirasvit\SeoToolbar\Block\Toolbar::class)) {
                /** @var \Mirasvit\SeoToolbar\Block\Toolbar $toolbar */
                $this->response->setContent(
                    str_replace('</body>', $toolbar->toHtml() . '</body>', $this->response->getContent())
                );
            }
        }

        return $result;
    }

    private function isAjax(): bool
    {
        if ($this->request->getParam('_')
            || $this->request->getParam('is_ajax')
            || $this->request->getParam('isAjax')
            || $this->request->isAjax()) {
            return true;
        }

        return false;
    }

    private function isExcludedUrl(): bool
    {
        $url = (string)$this->request->getUri();

        $links = $this->config->getDisableLinks();

        foreach ($links as $link) {
            if (strpos($url, $link) !== false) {
                return true;
            }
        }

        return false;
    }
}
