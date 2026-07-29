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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Core\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\RequestInterface;
use Mirasvit\Core\Service\FeatureService;

class FeatureRequest extends Template
{
    private $request;

    public function __construct(
        FeatureService   $featureService,
        Template\Context $context,
        RequestInterface $request,
        array            $data = []
    ) {
        $this->featureService = $featureService;
        $this->request        = $request;

        parent::__construct($context, $data);
    }

    public function isMirasvit()
    {
        return strpos((string)$this->request->getControllerModule(), 'Mirasvit') === 0;
    }

    public function getRequestUrl()
    {
        return $this->featureService->getImprovementSuggestionUrl($this->request->getControllerModule());
    }
}