<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\TiktokProductMapping;

use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\ValidationStateInterface;

/**
 * Product Mapping Reader class
 */
class Reader extends Filesystem
{
    /**
     * List of id attributes for merge
     *
     * @var string[]
     */
    protected $_idAttributes = ['/config/product_headers/header' => 'name'];

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\Config\FileResolverInterface $fileResolver
     * @param \Tiktok\Tiktok\Model\Config\TiktokProductMapping\Converter $converter
     * @param \Tiktok\Tiktok\Model\Config\TiktokProductMapping\SchemaLocator $schemaLocator
     * @param \Magento\Framework\Config\ValidationStateInterface $validationState
     */
    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocator $schemaLocator,
        ValidationStateInterface $validationState
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            'tiktok_product_mapping.xml'
        );
    }
}
