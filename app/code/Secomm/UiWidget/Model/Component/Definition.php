<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

use InvalidArgumentException;
use Secomm\UiWidget\Api\ComponentDefinitionInterface;

/**
 * Immutable component definition populated through dependency injection.
 */
class Definition implements ComponentDefinitionInterface
{
    private const ID_PATTERN = '/^[a-z][a-z0-9_]*$/';
    private const TEMPLATE_PATTERN = '/^[A-Za-z0-9_]+::[A-Za-z0-9_\.\/-]+\.phtml$/';

    /**
     * Initialize a component definition.
     *
     * @param string $id Stable component ID.
     * @param string $label Admin label.
     * @param string $template Registered template alias.
     * @param string $group Component group.
     * @param int $schemaVersion Persisted schema version.
     * @param array $fields Component field schema.
     * @param string $sourceComponent Upstream component path.
     * @param string $sourceVersion Imported upstream version.
     * @param int $sortOrder Admin option sort order.
     * @param bool $enabled Availability flag.
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $template,
        private readonly string $group = 'content',
        private readonly int $schemaVersion = 1,
        private readonly array $fields = [],
        private readonly string $sourceComponent = '',
        private readonly string $sourceVersion = '',
        private readonly int $sortOrder = 0,
        private readonly bool $enabled = true
    ) {
        $this->validate();
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @inheritDoc
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @inheritDoc
     */
    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * @inheritDoc
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @inheritDoc
     */
    public function getSourceComponent(): string
    {
        return $this->sourceComponent;
    }

    /**
     * @inheritDoc
     */
    public function getSourceVersion(): string
    {
        return $this->sourceVersion;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Validate persisted contract fields at construction time.
     */
    private function validate(): void
    {
        if (!preg_match(self::ID_PATTERN, $this->id)) {
            throw new InvalidArgumentException('Component ID must use lowercase letters, numbers, and underscores.');
        }
        if ($this->label === '') {
            throw new InvalidArgumentException('Component label must not be empty.');
        }
        if (!preg_match(self::TEMPLATE_PATTERN, $this->template)) {
            throw new InvalidArgumentException('Component template must be a registered Magento template alias.');
        }
        if ($this->schemaVersion < 1) {
            throw new InvalidArgumentException('Component schema version must be at least 1.');
        }
    }
}
