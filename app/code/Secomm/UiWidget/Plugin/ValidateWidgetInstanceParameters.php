<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Plugin;

use Magento\Widget\Model\Widget\Instance;
use Secomm\UiWidget\Block\Widget\SecommUi;
use Secomm\UiWidget\Model\Parameter\Validator;

/**
 * Rejects malformed parameters when saving a standalone widget instance.
 */
class ValidateWidgetInstanceParameters
{
    /**
     * @param Validator $validator Persisted parameter validator.
     */
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * Validate before Magento serializes widget instance parameters.
     *
     * @param Instance $subject Widget instance.
     */
    public function beforeBeforeSave(Instance $subject): void
    {
        $params = $subject->getData('widget_parameters');
        if ($subject->getType() !== SecommUi::class || !is_array($params)) {
            return;
        }
        $this->validator->validate(
            (string)($params['component'] ?? ''),
            (int)($params['schema_version'] ?? 0),
            (string)($params['payload'] ?? '')
        );
    }
}
