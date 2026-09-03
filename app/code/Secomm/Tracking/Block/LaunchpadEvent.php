<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Secomm\Tracking\Model\Consent\ConsentEvaluatorInterface;
use Secomm\Tracking\Model\Pipeline\BrowserEventProvider;

/**
 * FEAT-31X6N2 / TASK-E0NG8Z — pushes launchpad_event into window.dataLayer.
 *
 * Own block on head.additional (layout default.xml), sibling to Magefan's —
 * zero coupling to Magefan code (DEC D1: pixels ride the GTM container tags).
 * Renders directly (no phtml): the payload is one JSON push script.
 */
class LaunchpadEvent extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly BrowserEventProvider $eventProvider,
        private readonly ConsentEvaluatorInterface $consentEvaluator,
        private readonly SecureHtmlRenderer $secureHtmlRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLaunchpadEvent(): ?array
    {
        return $this->eventProvider->getEvent();
    }

    /**
     * Consent flags travel inside the event; GTM container tags decide via
     * Consent Mode (spec §8) — the push itself is not blocked.
     *
     * @return array{analytics: bool, marketing: bool}
     */
    public function getConsent(): array
    {
        return [
            ConsentEvaluatorInterface::SCOPE_ANALYTICS => $this->consentEvaluator->allows(
                ConsentEvaluatorInterface::SCOPE_ANALYTICS
            ),
            ConsentEvaluatorInterface::SCOPE_MARKETING => $this->consentEvaluator->allows(
                ConsentEvaluatorInterface::SCOPE_MARKETING
            ),
        ];
    }

    /**
     * JSON for the inline script.
     *
     * @return string
     */
    public function getEventJson(): string
    {
        $event = $this->getLaunchpadEvent();
        if ($event === null) {
            return '';
        }

        $event['consent'] = $this->getConsent();

        return (string)json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Renders the dataLayer push. All dynamic values are JSON-encoded server-side
     * and parsed client-side — nothing is interpolated into the JS source other
     * than the encoded string (XSS rule §7.2).
     */
    protected function _toHtml(): string
    {
        $json = $this->getEventJson();
        if ($json === '') {
            return '';
        }

        $script = <<<JS
(function () {
    var event = JSON.parse({$this->escapeJs($json)});

    // Browser matching params read from cookies at push time; absent stays absent.
    var params = {};
    var pairs = document.cookie ? document.cookie.split(';') : [];
    for (var i = 0; i < pairs.length; i++) {
        var parts = pairs[i].trim().split('=');
        if (parts.length < 2) { continue; }
        var name = parts[0];
        if (name === '_fbp') { params.fbp = decodeURIComponent(parts.slice(1).join('=')); }
        if (name === '_fbc') { params.fbc = decodeURIComponent(parts.slice(1).join('=')); }
        if (name === '_ttp') { params.ttp = decodeURIComponent(parts.slice(1).join('=')); }
        if (name === 'ttclid') { params.ttclid = decodeURIComponent(parts.slice(1).join('=')); }
    }
    event.user = params;

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ launchpad_event: event });
})();
JS;

        return $this->secureHtmlRenderer->renderTag('script', [], $script, false);
    }
}
