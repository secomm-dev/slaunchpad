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

    private function categoryMock(int $id, string $path): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getIsActive')->willReturn(true);
        $category->method('getName')->willReturn('Category ' . $id);
        $category->method('getPath')->willReturn($path);
        $category->method('getCustomAttributes')->willReturn([]);

        return $category;
    }

    private function rootCategory(): Category
    {
        // Root (id 2, path "1/2") resolved by getRootCategoryPath().
        $root = $this->createMock(Category::class);
        $root->method('getPath')->willReturn('1/2');

        return $root;
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
