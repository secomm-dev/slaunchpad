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

namespace Mirasvit\SeoMarkup\Ui\Extender\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;

class Actions extends Column
{
    const EXTENDER_URL_PATH_EDIT   = 'seomarkup/extender/edit';
    const EXTENDER_URL_PATH_DELETE = 'seomarkup/extender/delete';

    private $urlBuilder;

    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlBuilder,
        array              $components = [],
        array              $data = []
    ) {
        $this->urlBuilder = $urlBuilder;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $name = $this->getData('name');
                if (isset($item[ExtenderInterface::EXTENDER_ID])) {
                    $item[$name] = [
                        'edit'   => [
                            'href'  => $this->urlBuilder->getUrl(self::EXTENDER_URL_PATH_EDIT, [
                                ExtenderInterface::REQUEST_PARAM_ID => $item[ExtenderInterface::EXTENDER_ID],
                            ]),
                            'label' => __('Edit'),
                        ],
                        'delete' => [
                            'href'    => $this->urlBuilder->getUrl(self::EXTENDER_URL_PATH_DELETE, [
                                ExtenderInterface::REQUEST_PARAM_ID => $item[ExtenderInterface::EXTENDER_ID],
                            ]),
                            'label'   => __('Delete'),
                            'confirm' => [
                                'title'   => __('Delete record with ID %1', $item[ExtenderInterface::EXTENDER_ID]),
                                'message' => __('Are you sure you want to delete this rich snippet extender?'),
                            ],
                            'post'    => true,
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
