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

namespace Mirasvit\Seo\Service\TemplateEngine\Data;

use Magento\Framework\Module\Manager as ModuleManager;
use Mirasvit\Seo\Api\Service\StateServiceInterface;

class LandingData extends AbstractData
{
    private $stateService;

    private $moduleManager;

    public function __construct(
        StateServiceInterface $stateService,
        ModuleManager         $moduleManager
    ) {
        $this->stateService  = $stateService;
        $this->moduleManager = $moduleManager;

        parent::__construct();
    }

    public function getTitle(): string
    {
        return (string)__('Landing Page Data');
    }

    public function getVariables(): array
    {
        if (!$this->moduleManager->isEnabled('Mirasvit_LandingPage')) {
            return [];
        }

        return [
            'name',
            'page_title',
            'meta_title',
            'meta_description',
            'url_key',
        ];
    }

    public function getValue(string $attribute, array $additionalData = []): ?string
    {
        $landingPage = $this->stateService->getLandingPage();

        if (!$landingPage) {
            return null;
        }

        switch ($attribute) {
            case 'name':
                return (string)$landingPage->getName();

            case 'page_title':
                return (string)$landingPage->getPageTitle();

            case 'meta_title':
                return (string)$landingPage->getMetaTitle();

            case 'meta_description':
                return (string)$landingPage->getMetaDescription();

            case 'url_key':
                return (string)$landingPage->getUrlKey();
        }

        return null;
    }
}
