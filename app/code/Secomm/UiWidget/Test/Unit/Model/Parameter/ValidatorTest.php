<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Parameter;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\Definition;
use Secomm\UiWidget\Model\Component\Registry;
use Secomm\UiWidget\Model\Component\SchemaValidator;
use Secomm\UiWidget\Model\Parameter\Codec;
use Secomm\UiWidget\Model\Parameter\Validator;

class ValidatorTest extends TestCase
{
    /** @var Codec */
    private Codec $codec;

    /** @var Validator */
    private Validator $validator;

    protected function setUp(): void
    {
        $definition = new Definition(
            'banner',
            'Banner',
            'Secomm_UiWidget::components/banner.phtml',
            fields: [['name' => 'title', 'type' => 'text', 'required' => true]]
        );
        $this->codec = new Codec();
        $this->validator = new Validator(new Registry([$definition]), $this->codec, new SchemaValidator());
    }

    public function testReturnsNormalizedRegisteredComponentData(): void
    {
        self::assertSame(
            ['title' => 'Hello'],
            $this->validator->validate('banner', 1, $this->codec->encode(['title' => 'Hello', 'unknown' => 'drop']))
        );
    }

    /**
     * @dataProvider invalidParametersProvider
     */
    public function testRejectsInvalidParameters(string $component, int $version, string $payload): void
    {
        $this->expectException(LocalizedException::class);
        $this->validator->validate($component, $version, $payload);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function invalidParametersProvider(): array
    {
        $codec = new Codec();

        return [
            'unknown component' => ['unknown', 1, $codec->encode(['title' => 'Hello'])],
            'unsupported schema' => ['banner', 2, $codec->encode(['title' => 'Hello'])],
            'malformed payload' => ['banner', 1, 'invalid+payload'],
            'missing required field' => ['banner', 1, $codec->encode([])],
        ];
    }
}
