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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Block;

use Magento\Cms\Block\Block;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Osc\Helper\Data as OscHelper;
use Mageplaza\Osc\Model\System\Config\Source\StaticBlockPosition as Position;
use Zend_Serializer_Exception;

class StaticBlock extends Template
{
    /**
     * @var OscHelper
     */
    private $_oscHelper;

    /**
     * StaticBlock constructor.
     *
     * @param Context   $context
     * @param OscHelper $oscHelper
     * @param array     $data
     */
    public function __construct(
        Context $context,
        OscHelper $oscHelper,
        array $data = []
    ) {
        $this->_oscHelper = $oscHelper;

        parent::__construct($context, $data);
    }

    /**
     * @return array
     * @throws Zend_Serializer_Exception
     */
    public function getStaticBlock()
    {
        try {
            $layout = $this->getLayout();
        } catch (LocalizedException $e) {
            $this->_logger->critical($e->getMessage());

            return [];
        }

        $result = [];

        $config = ($this->_oscHelper->isEnabled() && $this->_oscHelper->isEnableStaticBlock())
            ? $this->_oscHelper->getStaticBlockList()
            : [];
        foreach ($config as $key => $row) {
            /**
 * @var Block $block
*/
            $block = $layout->createBlock(Block::class)->setBlockId($row['block'])->toHtml();
            $name = $this->getNameInLayout();
            $position = (int)$row['position'];

            if (($position === Position::SHOW_IN_SUCCESS_PAGE && $name === 'osc.static-block.success')
                || ($position === Position::SHOW_AT_TOP_CHECKOUT_PAGE && $name === 'osc.static-block.top')
                || ($position === Position::SHOW_AT_BOTTOM_CHECKOUT_PAGE && $name === 'osc.static-block.bottom')
            ) {
                $result[] = [
                    'content' => $block,
                    'sortOrder' => $row['sort_order']
                ];
            }
        }

        usort(
            $result,
            function ($a, $b) {
                return ($a['sortOrder'] <= $b['sortOrder']) ? -1 : 1;
            }
        );

        return $result;
    }

    /**
     * Sanitize content to remove potentially dangerous code
     *
     * @param string $content
     *
     * @return string
     */
    public function sanitizeContent(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        // Remove script tags and their content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);

        // Remove onclick and other event handlers
        $content = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);

        // Remove javascript: URLs
        $content = preg_replace('/\s+href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $content);

        // Remove data: URLs except for images
        $content = preg_replace('/\s+href\s*=\s*["\']data:[^"\']*["\']/i', '', $content);

        // Remove vbscript: URLs
        $content = preg_replace('/\s+href\s*=\s*["\']vbscript:[^"\']*["\']/i', '', $content);

        // Remove expression() in CSS
        $content = preg_replace('/expression\s*\([^)]*\)/i', '', $content);

        // Remove dangerous inline styles
        $content = preg_replace('/\s+style\s*=\s*["\'][^"\']*(?:expression|javascript|behavior)[^"\']*["\']/i', '', $content);

        // Remove potentially dangerous HTML tags
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'base'];
        foreach ($dangerousTags as $tag) {
            $content = preg_replace('/<' . $tag . '[^>]*>.*?<\/' . $tag . '>/is', '', $content);
            $content = preg_replace('/<' . $tag . '[^>]*\/>/is', '', $content);
        }

        return $content;
    }
}
