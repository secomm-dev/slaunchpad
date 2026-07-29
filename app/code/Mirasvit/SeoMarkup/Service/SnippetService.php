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

namespace Mirasvit\SeoMarkup\Service;

use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;

class SnippetService
{
    private $templateEngineService;

    public function __construct(
        TemplateEngineServiceInterface $templateEngineService
    ) {
        $this->templateEngineService = $templateEngineService;
    }

    public function renderSnippetVariables(array $snippet): array
    {
        foreach ($snippet as $key => $value) {
            if (is_array($value)) {
                $snippet[$key] = $this->renderSnippetVariables($value);
            } elseif (is_string($value) && (strpos($value, '[') !== false || strpos($value, '{') !== false)) {
                $snippet[$key] = $this->templateEngineService->render($value);
            }
        }

        return $snippet;
    }

    public function extendRichSnippet(array $data, array $update, bool $isOverrideEnabled): array
    {
        foreach ($update as $key => $value) {
            if (is_array($value)) {
                $newData = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];

                $data[$key] = $this->extendRichSnippet($newData, $value, $isOverrideEnabled);
            } elseif ($isOverrideEnabled || !isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
