<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\LlmsTxtFormatter;

/**
 * Covers the llms.txt v2 conformance output (SPEC-TASK-0X552E §12.1).
 */
class LlmsTxtFormatterTest extends TestCase
{
    /**
     * @var LlmsTxtFormatter
     */
    private $formatter;

    protected function setUp(): void
    {
        $this->formatter = new LlmsTxtFormatter();
    }

    public function testMarkdownLinkSyntaxWithLocaleAndCurrency(): void
    {
        $sections = [
            'Priority Pages' => [['label' => 'Home', 'url' => 'https://shop.test']],
            'Collections' => [['label' => 'Dresses', 'url' => 'https://shop.test/dresses']],
        ];

        $expected = "# Fashion Shop\n"
            . "> Nice clothes\n"
            . "\nLocale: vi_VN\nCurrency: VND\n"
            . "\n## Priority Pages\n- [Home](https://shop.test)\n"
            . "\n## Collections\n- [Dresses](https://shop.test/dresses)\n";

        $this->assertSame(
            $expected,
            $this->formatter->format('Fashion Shop', 'Nice clothes', 'vi_VN', $sections, 'VND')
        );
    }

    public function testOptionalDescriptionSuffix(): void
    {
        $sections = [
            'Pages' => [
                ['label' => 'About', 'url' => 'https://shop.test/about', 'description' => 'Our story'],
            ],
        ];

        $this->assertSame(
            "# S\n\nLocale: vi_VN\n\n## Pages\n- [About](https://shop.test/about): Our story\n",
            $this->formatter->format('S', '', 'vi_VN', $sections)
        );
    }

    public function testAbsentDescriptionProducesNoTrailingColon(): void
    {
        $sections = [
            'Pages' => [
                ['label' => 'About', 'url' => 'https://shop.test/about', 'description' => '  '],
            ],
        ];

        $this->assertSame(
            "# S\n\nLocale: vi_VN\n\n## Pages\n- [About](https://shop.test/about)\n",
            $this->formatter->format('S', '', 'vi_VN', $sections)
        );
    }

    public function testDescriptionSanitizationAndBound(): void
    {
        $sections = [
            'Pages' => [
                [
                    'label' => 'L',
                    'url' => 'https://shop.test/x',
                    'description' => "<b>Bold</b>\nstatement\ttab " . str_repeat('a', 300),
                ],
            ],
        ];

        $expectedTail = str_repeat('a', 240 - strlen('Bold statement tab '));
        $line = '- [L](https://shop.test/x): Bold statement tab ' . $expectedTail;

        $this->assertStringContainsString(
            $line,
            $this->formatter->format('S', '', 'vi_VN', $sections)
        );
    }

    public function testIsByteDeterministicForSameInput(): void
    {
        $sections = ['Pages' => [['label' => 'About', 'url' => 'https://shop.test/about', 'description' => 'd']]];

        $a = $this->formatter->format('S', 'sum', 'vi_VN', $sections, 'VND');
        $b = $this->formatter->format('S', 'sum', 'vi_VN', $sections, 'VND');

        $this->assertSame($a, $b);
    }

    public function testEscapesSquareBracketsAndStripsControlChars(): void
    {
        $sections = ['Pages' => [['label' => "Evil [x]\nlabel", 'url' => 'https://shop.test/x']]];

        $body = $this->formatter->format('S', "line1\nline2\x00", 'vi_VN', $sections);

        $this->assertStringNotContainsString('[x]', $body);
        $this->assertStringContainsString('\[x\]', $body);
        $this->assertStringNotContainsString("\nlabel", $body);
        $this->assertStringNotContainsString("\x00", $body);
        $this->assertStringContainsString('> line1 line2', $body);
    }

    public function testSkipsEmptySectionsAndEmptyMetadata(): void
    {
        $body = $this->formatter->format('S', '', '', ['Pages' => []]);

        $this->assertSame("# S\n", $body);
    }

    public function testCurrencyLineOmittedWhenEmpty(): void
    {
        $body = $this->formatter->format('S', 'sum', 'vi_VN', [], '');

        $this->assertSame("# S\n> sum\n\nLocale: vi_VN\n", $body);
        $this->assertStringNotContainsString('Currency:', $body);
    }

    public function testUtf8Output(): void
    {
        $body = $this->formatter->format('Thời trang', 'Thời trang Việt Nam', 'vi_VN', [], 'VND');

        $this->assertSame("# Thời trang\n> Thời trang Việt Nam\n\nLocale: vi_VN\nCurrency: VND\n", $body);
    }

    /**
     * SPEC-TASK-QYZMF1 §3.2: prose sections render sanitized, one line each.
     */
    public function testProseSectionRendersSanitizedLines(): void
    {
        $sections = ['Agent Guidance' => [['prose' => ['Line <b>one</b>', "line\ttwo"]]]];

        $this->assertSame(
            "# S\n\nLocale: vi_VN\n\n## Agent Guidance\nLine one\nline two\n",
            $this->formatter->format('S', '', 'vi_VN', $sections)
        );
    }

    /**
     * SPEC-TASK-QYZMF1 §3.4: commerce endpoints render as GET + Purpose
     * subsections, never as fabricated Markdown links.
     */
    public function testCommerceDetailEntriesRenderAsGetAndPurpose(): void
    {
        $sections = [
            'Machine-readable Commerce' => [
                ['detail' => [
                    'label' => 'Store Information',
                    'url' => 'https://example.com/ai/store?store=default',
                    'purpose' => 'Store metadata, locale, currency and supported public catalog context.',
                ]],
                ['detail' => [
                    'label' => 'Product Detail',
                    'url' => 'https://example.com/ai/products/{sku}?store=default',
                    'purpose' => 'Retrieve public product information for a known SKU.',
                    'plain' => true,
                ]],
            ],
        ];

        $body = $this->formatter->format('S', '', 'vi_VN', $sections);

        $this->assertSame(
            "# S\n\nLocale: vi_VN\n"
            . "\n## Machine-readable Commerce\n"
            . "\n### Store Information\n"
            . "GET https://example.com/ai/store?store=default\n"
            . "Purpose: Store metadata, locale, currency and supported public catalog context.\n"
            . "\n### Product Detail\n"
            . "GET https://example.com/ai/products/{sku}?store=default\n"
            . "Purpose: Retrieve public product information for a known SKU.\n",
            $body
        );
        // Route templates are never clickable Markdown links.
        $this->assertStringNotContainsString('[Product Detail](', $body);
    }

    /**
     * Product Search usage guidance renders after the Purpose line, and the
     * documented query syntax survives verbatim: literal filter[...] brackets
     * stay copy-pastable while markup/control characters are still stripped.
     */
    public function testUsageGuidanceRendersLiterallyAfterPurpose(): void
    {
        $sections = [
            'Machine-readable Commerce' => [
                ['detail' => [
                    'label' => 'Product Search',
                    'url' => 'https://example.com/ai/catalog/search?store=default',
                    'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
                    'usage' => [
                        '- q=KEYWORD: keyword search.',
                        '- filter[ATTRIBUTE]=OPTION_ID: allowlisted attributes only.',
                    ],
                ]],
            ],
        ];

        $body = $this->formatter->format('S', '', 'vi_VN', $sections);

        $this->assertStringContainsString(
            "GET https://example.com/ai/catalog/search?store=default\n"
            . "Purpose: Search public products using the bounded AI Commerce catalog facade.\n"
            . '- q=KEYWORD: keyword search.' . "\n"
            . '- filter[ATTRIBUTE]=OPTION_ID: allowlisted attributes only.',
            $body
        );
        // The documented syntax must NOT be bracket-escaped.
        $this->assertStringNotContainsString('\[', $body);
    }
}
