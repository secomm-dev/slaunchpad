<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\EligibilityChecker;

class EligibilityCheckerTest extends TestCase
{
    /**
     * @var EligibilityChecker
     */
    private $checker;

    protected function setUp(): void
    {
        $this->checker = new EligibilityChecker();
    }

    public function testAllowsPublicPaths(): void
    {
        $this->assertTrue($this->checker->isEligiblePath('/about-us'));
        $this->assertTrue($this->checker->isEligiblePath('/women/dresses'));
        $this->assertTrue($this->checker->isEligiblePath('/'));
    }

    public function testRejectsBlockedRoutes(): void
    {
        $blocked = [
            '/checkout', '/checkout/index', '/cart', '/customer/login', '/account', '/wishlist',
            '/catalogsearch/result', '/product_compare', '/compare', '/review/product',
            '/admin', '/api', '/rest', '/graphql', '/llms.txt',
        ];

        foreach ($blocked as $path) {
            $this->assertFalse($this->checker->isEligiblePath($path), $path);
        }
    }

    public function testRejectsQueryAndFragmentAndExternal(): void
    {
        $this->assertFalse($this->checker->isEligiblePath('/about-us?x=1'));
        $this->assertFalse($this->checker->isEligiblePath('/about-us#top'));
        $this->assertFalse($this->checker->isEligiblePath('https://example.com/about'));
        $this->assertFalse($this->checker->isEligiblePath('/women?color=red'));
    }
}
