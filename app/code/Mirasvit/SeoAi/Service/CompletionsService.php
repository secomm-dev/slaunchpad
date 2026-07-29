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

namespace Mirasvit\SeoAi\Service;

use Mirasvit\SeoAi\Model\ConfigProvider;

class CompletionsService
{
    private $configProvider;

    private $aiCompletionService;

    public function __construct(
        ConfigProvider      $configProvider,
        AiCompletionService $aiCompletionService
    ) {
        $this->configProvider      = $configProvider;
        $this->aiCompletionService = $aiCompletionService;
    }

    public function isAvailable(): bool
    {
        if (!$this->configProvider->isHelperEnabled()) {
            return false;
        }

        return $this->aiCompletionService->isAvailable();
    }

    public function answer(string $text): ?string
    {
        if (!$this->configProvider->isHelperEnabled()) {
            return null;
        }

        $answer = $this->aiCompletionService->getCompletion($text);

        if ($answer === null) {
            return '';
        }

        $answer = trim($answer);
        $answer = trim($answer, "\n");
        $answer = trim($answer, "\r");
        $answer = trim($answer, "\"");

        return $answer;
    }
}
