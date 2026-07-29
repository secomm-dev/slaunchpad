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

namespace Mirasvit\SeoContent\Plugin\Variable;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Variable\Model\Variable\Data as Subject;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;

class DataPlugin
{
    private const APPLICABLE_ROUTES
        = [
            'seo_content/rewrite/edit',
            'seo_content/template/edit',
        ];

    private $redirect;

    private $templateEngineService;

    public function __construct(
        RedirectInterface              $redirect,
        TemplateEngineServiceInterface $templateEngineService
    ) {
        $this->redirect              = $redirect;
        $this->templateEngineService = $templateEngineService;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCustomVariables(Subject $subject, array $result): array
    {
        foreach (self::APPLICABLE_ROUTES as $route) {
            if (false !== strpos($this->redirect->getRefererUrl(), $route)) {
                return $this->appendSeoVariables($result);
            }
        }

        return $result;
    }

    private function appendSeoVariables(array $variables): array
    {
        foreach ($this->templateEngineService->getData() as $scope => $dataObject) {
            if ('store' === $scope) {
                continue;
            }

            foreach ($dataObject->getVariables() as $var) {
                $variables[] = [
                    'code'          => $scope . '_' . $var,
                    'variable_name' =>
                        str_replace(' Data', '', $dataObject->getTitle())
                        . ' / ' . ucwords(str_replace('_', ' ', $var)),
                    'variable_type' => 'adv_seo_' . $scope,
                ];
            }
        }

        return $variables;
    }
}
