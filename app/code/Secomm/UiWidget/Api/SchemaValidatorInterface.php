<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Api;

/**
 * Validates component data against its server-owned field schema.
 */
interface SchemaValidatorInterface
{
    /**
     * Return normalized data or null when required/type constraints fail.
     *
     * @param ComponentDefinitionInterface $definition Server-owned definition.
     * @param array $data Decoded component data.
     * @return array<string, mixed>|null
     */
    public function validate(ComponentDefinitionInterface $definition, array $data): ?array;
}
