<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Profile\Config;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — locates the XSDs for merged + per-file validation of
 * etc/address_profiles.xml across all modules.
 */
class SchemaLocator implements SchemaLocatorInterface
{
    /**
     * Path to corresponding XSD file with validation data for merged config.
     *
     * @var string
     */
    protected string $schema;

    /**
     * Path to corresponding XSD file with validation data for per-file config.
     *
     * @var string
     */
    protected string $perFileSchema;

    public function __construct(Reader $moduleReader)
    {
        $etcDir = $moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Secomm_AddressDropdown');
        $this->schema = $etcDir . '/address_profiles.xsd';
        $this->perFileSchema = $etcDir . '/address_profiles_file.xsd';
    }

    /**
     * @inheritDoc
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @inheritDoc
     */
    public function getPerFileSchema(): ?string
    {
        return $this->perFileSchema;
    }
}
