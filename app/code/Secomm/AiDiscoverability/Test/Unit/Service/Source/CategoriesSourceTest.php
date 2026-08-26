<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service\Source;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\CanonicalPolicy;
use Secomm\AiDiscoverability\Service\SeoPolicy;
use Secomm\AiDiscoverability\Service\Source\CategoriesSource;

/**
 * SPEC-TASK-5TGJ7V runtime-investigation regression: the vi_vn /llms.txt
 * showed no `## Collections` although categories were selected and saved at
 * store scope. Root cause was DATA (class D): the store view had ZERO
 * category url_rewrite rows, so CanonicalPolicy::getCategoryUrl returned
 * null and the entries were (correctly) omitted. These tests pin that
 * behavior: a category without a store rewrite is skipped, never emitted
 * with a fabricated URL.
 */
class CategoriesSourceTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var CategoryRepositoryInterface&MockObject
     */
    private $categoryRepository;

    /**
     * @var CanonicalPolicy&MockObject
     */
    private $canonicalPolicy;

    /**
     * @var SeoPolicy&MockObject
     */
    private $seoPolicy;

    /**
     * @var Store&MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->canonicalPolicy = $this->createMock(CanonicalPolicy::class);
        $this->seoPolicy = $this->createMock(SeoPolicy::class);

        $group = $this->createMock(Group::class);
        $group->method('getRootCategoryId')->willReturn(2);

        $this->store = $this->createMock(Store::class);
        $this->store->method('getId')->willReturn(3);
        $this->store->method('getGroup')->willReturn($group);

        $this->seoPolicy->method('isNoindexed')->willReturn(false);
    }

    /**
     * Category mock carrying EAV magic getters needed by the source.
     *
     * @param int $id entity id
     * @param string $path category path
     * @param string $metaDescription meta_description value
     * @param string $description description value
     * @return Category&\PHPUnit\Framework\MockObject\MockObject
     */
    private function categoryMock(
        int $id,
        string $path,
        string $metaDescription = '',
        string $description = ''
    ): Category {
        return $this->buildCategoryMock($id, $path, $metaDescription, $description);
    }

    /**
     * Builds the underlying partial mock (declared getters via onlyMethods,
     * EAV magic getters via addMethods).
     *
     * @param int $id entity id
     * @param string $path category path
     * @param string $metaDescription meta_description value
     * @param string $description description value
     * @return Category&\PHPUnit\Framework\MockObject\MockObject
     */
    private function buildCategoryMock(
        int $id,
        string $path,
        string $metaDescription,
        string $description
    ): Category {
        // Declared methods go through onlyMethods(); EAV magic getters (not
        // declared on the model) through addMethods().
        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getCustomAttributes', 'getIsActive', 'getName', 'getPath', 'getParentId'])
            ->addMethods(['getMetaDescription', 'getDescription'])
            ->getMock();
        $category->method('getId')->willReturn($id);
        $category->method('getIsActive')->willReturn(true);
        $category->method('getName')->willReturn('Category ' . $id);
        $category->method('getPath')->willReturn($path);
        $category->method('getCustomAttributes')->willReturn([]);
        $category->method('getMetaDescription')->willReturn($metaDescription);
        $category->method('getDescription')->willReturn($description);

        return $category;
    }

    private function rootCategory(): Category
    {
        // Root (id 2, path "1/2") resolved by getRootCategoryPath().
        return $this->categoryMock(2, '1/2');
    }

    public function testCategoryWithoutStoreUrlRewriteIsOmittedNotFabricated(): void
    {
        $this->config->method('getCategoryIds')->willReturn([3, 4]);
        $this->categoryRepository->method('get')->willReturnCallback(
            fn (int $id): Category => $id === 2
                ? $this->rootCategory()
                : $this->categoryMock($id, '1/2/' . $id)
        );
        // Store 3 has NO url_rewrite rows: canonical policy resolves nothing.
        $this->canonicalPolicy->method('getCategoryUrl')->willReturn(null);

        $entries = $this->source()->getEntries($this->store);

        $this->assertSame([], $entries);
    }

    public function testCategoryWithCanonicalRewriteIsEmitted(): void
    {
        $this->config->method('getCategoryIds')->willReturn([4]);
        $this->categoryRepository->method('get')->willReturnCallback(
            fn (int $id): Category => $id === 2
                ? $this->rootCategory()
                : $this->categoryMock($id, '1/2/' . $id)
        );
        $this->canonicalPolicy->method('getCategoryUrl')->willReturn('https://example.com/gear/fitness-equipment.html');

        $entries = $this->source()->getEntries($this->store);

        $this->assertSame(
            [
                [
                    'label' => 'Category 4',
                    'url' => 'https://example.com/gear/fitness-equipment.html',
                ],
            ],
            $entries
        );
    }

    public function testMetaDescriptionPreferredOverDescriptionAttribute(): void
    {
        $this->config->method('getCategoryIds')->willReturn([5]);
        $this->categoryRepository->method('get')->willReturnCallback(
            fn (int $id): Category => $id === 2
                ? $this->rootCategory()
                : $this->categoryMock($id, '1/2/' . $id, 'Meta wins', '<p>Body text</p>')
        );
        $this->canonicalPolicy->method('getCategoryUrl')->willReturn('https://example.com/training');

        $entries = $this->source()->getEntries($this->store);

        $this->assertSame('Meta wins', $entries[0]['description']);
    }

    public function testDescriptionAttributeFallbackStrippedAndBounded(): void
    {
        $this->config->method('getCategoryIds')->willReturn([5]);
        $longHtml = '<p>' . str_repeat('word ', 100) . '</p><script>alert(1)</script>';
        $this->categoryRepository->method('get')->willReturnCallback(
            fn (int $id): Category => $id === 2
                ? $this->rootCategory()
                : $this->categoryMock($id, '1/2/' . $id, '', $longHtml)
        );
        $this->canonicalPolicy->method('getCategoryUrl')->willReturn('https://example.com/training');

        $entries = $this->source()->getEntries($this->store);

        $description = $entries[0]['description'];
        $this->assertSame(240, mb_strlen($description));
        $this->assertStringNotContainsString('<', $description);
        $this->assertStringNotContainsString('alert', $description);
        $this->assertStringStartsWith('word word', $description);
    }

    public function testNoUsableDescriptionEmitsLinkOnly(): void
    {
        $this->config->method('getCategoryIds')->willReturn([5]);
        $this->categoryRepository->method('get')->willReturnCallback(
            fn (int $id): Category => $id === 2
                ? $this->rootCategory()
                : $this->categoryMock($id, '1/2/' . $id, '', "<img src=x>\n   ")
        );
        $this->canonicalPolicy->method('getCategoryUrl')->willReturn('https://example.com/training');

        $entries = $this->source()->getEntries($this->store);

        $this->assertArrayNotHasKey('description', $entries[0]);
    }

    private function source(): CategoriesSource
    {
        return new CategoriesSource(
            $this->config,
            $this->categoryRepository,
            $this->canonicalPolicy,
            $this->seoPolicy,
            $this->createMock(LoggerInterface::class)
        );
    }
}
