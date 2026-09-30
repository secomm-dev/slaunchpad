<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * TASK-WY6WP5 — Shipping Coverage grid row action (directive §5/§7): "Configure" opens
 * the create form for a Not Configured target, "Edit" opens the same form for a
 * Configured one. Both lead to secomm_shippingcore/coverage/edit with explicit
 * target_type/target_code.
 */
class CoverageActions extends Column
{
    private const URL_PATH_EDIT = 'secomm_shippingcore/coverage/edit';

    private UrlInterface $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $code = $item['target_code'] ?? null;
            if ($code === null) {
                continue;
            }
            $item[$this->getName()]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, [
                    'target_type' => $item['target_type'] ?? 'CARRIER',
                    'target_code' => $code,
                ]),
                'label' => ($item['action_mode'] ?? 'edit') === 'configure' ? __('Configure') : __('Edit'),
            ];
        }

        return $dataSource;
    }
}
