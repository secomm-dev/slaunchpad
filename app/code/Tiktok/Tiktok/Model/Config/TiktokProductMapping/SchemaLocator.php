<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\TiktokProductMapping;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Product Mapping Schema Locator
 */
class SchemaLocator implements SchemaLocatorInterface
{
    /**
     * @var string
     */
    protected string $schema;

    /**
     * @var string
     */
    protected string $perFileSchema;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     */
    public function __construct(ModuleDirReader $moduleReader)
    {
        $moduleEtcPath = $moduleReader->getModuleDir('etc', 'Tiktok_Tiktok');
        $this->schema = $moduleEtcPath . '/tiktok_product_mapping.xsd';
        $this->perFileSchema = $moduleEtcPath . '/tiktok_product_mapping.xsd';
    }

    /**
     * Get path to merged config schema
     *
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Get path to per file validation schema
     *
     * @return string
     */
    public function getPerFileSchema(): string
    {
        return $this->perFileSchema;
    }
}
