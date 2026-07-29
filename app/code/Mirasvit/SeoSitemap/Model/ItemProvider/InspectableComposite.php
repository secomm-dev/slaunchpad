<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoSitemap\Model\ItemProvider;

use Magento\Sitemap\Model\ItemProvider\Composite;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;

class InspectableComposite extends Composite
{
    /**
     * @var ItemProviderInterface[]
     */
    private $itemProviders;

    public function __construct(array $itemProviders = [])
    {
        parent::__construct($itemProviders);
        $this->itemProviders = $itemProviders;
    }

    /**
     * @return ItemProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->itemProviders;
    }
}
