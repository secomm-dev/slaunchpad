<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Secomm\UiWidget\Api\TemplateResolverInterface;
use Secomm\UiWidget\Model\Content\TrustedHtmlRenderer;
use Secomm\UiWidget\Model\Parameter\Validator;

/**
 * Renders an allowlisted Secomm UI component.
 */
class SecommUi extends Template implements BlockInterface
{
    /** @var string */
    private string $instanceId;

    /**
     * @param Template\Context $context Template context.
     * @param TemplateResolverInterface $templateResolver Safe template resolver.
     * @param Validator $parameterValidator Persisted parameter validator.
     * @param Random $random Random value generator.
     * @param array $data Block data.
     */
    public function __construct(
        Template\Context $context,
        private readonly TemplateResolverInterface $templateResolver,
        private readonly Validator $parameterValidator,
        private readonly TrustedHtmlRenderer $trustedHtmlRenderer,
        Random $random,
        array $data = []
    ) {
        $this->instanceId = $random->getUniqueHash('secomm-ui-');
        parent::__construct($context, $data);
    }

    /**
     * Return a DOM-safe ID unique to this widget block instance.
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * Render an explicit trusted-rich-text field through Magento's CMS filter.
     *
     * @throws \Exception When Magento cannot filter the CMS content.
     */
    public function renderTrustedHtml(string $content): string
    {
        return $this->trustedHtmlRenderer->render($content);
    }

    /**
     * Resolve the registered component template before rendering.
     */
    protected function _toHtml(): string
    {
        $template = $this->templateResolver->resolve((string) $this->getData('component'));
        if ($template === null) {
            return '';
        }

        try {
            $componentData = $this->parameterValidator->validate(
                (string)$this->getData('component'),
                (int)$this->getData('schema_version'),
                (string)$this->getData('payload')
            );
        } catch (LocalizedException) {
            return '';
        }

        $this->setData('component_data', $componentData);
        $this->setTemplate($template);

        return parent::_toHtml();
    }
}
