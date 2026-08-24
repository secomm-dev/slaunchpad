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

        $expectedTail = str_repeat('a', 200 - strlen('Bold statement tab '));
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
}
