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

namespace Mirasvit\SeoMarkup\Ui\Component;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\AbstractComponent;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;

class SnippetVariablesComponent extends AbstractComponent
{
    private $templateEngineService;

    public function __construct(
        TemplateEngineServiceInterface $templateEngineService,
        ContextInterface               $context,
        array                          $components = [],
        array                          $data = []
    ) {
        $this->templateEngineService = $templateEngineService;

        $data['config']['component'] = 'Mirasvit_SeoMarkup/js/component/snippet-variables';

        parent::__construct($context, $components, $data);
    }

    public function getComponentName(): string
    {
        return 'snippet_variables';
    }

    public function prepare(): void
    {
        parent::prepare();

        $config        = $this->getData('config');
        $allowedScopes = ['product', 'category', 'store'];

        foreach ($this->templateEngineService->getData() as $scope => $dataObject) {
            if (!in_array($scope, $allowedScopes)) {
                continue;
            }

            $variables = $dataObject->getVariables();

            if (empty($variables)) {
                continue;
            }

            $scopeData = [
                'label' => (string)$dataObject->getTitle(),
                'vars'  => [],
            ];

            foreach ($variables as $var) {
                $scopeData['vars'][] = $scope . '_' . $var;
            }

            $config['scopeData'][] = $scopeData;
        }

        $this->setData('config', $config);
    }
}
