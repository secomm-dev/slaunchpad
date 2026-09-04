<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Api;

/**
 * Describes one explicitly registered Secomm UI component.
 */
interface ComponentDefinitionInterface
{
    /**
     * Return the stable component ID.
     */
    public function getId(): string;

    /**
     * Return the translatable Admin label.
     */
    public function getLabel(): string;

    /**
     * Return the component group.
     */
    public function getGroup(): string;

    /**
     * Return the registered Magento template alias.
     */
    public function getTemplate(): string;

    /**
     * Return the persisted schema version.
     */
    public function getSchemaVersion(): int;

    /**
     * Return the component field schema.
     *
     * @return array<string, mixed>
     */
    public function getFields(): array;

    /**
     * Return the upstream component path.
     */
    public function getSourceComponent(): string;

    /**
     * Return the imported upstream version.
     */
    public function getSourceVersion(): string;

    /**
     * Return the Admin option sort order.
     */
    public function getSortOrder(): int;

    /**
     * Determine whether the component is available.
     */
    public function isEnabled(): bool;
}
