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



namespace Mirasvit\SeoToolbar\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\SeoToolbar\Api\Data\DataProviderItemInterface;
use Mirasvit\SeoToolbar\Api\Service\DataProviderInterface;

class Toolbar extends Template
{
    protected $_template = 'Mirasvit_SeoToolbar::toolbar.phtml';

    /**
     * @var DataProviderInterface[]
     */
    private $dataProviderPool;

    public function __construct(
        Context $context,
        array $dataProviderPool
    ) {
        $this->dataProviderPool = $dataProviderPool;

        ksort($this->dataProviderPool);

        parent::__construct($context);
    }

    /**
     * @return array[]
     */
    public function getSections()
    {
        $sections = [];

        foreach ($this->dataProviderPool as $dataProvider) {
            $sections[] = [
                'title' => (string)$dataProvider->getTitle(),
                'items' => $dataProvider->getItems()
            ];
        }

        foreach ($sections as &$section) {
            if (isset($section['items'])) {
                foreach ($section['items'] as &$item) {
                    $item['title'] = $item['title'] ?? '';
                    $item['status'] = $item['status'] ?? '';
                    $item['description'] = nl2br($item['description'] ?? '');
                    $item['note'] = $item['note'] ?? '';
                    $item['action'] = $item['action'] ?? '';
                }
            }
        }

        return $sections;
    }
}
