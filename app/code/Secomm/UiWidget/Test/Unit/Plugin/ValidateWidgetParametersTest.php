<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Plugin;

use Magento\Widget\Model\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Block\Widget\SecommUi;
use Secomm\UiWidget\Model\Parameter\Validator;
use Secomm\UiWidget\Plugin\ValidateWidgetParameters;

class ValidateWidgetParametersTest extends TestCase
{
    /**
     * @var Validator|MockObject
     */
    private $validator;

    /** @var ValidateWidgetParameters */
    private ValidateWidgetParameters $plugin;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(Validator::class);
        $this->plugin = new ValidateWidgetParameters($this->validator);
    }

    /**
     * BuildWidget posts `as_is` only outside WYSIWYG: TinyMCE inserts pass null (SLP-267).
     *
     * @dataProvider asIsProvider
     */
    public function testPassesAsIsThroughUnchanged(mixed $asIs): void
    {
        $params = ['component' => 'categories_a', 'schema_version' => '1', 'payload' => 'abc'];
        $this->validator->expects(self::once())
            ->method('validate')
            ->with('categories_a', 1, 'abc')
            ->willReturn([]);

        $result = $this->plugin->beforeGetWidgetDeclaration(
            $this->createMock(Widget::class),
            SecommUi::class,
            $params,
            $asIs
        );

        self::assertSame([SecommUi::class, $params, $asIs], $result);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function asIsProvider(): array
    {
        return [
            'wysiwyg (not posted)' => [null],
            'textarea' => ['1'],
            'default' => [true],
        ];
    }

    public function testSkipsOtherWidgetTypes(): void
    {
        $this->validator->expects(self::never())->method('validate');

        $result = $this->plugin->beforeGetWidgetDeclaration(
            $this->createMock(Widget::class),
            'Magento\Cms\Block\Widget\Block',
            ['block_id' => '1'],
            null
        );

        self::assertSame(['Magento\Cms\Block\Widget\Block', ['block_id' => '1'], null], $result);
    }
}
