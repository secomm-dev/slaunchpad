<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Plugin\Mirasvit\SeoAutolink;

use Mirasvit\SeoAutolink\Service\TextProcessorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\UiWidget\Plugin\Mirasvit\SeoAutolink\PreserveSecommUiContent;

class PreserveSecommUiContentTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var TextProcessorService|MockObject
     */
    private $subject;

    /** @var PreserveSecommUiContent */
    private PreserveSecommUiContent $plugin;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = $this->createMock(TextProcessorService::class);
        $this->plugin = new PreserveSecommUiContent($this->logger);
    }

    /**
     * @dataProvider emptyResultProvider
     */
    public function testRestoresSecommUiSourceWhenAutolinkReturnsEmpty(?string $result): void
    {
        $source = '<section data-secomm-ui-component="card_b">Content</section>';
        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'Mirasvit SEO Autolink returned empty Secomm UI content; original HTML was restored.',
                ['source_length' => strlen($source)]
            );

        self::assertSame($source, $this->plugin->afterAddLinks($this->subject, $result, $source));
    }

    public static function emptyResultProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => [" \n\t"],
        ];
    }

    /**
     * @dataProvider unchangedResultProvider
     */
    public function testDoesNotInterfereOutsideFallbackContract(?string $result, ?string $source): void
    {
        $this->logger->expects(self::never())->method('warning');

        self::assertSame($result, $this->plugin->afterAddLinks($this->subject, $result, $source));
    }

    public static function unchangedResultProvider(): array
    {
        return [
            'successful processing' => [
                '<section data-secomm-ui-component="card_b">Linked content</section>',
                '<section data-secomm-ui-component="card_b">Content</section>',
            ],
            'non Secomm content' => ['', '<p>CMS content</p>'],
            'empty source' => ['', ''],
            'null source' => [null, null],
        ];
    }
}
