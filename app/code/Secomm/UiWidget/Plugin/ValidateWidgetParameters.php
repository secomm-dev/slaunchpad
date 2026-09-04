<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Plugin;

use Magento\Widget\Model\Widget;
use Secomm\UiWidget\Block\Widget\SecommUi;
use Secomm\UiWidget\Model\Parameter\Validator;

/**
 * Rejects malformed Secomm UI data before Magento builds a CMS directive.
 */
class ValidateWidgetParameters
{
    /**
     * @param Validator $validator Persisted parameter validator.
     */
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * Validate only this module's widget declaration.
     *
     * @param Widget $subject Widget model.
     * @param string $type Widget block type.
     * @param array $params Widget parameters.
     * @param bool $asIs Whether to return a directive.
     * @return array{string, array<string, mixed>, bool}
     */
    public function beforeGetWidgetDeclaration(
        Widget $subject,
        string $type,
        array $params = [],
        bool $asIs = true
    ): array {
        if ($type === SecommUi::class) {
            $this->validator->validate(
                (string)($params['component'] ?? ''),
                (int)($params['schema_version'] ?? 0),
                (string)($params['payload'] ?? '')
            );
        }

        return [$type, $params, $asIs];
    }
}
