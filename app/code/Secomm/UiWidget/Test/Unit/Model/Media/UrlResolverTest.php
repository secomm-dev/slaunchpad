<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Media;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Media\UrlResolver;

class UrlResolverTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private UrlResolver $resolver;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_MEDIA)
            ->willReturn('https://store.example/media/');
        $this->storeManager->method('getStore')->willReturn($store);
        $this->resolver = new UrlResolver($this->storeManager);
    }

    /**
     * @dataProvider portableMediaProvider
     */
    public function testResolvesPortableMediaValues(string $value, string $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($value));
    }

    public static function portableMediaProvider(): array
    {
        return [
            'root media URL' => [
                '/media/.renditions/wysiwyg/banner.jpg',
                'https://store.example/media/.renditions/wysiwyg/banner.jpg',
            ],
            'relative media URL' => [
                'wysiwyg/banner.jpg',
                'https://store.example/media/wysiwyg/banner.jpg',
            ],
            'media-prefixed URL' => [
                'media/wysiwyg/banner.jpg',
                'https://store.example/media/wysiwyg/banner.jpg',
            ],
        ];
    }

    public function testPreservesAbsoluteAndNonMediaRootUrls(): void
    {
        self::assertSame('https://cdn.example/banner.jpg', $this->resolver->resolve('https://cdn.example/banner.jpg'));
        self::assertSame('/custom/banner.jpg', $this->resolver->resolve('/custom/banner.jpg'));
        self::assertSame('', $this->resolver->resolve(''));
    }
}
