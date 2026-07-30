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

namespace Mirasvit\SeoAutolink\Block\Adminhtml\Import;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget;

class Edit extends Widget
{
    /**
     * @var string
     */
    protected $_template = 'Mirasvit_SeoAutolink::autolink/import_export.phtml';

    public function __construct(
        Context $context,
        array   $data = []
    ) {
        parent::__construct($context, $data);

        $this->setUseContainer(true);
    }

    public function getDownloadUrl(): string
    {
        return $this->getUrl('*/*/download', ['file' => 'seo_autolink_example']);
    }

    public function getBackUrl()
    {
        return $this->getUrl('*/link/');
    }
}
