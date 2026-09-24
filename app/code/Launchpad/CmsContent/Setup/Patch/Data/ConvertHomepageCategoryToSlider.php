<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\UiWidget\Model\Parameter\Codec;
use Secomm\UiWidget\Model\Parameter\Validator;

/**
 * SLP-267 (TASK-HARDAR): replaces the homepage Category section built from
 * PageBuilder columns (`.lp-tile`, heading + column-group) with ONE PageBuilder
 * Text element holding a `Secomm UI` `categories_a` widget, so admins manage
 * the category slider as widget items instead of grid columns.
 *
 * The widget sits in a PageBuilder TEXT element (see buildElement()). Content
 * from the first SLP-267 iteration (widget inside an HTML Code element) is
 * moved into a Text element as-is (items kept).
 *
 * Idempotent: a page without `.lp-tile` columns / HTML-element widget (fresh
 * seed from the updated template, or content already edited by an admin) is
 * left untouched.
 */
class ConvertHomepageCategoryToSlider implements DataPatchInterface
{
    private const PAGE_IDENTIFIER = 'home';
    private const TILE_MARKER = 'lp-tile ';
    private const GROUP_OPEN = '<div class="pagebuilder-column-group"';
    private const TEXT_OPEN = '<div data-content-type="text" data-appearance="default" data-element="main">';
    private const HTML_ELEMENT_PATTERN = '#<div data-content-type="html" data-appearance="default" data-element="main">'
        . '(\{\{widget type="Secomm\\\\UiWidget\\\\Block\\\\Widget\\\\SecommUi"'
        . ' component="categories_a"[^}]*\}\})</div>#';
    private const HEADING_PATTERN = '#<h2 [^>]*data-content-type="heading"[^>]*>\s*Category\s*</h2>\s*$#';
    private const TILES = [
        ['Chair', 'cat-chair', '/living-room/living-room-seating.html'],
        ['Table', 'cat-table', '/living-room/living-room-tables.html'],
        ['Cabinet', 'cat-cabinet', '/living-room/living-room-storage.html'],
        ['Lighting', 'cat-lighting', '/living-room/living-room-lighting.html'],
        ['Bed', 'cat-bed', '/bedroom/beds.html'],
        ['Nightstand', 'cat-nightstand', '/bedroom/nightstands.html'],
    ];

    /**
     * @param CollectionFactory $collectionFactory
     * @param PageRepositoryInterface $pageRepository
     * @param Codec $codec
     * @param Validator $validator
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly Codec $codec,
        private readonly Validator $validator
    ) {
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            SeedHomepageContent::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function apply(): ConvertHomepageCategoryToSlider
    {
        /** @var \Magento\Cms\Model\Page $page */
        $page = $this->collectionFactory->create()
            ->addFieldToFilter('identifier', self::PAGE_IDENTIFIER)
            ->getFirstItem();
        if (!$page->getId()) {
            return $this;
        }

        $content = $this->convert((string)$page->getContent());
        if ($content !== null) {
            $page->setContent($content);
            $this->pageRepository->save($page);
        }

        return $this;
    }

    /**
     * Replace the Category heading + column-group with the widget element.
     *
     * @param string $content Page content.
     * @return string|null Converted content, or null when there is nothing to convert.
     */
    public function convert(string $content): ?string
    {
        $moved = preg_replace(self::HTML_ELEMENT_PATTERN, self::TEXT_OPEN . '$1</div>', $content, 1, $count);
        if ($count > 0 && $moved !== null) {
            return $moved;
        }

        $tile = strpos($content, self::TILE_MARKER);
        $start = $tile === false ? false : strrpos(substr($content, 0, $tile), self::GROUP_OPEN);
        if ($start === false) {
            return null;
        }
        $end = $this->findElementEnd($content, $start);
        if ($end === null) {
            return null;
        }

        $before = substr($content, 0, $start);
        $withoutHeading = preg_replace(self::HEADING_PATTERN, '', $before);

        return $this->pruneOrphanStyles(
            ($withoutHeading ?? $before) . $this->buildElement() . substr($content, $end)
        );
    }

    /**
     * Drop `<style>` rules whose `[data-pb-style=ID]` element no longer exists.
     *
     * PageBuilder stage (stage-builder.js convertToInlineStyles) calls
     * `document.querySelector(selector).setAttribute(...)` for every rule
     * WITHOUT a null check — one orphan rule throws and the Admin stage never
     * renders (SLP-267 regression: removed columns left 26 orphan rules).
     *
     * @param string $content Page content.
     */
    public function pruneOrphanStyles(string $content): string
    {
        return (string)preg_replace_callback('#<style>(.*?)</style>#s', function (array $style) use ($content) {
            $css = preg_replace_callback('#([^{}@]+)\{([^{}]*)\}#', function (array $rule) use ($content) {
                $selectors = array_filter(explode(',', $rule[1]), function (string $selector) use ($content) {
                    return !preg_match('#\[data-pb-style=([A-Z0-9]+)\]#', $selector, $id)
                        || str_contains($content, 'data-pb-style="' . $id[1] . '"');
                });

                return $selectors ? implode(',', $selectors) . '{' . $rule[2] . '}' : '';
            }, $style[1]);
            $css = preg_replace('#@media[^{]*\{\s*\}#', '', (string)$css);

            return '<style>' . $css . '</style>';
        }, $content);
    }

    /**
     * PageBuilder Text element (TinyMCE) holding the `categories_a` widget directive.
     *
     * Text, not HTML Code: in TinyMCE the directive becomes a widget placeholder
     * and double-clicking it reopens the Secomm UI form PRE-FILLED from the
     * payload (widget.js initOptionValues() only pre-fills inside WYSIWYG — in an
     * HTML Code textarea "Insert Widget" always starts empty).
     */
    public function buildElement(): string
    {
        return self::TEXT_OPEN . $this->buildDirective() . '</div>';
    }

    /**
     * `categories_a` widget directive with the seeded Category items.
     */
    private function buildDirective(): string
    {
        return '{{widget type="Secomm\UiWidget\Block\Widget\SecommUi" component="categories_a" schema_version="1"'
            . ' payload="' . $this->buildPayload() . '" type_name="Secomm UI"}}';
    }

    /**
     * Encode + validate the widget payload (schema v1).
     *
     * @throws \Magento\Framework\Exception\LocalizedException When the payload fails schema validation.
     */
    private function buildPayload(): string
    {
        $items = [];
        foreach (self::TILES as [$label, $image, $url]) {
            $items[] = [
                'label' => $label,
                'image' => 'wysiwyg/homepage/' . $image . '.webp',
                'image_alt' => $label,
                'url' => $url,
                'loading' => 'lazy',
                'open_in_new' => false,
            ];
        }
        $payload = $this->codec->encode([
            'heading' => 'Category',
            'browse_label' => '',
            'browse_url' => '',
            'browse_open_in_new' => false,
            'mobile_slider' => true,
            'items' => $items,
        ]);
        $this->validator->validate('categories_a', 1, $payload);

        return $payload;
    }

    /**
     * Offset just after the `</div>` closing the `<div` opened at $start.
     *
     * @param string $content Page content.
     * @param int $start Offset of the opening `<div`.
     */
    private function findElementEnd(string $content, int $start): ?int
    {
        $depth = 0;
        preg_match_all('#<div\b|</div>#', $content, $matches, PREG_OFFSET_CAPTURE, $start);
        foreach ($matches[0] as [$tag, $offset]) {
            $depth += $tag === '</div>' ? -1 : 1;
            if ($depth === 0) {
                return $offset + strlen('</div>');
            }
        }

        return null;
    }
}
