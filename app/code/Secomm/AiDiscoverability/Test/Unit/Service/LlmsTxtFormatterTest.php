<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\LlmsTxtFormatter;

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

    public function testDeterministicSectionOrderAndFormat(): void
    {
        $sections = [
            'Priority Pages' => [['label' => 'Home', 'url' => 'https://shop.test']],
            'Collections' => [['label' => 'Dresses', 'url' => 'https://shop.test/dresses']],
        ];

        $expected = "# Fashion Shop\n"
            . "> Nice clothes\n"
            . "\nLocale: vi_VN\n"
            . "\n## Priority Pages\n- [Home]: https://shop.test\n"
            . "\n## Collections\n- [Dresses]: https://shop.test/dresses\n";

        $this->assertSame(
            $expected,
            $this->formatter->format('Fashion Shop', 'Nice clothes', 'vi_VN', $sections)
        );
    }

    public function testIsByteDeterministicForSameInput(): void
    {
        $sections = ['Pages' => [['label' => 'About', 'url' => 'https://shop.test/about']]];

        $a = $this->formatter->format('S', 'sum', 'vi_VN', $sections);
        $b = $this->formatter->format('S', 'sum', 'vi_VN', $sections);

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

    public function testSkipsEmptySectionsAndEmptyLocale(): void
    {
        $body = $this->formatter->format('S', '', '', ['Pages' => []]);

        $this->assertSame("# S\n", $body);
    }

    public function testUtf8Output(): void
    {
        $body = $this->formatter->format('Thời trang', 'Thời trang Việt Nam', 'vi_VN', []);

        $this->assertSame("# Thời trang\n> Thời trang Việt Nam\n\nLocale: vi_VN\n", $body);
    }
}
