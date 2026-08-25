<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Input;

use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Service\Input\SearchQueryParser;
use Secomm\AiCommerce\Service\InvalidParameterException;

class SearchQueryParserTest extends TestCase
{
    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var SearchQueryParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getMaxPageSize')->willReturn(50);
        $this->config->method('getFilterAllowlist')->willReturn(['color', 'size']);
        $this->parser = new SearchQueryParser($this->config);
    }

    public function testParsesAllValidKeys(): void
    {
        $result = $this->parser->parse([
            'q' => 'linen',
            'category' => '5',
            'price_min' => '10.5',
            'price_max' => '99',
            'page' => '2',
            'page_size' => '25',
            'sort' => 'price_asc',
            'store' => 'default',
        ], 1);

        $this->assertSame('linen', $result['q']);
        $this->assertSame(5, $result['category_id']);
        $this->assertSame(10.5, $result['price_min']);
        $this->assertSame(99.0, $result['price_max']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(25, $result['page_size']);
        $this->assertSame('price_asc', $result['sort']);
    }

    public function testAppliesDefaults(): void
    {
        $result = $this->parser->parse([], 1);

        $this->assertNull($result['q']);
        $this->assertNull($result['category_id']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['page_size']);
        $this->assertSame('relevance', $result['sort']);
        $this->assertSame([], $result['filters']);
    }

    public function testRejectsUnknownParameter(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['fields' => 'sku,price'], 1);
    }

    public function testRejectsOversizedQueryText(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['q' => str_repeat('a', 129)], 1);
    }

    public function testRejectsPageSizeBeyondCap(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['page_size' => '51'], 1);
    }

    public function testRejectsPageBeyondBound(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['page' => '51'], 1);
    }

    public function testRejectsNonAllowlistedSort(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['sort' => 'created_at'], 1);
    }

    public function testRejectsNonAllowlistedFilterAttribute(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['filter' => ['cost' => '5']], 1);
    }

    public function testRejectsExcessFilters(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['filter' => ['color' => 'red', 'size' => 's', 'a' => 'x', 'b' => 'y', 'c' => 'z']], 1);
    }

    public function testRejectsNonNumericPrice(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->parser->parse(['price_min' => 'abc'], 1);
    }

    public function testAcceptsAllowlistedFilters(): void
    {
        $result = $this->parser->parse(['filter' => ['color' => 'blue', 'Size' => 'm']], 1);
        $this->assertSame(['color' => 'blue', 'size' => 'm'], $result['filters']);
    }
}
