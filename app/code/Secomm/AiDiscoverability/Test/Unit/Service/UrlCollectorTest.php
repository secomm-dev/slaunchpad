<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\UrlCollector;

class UrlCollectorTest extends TestCase
{
    /**
     * @var UrlCollector
     */
    private $collector;

    protected function setUp(): void
    {
        $this->collector = new UrlCollector();
    }

    public function testDeduplicatesByCanonicalUrlKeepingFirstOccurrence(): void
    {
        $entries = [
            ['label' => 'Priority', 'url' => 'https://shop.test/about'],
            ['label' => 'CMS', 'url' => 'https://shop.test/about'],
            ['label' => 'Other', 'url' => 'https://shop.test/other'],
        ];

        $result = $this->collector->collect($entries, 100);

        $this->assertCount(2, $result);
        $this->assertSame('Priority', $result[0]['label']);
    }

    public function testBoundsToMaxUrls(): void
    {
        $entries = [];
        for ($i = 1; $i <= 150; $i++) {
            $entries[] = ['label' => "P$i", 'url' => "https://shop.test/p$i"];
        }

        $this->assertCount(100, $this->collector->collect($entries, 100));
    }

    public function testSortsByLabelCaseInsensitively(): void
    {
        $entries = [
            ['label' => 'dresses', 'url' => 'u3'],
            ['label' => 'Áo khoác', 'url' => 'u1'],
            ['label' => 'Bags', 'url' => 'u2'],
        ];

        $labels = array_column($this->collector->sortByLabel($entries), 'label');

        $this->assertSame(['Bags', 'dresses', 'Áo khoác'], $labels);
    }
}
