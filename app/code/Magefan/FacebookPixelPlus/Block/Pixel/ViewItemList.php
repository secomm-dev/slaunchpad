<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Block\Pixel;

use Magefan\FacebookPixel\Block\AbstractPixel;

class ViewItemList extends AbstractPixel
{
    public const ITEM_LIST = 'ViewCategory';

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $selector = '';

    /**
     * Set parameters
     *
     * @param array $parameters
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Get parameters
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get event name
     *
     * @return string
     */
    protected function getEventName(): string
    {
        return self::ITEM_LIST;
    }

    /**
     * Get track method
     *
     * @return string
     */
    protected function getTrackMethod(): string
    {
        return "trackCustom";
    }

    /**
     * Set selector
     *
     * @param string $selector
     */
    public function setSelector(string $selector)
    {
        $this->selector = $selector;
    }

    /**
     * Get selector
     *
     * @return array|string
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return string
     */
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        if ($html && $this->getSelector()) {
            $script = '
                document.addEventListener("DOMContentLoaded", function () {
                    var element = document.querySelector(".' . $this->getSelector() . '");
                    if (!element) return;

                    var observer = new IntersectionObserver(function(entries) {
                        if (entries[0].isIntersecting) {
                            ' . $this->stripTags($html) . '
                            observer.disconnect();
                        }
                    });
                    observer.observe(element);
                });
            ';
            return $this->mfSecureRenderer->renderTag('script', ['style' => 'display:none'], $script, false);
        }
        return $html;
    }
}
