<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Setup\Patch\Data;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Secomm\UiWidget\Model\Parameter\Codec;
use Secomm\UiWidget\Model\Parameter\Validator;

/**
 * SLP-267 (TASK-HARDAR): replaces the homepage Category section built from
 * PageBuilder columns (`.lp-tile`, heading + column-group) with ONE PageBuilder
 * Text element holding a `Secomm UI` `categories_a` widget, so admins manage
 * the category slider as widget items instead of grid columns.
 *
 * Content-preserving, per store view: EVERY `home` page is converted on its
 * own (staging keeps one translated page per store view), and the widget is
 * built from that page's own section — heading text, and per tile the label,
 * image, alt text, URL and new-tab flag, in the same order and count. Only the
 * markup changes; nothing is replaced with seed values.
 *
 * The widget sits in a PageBuilder TEXT element (see buildElement()). Content
 * from the first SLP-267 iteration (widget inside an HTML Code element) is
 * moved into a Text element as-is (items kept).
 *
 * Idempotent: a page without `.lp-tile` columns / HTML-element widget (fresh
 * seed from the updated template, or content already converted) is left
 * untouched. A page whose tiles cannot form a valid widget (missing image/URL,
 * more than 12 tiles) is also left untouched and logged — never half-converted.
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
    /** Section heading = the PB heading element right before the column group. */
    private const HEADING_PATTERN = '#<h([1-6]) [^>]*data-content-type="heading"[^>]*>(.*?)</h\1>\s*$#s';
    private const TILE_OPEN_PATTERN = '#<div class="[^"]*\blp-tile\b[^"]*"#';
    private const DEFAULT_HEADING = 'Category';

    /**
     * @param CollectionFactory $collectionFactory
     * @param PageRepositoryInterface $pageRepository
     * @param Codec $codec
     * @param Validator $validator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly Codec $codec,
        private readonly Validator $validator,
        private readonly LoggerInterface $logger
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
        $pageIds = $this->collectionFactory->create()
            ->addFieldToFilter('identifier', self::PAGE_IDENTIFIER)
            ->getAllIds();

        foreach ($pageIds as $pageId) {
            // Repository load: store assignment is read back, so save() keeps it.
            $page = $this->pageRepository->getById((int)$pageId);
            try {
                $content = $this->convert((string)$page->getContent());
            } catch (LocalizedException $e) {
                $this->logger->warning(sprintf(
                    'SLP-267: homepage (page_id %d) Category left unchanged: %s',
                    $pageId,
                    $e->getMessage()
                ));
                continue;
            }
            if ($content !== null) {
                $page->setContent($content);
                $this->pageRepository->save($page);
            }
        }

        return $this;
    }

    /**
     * Replace the Category heading + column-group with the widget element.
     *
     * @param string $content Page content.
     * @return string|null Converted content, or null when there is nothing to convert.
     * @throws LocalizedException When the tiles cannot form a valid widget payload.
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
        $heading = self::DEFAULT_HEADING;
        if (preg_match(self::HEADING_PATTERN, $before, $match, PREG_OFFSET_CAPTURE)) {
            $heading = $this->text($match[2][0]) ?: self::DEFAULT_HEADING;
            $before = substr($before, 0, $match[0][1]);
        }
        $items = $this->extractTiles(substr($content, $start, $end - $start));

        return $this->pruneOrphanStyles(
            $before . $this->buildElement($heading, $items) . substr($content, $end)
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
     *
     * @param string $heading Section heading.
     * @param array<int, array<string, mixed>> $items Widget items.
     * @throws LocalizedException When the payload fails schema validation.
     */
    public function buildElement(string $heading, array $items): string
    {
        return self::TEXT_OPEN . '{{widget type="Secomm\UiWidget\Block\Widget\SecommUi" component="categories_a"'
            . ' schema_version="1" payload="' . $this->buildPayload($heading, $items) . '" type_name="Secomm UI"}}'
            . '</div>';
    }

    /**
     * Widget items from the `.lp-tile` columns, in page order.
     *
     * Label = the tile's heading (fallback: image alt); image = the desktop
     * image's `{{media url=…}}` path; URL / new tab = the tile link.
     *
     * @param string $group Column-group markup.
     * @return array<int, array<string, mixed>>
     */
    private function extractTiles(string $group): array
    {
        $items = [];
        preg_match_all(self::TILE_OPEN_PATTERN, $group, $opens, PREG_OFFSET_CAPTURE);
        foreach ($opens[0] as [, $offset]) {
            $end = $this->findElementEnd($group, $offset);
            $tile = substr($group, $offset, ($end ?? strlen($group)) - $offset);

            if (!preg_match('#<img\b[^>]*data-element="desktop_image"[^>]*>#', $tile, $img)) {
                preg_match('#<img\b[^>]*>#', $tile, $img);
            }
            $imgTag = $img[0] ?? '';
            $alt = $this->text($this->attribute($imgTag, 'alt'));
            preg_match('#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#s', $tile, $label);
            preg_match('#<a\b[^>]*>#', $tile, $link);
            $linkTag = $link[0] ?? '';

            $labelText = $this->text($label[1] ?? '') ?: $alt;
            $items[] = [
                'label' => $labelText,
                'image' => $this->mediaPath($this->attribute($imgTag, 'src')),
                'image_alt' => $alt ?: $labelText,
                'url' => html_entity_decode($this->attribute($linkTag, 'href'), ENT_QUOTES | ENT_HTML5),
                'loading' => 'lazy',
                'open_in_new' => $this->attribute($linkTag, 'target') === '_blank',
            ];
        }

        return $items;
    }

    /**
     * Encode + validate the widget payload (schema v1).
     *
     * @param string $heading Section heading.
     * @param array<int, array<string, mixed>> $items Widget items.
     * @throws LocalizedException When the payload fails schema validation.
     */
    private function buildPayload(string $heading, array $items): string
    {
        $payload = $this->codec->encode([
            'heading' => $heading,
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
     * Media-relative path from `{{media url=…}}` (plain src kept as-is).
     *
     * @param string $src Image src attribute.
     */
    private function mediaPath(string $src): string
    {
        $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5);
        if (preg_match('#\{\{media url=([^}]*)\}\}#', $src, $media)) {
            return trim($media[1], " \"'");
        }

        return $src;
    }

    /**
     * @param string $tag HTML start tag.
     * @param string $name Attribute name.
     */
    private function attribute(string $tag, string $name): string
    {
        return preg_match('#\s' . $name . '="([^"]*)"#', $tag, $value) ? $value[1] : '';
    }

    /**
     * Plain text of an HTML fragment (tags stripped, entities decoded).
     *
     * @param string $html HTML fragment.
     */
    private function text(string $html): string
    {
        return trim(preg_replace('#\s+#u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
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
