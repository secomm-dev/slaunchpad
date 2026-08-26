<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\InvalidateCache;
use Secomm\AiCommerce\Observer\StockInvalidation;

/**
 * SPEC-TASK-AIC-PDC1 §3.2: MSI stock changes invalidate the module cache via
 * the Magento-native clean_cache_by_tags signal carrying product identities.
 */
class StockInvalidationTest extends TestCase
{
    /**
     * @var InvalidateCache&MockObject
     */
    private $invalidateCache;

    /**
     * @var StockInvalidation
     */
    private $observer;

    protected function setUp(): void
    {
        $this->invalidateCache = $this->createMock(InvalidateCache::class);
        $this->observer = new StockInvalidation($this->invalidateCache);
    }

    public function testProductIdentityIdentitiesTriggersCleanAll(): void
    {
        $this->invalidateCache->expects($this->once())->method('cleanAll');
        $this->observer->execute($this->event(['cat_p_42']));
    }

    public function testBareProductTagTriggersCleanAll(): void
    {
        $this->invalidateCache->expects($this->once())->method('cleanAll');
        $this->observer->execute($this->event([Product::CACHE_TAG]));
    }

    public function testNonProductIdentitiesDoNotClean(): void
    {
        $this->invalidateCache->expects($this->never())->method('cleanAll');
        $this->observer->execute($this->event(['cat_c_7', 'other_tag']));
    }

    public function testNonIdentityObjectDoesNotClean(): void
    {
        $this->invalidateCache->expects($this->never())->method('cleanAll');

        $this->observer->execute(new Observer(['event' => $this->eventWithObject(new \stdClass())]));
    }

    /**
     * Build an observer payload whose event object exposes the given identities.
     *
     * @param string[] $identities cache identities
     * @return Observer
     */
    private function event(array $identities): Observer
    {
        return new Observer(['event' => $this->eventWithObject($this->identityObject($identities))]);
    }

    /**
     * Build an event whose magic getObject() returns the given object.
     *
     * @param object $object event data object
     * @return Event&MockObject
     */
    private function eventWithObject(object $object): Event
    {
        // getObject() is a magic getter on Event (DataObject), not declared.
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getObject'])
            ->getMock();
        $event->method('getObject')->willReturn($object);

        return $event;
    }

    /**
     * @param string[] $identities cache identities
     * @return IdentityInterface&MockObject
     */
    private function identityObject(array $identities): IdentityInterface
    {
        return $this->createConfiguredMock(IdentityInterface::class, [
            'getIdentities' => $identities,
        ]);
    }
}
