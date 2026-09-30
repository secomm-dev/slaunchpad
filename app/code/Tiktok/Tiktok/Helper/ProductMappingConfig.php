<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Helper;

use Tiktok\Tiktok\Model\Config\TiktokProductMapping\Reader;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Helper class for Tiktok product mapping config
 */
class ProductMappingConfig extends AbstractHelper
{
    /**
     * @var \Tiktok\Tiktok\Model\Config\TiktokProductMapping\Reader
     */
    protected Reader $configReader;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Tiktok\Tiktok\Model\Config\TiktokProductMapping\Reader $configReader
     */
    public function __construct(
        Context $context,
        Reader $configReader
    ) {
        $this->configReader = $configReader;
        parent::__construct($context);
    }

    /**
     * Get product mapping
     *
     * @return array
     */
    public function getProductMapping(): array
    {
        return $this->configReader->read();
    }
}
