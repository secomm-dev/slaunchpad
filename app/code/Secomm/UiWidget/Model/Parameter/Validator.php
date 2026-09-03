<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Parameter;

use Magento\Framework\Exception\LocalizedException;
use Secomm\UiWidget\Api\ComponentRegistryInterface;
use Secomm\UiWidget\Api\ParameterCodecInterface;
use Secomm\UiWidget\Api\SchemaValidatorInterface;

/**
 * Validates the complete persisted component parameter contract.
 */
class Validator
{
    /**
     * @param ComponentRegistryInterface $registry Component registry.
     * @param ParameterCodecInterface $codec Versioned codec.
     * @param SchemaValidatorInterface $schemaValidator Field validator.
     */
    public function __construct(
        private readonly ComponentRegistryInterface $registry,
        private readonly ParameterCodecInterface $codec,
        private readonly SchemaValidatorInterface $schemaValidator
    ) {
    }

    /**
     * Decode and validate persisted parameters.
     *
     * @param string $componentId Stable component ID.
     * @param int $schemaVersion Persisted schema version.
     * @param string $payload Encoded component data.
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function validate(string $componentId, int $schemaVersion, string $payload): array
    {
        $definition = $this->registry->get($componentId);
        if ($definition === null || $definition->getSchemaVersion() !== $schemaVersion) {
            throw new LocalizedException(__('The selected Secomm UI component or schema version is invalid.'));
        }
        $data = $this->codec->decode($payload);
        $normalized = $data === null ? null : $this->schemaValidator->validate($definition, $data);
        if ($normalized === null) {
            throw new LocalizedException(__('The Secomm UI component data is invalid.'));
        }

        return $normalized;
    }
}
