<?php

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin;

use Launchpad\SnowdogMenu\ViewModel\Config;
use Snowdog\Menu\Model\TemplateResolver;

/**
 * Keep Launchpad renderer templates independent from the configured menu ID.
 */
class TemplateResolverPlugin
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @return array{0: mixed, 1: string, 2: string, 3: int|null}
     */
    public function beforeGetMenuTemplate(
        TemplateResolver $subject,
        mixed $block,
        string $menuId,
        string $template,
        ?int $nodeId = null
    ): array {
        if (in_array($menuId, $this->getConfiguredIdentifiers(), true)) {
            $menuId = Config::DEFAULT_MENU_IDENTIFIER;
        }

        return [$block, $menuId, $template, $nodeId];
    }

    /**
     * @return string[]
     */
    private function getConfiguredIdentifiers(): array
    {
        return array_unique([
            $this->config->getDesktopMenuIdentifier(),
            $this->config->getMobileMenuIdentifier(),
        ]);
    }
}
