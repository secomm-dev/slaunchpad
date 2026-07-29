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


namespace Mirasvit\SeoContent\Controller\Adminhtml\Template;


use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;

class Suggest extends Action
{
    private $templateEngineService;

    private $serializer;

    public function __construct(
        TemplateEngineServiceInterface $templateEngineService,
        Context $context,
        Json $serializer
    ) {
        $this->templateEngineService = $templateEngineService;
        $this->serializer            = $serializer;

        parent::__construct($context);
    }

    public function execute()
    {
        $ruleType = $this->getRequest()->getParam('rule_type');

        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();

        $vars = (string)$this->serializer->serialize(['suggestion' => $this->getAllowedVariablesByRuleType($ruleType)]);

        $response->representJson($vars);

        return $response;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getAllowedVariablesByRuleType(string $ruleType): array
    {
        $scopes    = [];
        $variables = [];

        switch ($ruleType) {
            case TemplateInterface::RULE_TYPE_PRODUCT:
                $scopes = ['product', 'category', 'store'];

                break;
            case TemplateInterface::RULE_TYPE_CATEGORY:
                $scopes = ['category', 'pager', 'store'];

                break;
            case TemplateInterface::RULE_TYPE_NAVIGATION:
                $scopes = ['category', 'filter', 'pager', 'store'];

                break;
            case TemplateInterface::RULE_TYPE_PAGE:
                $scopes = ['cmsPage', 'store'];

                break;
            case TemplateInterface::RULE_TYPE_BLOG:
                $scopes = ['blog', 'store'];

                break;
            case TemplateInterface::RULE_TYPE_BRAND:
                $scopes = ['brand', 'pager', 'store'];

                break;
            default:
                $scopes = ['store'];
        }

        foreach ($this->templateEngineService->getData() as $scope => $dataObject) {
            if (!in_array($scope, $scopes)) {
                continue;
            }

            foreach ($dataObject->getVariables() as $var) {
                $variables[] = '[' . $scope . '_' . $var . ']';
            }
        }

        return $variables;
    }
}
