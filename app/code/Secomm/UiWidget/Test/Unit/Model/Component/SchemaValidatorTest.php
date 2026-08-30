<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\Definition;
use Secomm\UiWidget\Model\Component\SchemaValidator;

class SchemaValidatorTest extends TestCase
{
    /** @var SchemaValidator */
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testNormalizesSupportedFieldsAndDropsUnknownInput(): void
    {
        $definition = $this->definition([
            ['name' => 'title', 'type' => 'text', 'required' => true, 'max_length' => 20],
            ['name' => 'enabled', 'type' => 'yesno'],
            ['name' => 'columns', 'type' => 'integer', 'min' => 1, 'max' => 4],
            ['name' => 'style', 'type' => 'select', 'options' => [['value' => 'light', 'label' => 'Light']]],
            ['name' => 'url', 'type' => 'url'],
        ]);

        self::assertSame(
            ['title' => 'Banner', 'enabled' => true, 'columns' => 3, 'style' => 'light', 'url' => '/sale'],
            $this->validator->validate($definition, [
                'title' => 'Banner', 'enabled' => '1', 'columns' => '3', 'style' => 'light',
                'url' => '/sale', 'template' => 'Vendor_Module::unsafe.phtml',
            ])
        );
    }

    public function testPreservesRepeaterOrder(): void
    {
        $definition = $this->definition([[
            'name' => 'items', 'type' => 'collection', 'max_items' => 3,
            'fields' => [['name' => 'label', 'type' => 'text', 'required' => true]],
        ]]);
        $data = ['items' => [['label' => 'Second'], ['label' => 'First']]];

        self::assertSame($data, $this->validator->validate($definition, $data));
    }

    public function testEnforcesRepeaterMinimumItems(): void
    {
        $definition = $this->definition([[
            'name' => 'items', 'type' => 'collection', 'min_items' => 1, 'max_items' => 3,
            'fields' => [['name' => 'label', 'type' => 'text', 'required' => true]],
        ]]);

        self::assertNull($this->validator->validate($definition, ['items' => []]));
        self::assertSame(
            ['items' => [['label' => 'First']]],
            $this->validator->validate($definition, ['items' => [['label' => 'First']]])
        );
    }

    public function testRejectsRequiredInvalidUnsafeAndOversizedValues(): void
    {
        self::assertNull($this->validator->validate(
            $this->definition([['name' => 'url', 'type' => 'url', 'required' => true]]),
            ['url' => 'javascript:alert(1)']
        ));
        self::assertNull($this->validator->validate(
            $this->definition([[
                'name' => 'items', 'type' => 'collection', 'max_items' => 1,
                'fields' => [['name' => 'label', 'type' => 'text']],
            ]]),
            ['items' => [['label' => 'A'], ['label' => 'B']]]
        ));
    }

    public function testRequiresBothFieldsInOptionalCompoundPair(): void
    {
        $definition = $this->definition([
            ['name' => 'label', 'type' => 'text', 'required_with' => ['url']],
            ['name' => 'url', 'type' => 'url', 'required_with' => ['label']],
        ]);

        self::assertSame([], $this->validator->validate($definition, []));
        self::assertSame(
            ['label' => 'Shop', 'url' => '/shop'],
            $this->validator->validate($definition, ['label' => 'Shop', 'url' => '/shop'])
        );
        self::assertNull($this->validator->validate($definition, ['url' => '/shop']));
        self::assertNull($this->validator->validate($definition, ['label' => 'Shop']));
    }

    public function testMediaImageUsesTheExistingSafeMediaContract(): void
    {
        $definition = $this->definition([[
            'name' => 'image', 'type' => 'media-image', 'required' => true, 'max_length' => 2048,
        ]]);

        self::assertSame(
            ['image' => '/media/wysiwyg/card.jpg'],
            $this->validator->validate($definition, ['image' => '/media/wysiwyg/card.jpg'])
        );
        self::assertNull($this->validator->validate($definition, ['image' => 'javascript:alert(1)']));
    }

    public function testVideoUrlMustMatchSelectedProvider(): void
    {
        $definition = $this->definition([
            [
                'name' => 'provider', 'type' => 'select',
                'options' => [['value' => 'youtube', 'label' => 'YouTube'], ['value' => 'vimeo', 'label' => 'Vimeo']],
            ],
            [
                'name' => 'video_url', 'type' => 'video-url', 'required' => true,
                'provider_field' => 'provider',
            ],
        ]);

        self::assertSame(
            ['provider' => 'youtube', 'video_url' => 'https://youtu.be/DZG4XUd3kRY'],
            $this->validator->validate($definition, [
                'provider' => 'youtube', 'video_url' => 'https://youtu.be/DZG4XUd3kRY',
            ])
        );
        self::assertSame(
            ['provider' => 'vimeo', 'video_url' => 'https://vimeo.com/1179947837'],
            $this->validator->validate($definition, [
                'provider' => 'vimeo', 'video_url' => 'https://vimeo.com/1179947837',
            ])
        );
        self::assertNull($this->validator->validate($definition, [
            'provider' => 'youtube', 'video_url' => 'https://vimeo.com/1179947837',
        ]));
        self::assertNull($this->validator->validate($definition, [
            'provider' => 'vimeo', 'video_url' => 'https://example.com/video/1179947837',
        ]));
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function definition(array $fields): Definition
    {
        return new Definition('test', 'Test', 'Secomm_UiWidget::components/test.phtml', fields: $fields);
    }
}
