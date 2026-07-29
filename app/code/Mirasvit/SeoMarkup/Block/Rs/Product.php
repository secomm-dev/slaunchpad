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

namespace Mirasvit\SeoMarkup\Block\Rs;

use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;
use Magento\Framework\View\Element\Template;
use Mirasvit\SeoMarkup\Service\ProductRichSnippetsService;

class Product extends Template
{
    private $registry;

    private $productConfig;

    private $productSnippetService;

    private $serializer;

    public function __construct(
        ProductConfig              $productConfig,
        Registry                   $registry,
        Context                    $context,
        ProductRichSnippetsService $productSnippetService,
        Json                       $serializer
    ) {
        $this->productSnippetService = $productSnippetService;
        $this->registry              = $registry;
        $this->productConfig         = $productConfig;
        $this->serializer            = $serializer;

        parent::__construct($context);
    }

    protected function _toHtml()
    {
        if (!$this->productConfig->isRsEnabled((int)$this->_storeManager->getStore()->getId())) {
            return '';
        }

        $product = $this->registry->registry('current_product');

        $data = $this->productSnippetService->getJsonData($product);

        if (!$data) {
            return '';
        }

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }
}
