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

namespace Mirasvit\SeoToolbar\Controller\Toolbar;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Mirasvit\SeoToolbar\Model\Config;
use Magento\Framework\Controller\Result\JsonFactory;

class Ajax extends Action
{
    protected $resultJsonFactory;

    private $config;

    public function __construct(
        JsonFactory $resultJsonFactory,
        Config      $config,
        Context     $context
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config            = $config;
        parent::__construct($context);
    }

    public function execute()
    {
        $result           = $this->resultJsonFactory->create();
        $isToolBarAllowed = $this->config->isToolbarAllowed();

        return $result->setData(['isToolBarAllowed' => $isToolBarAllowed]);
    }
}
