<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model\StoreContext;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\StoreContext\Resolver;
use Secomm\AiCommerce\Service\InvalidParameterException;

class ResolverTest extends TestCase
{
    /**
     * @var StoreRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeRepository;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManager;

    /**
     * @var Resolver
     */
    private $resolver;

    /**
     * @var StoreInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->resolver = new Resolver($this->storeRepository, $this->storeManager);
        $this->store = $this->createMock(StoreInterface::class);
        $this->store->method('getId')->willReturn(3);
    }

    public function testMissingStoreResolvesDocumentedDefault(): void
    {
        $this->storeManager->method('getDefaultStoreView')->willReturn($this->store);
        $this->storeManager->expects($this->once())->method('setCurrentStore')->with(3);

        $this->assertSame($this->store, $this->resolver->resolve(null));
    }

    public function testValidActiveStoreCode(): void
    {
        $this->storeRepository->method('getActiveStoreByCode')->with('us_en')->willReturn($this->store);

        $this->assertSame($this->store, $this->resolver->resolve('us_en'));
    }

    public function testInvalidStoreCodeThrows400(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->resolver->resolve('not a code!');
    }

    public function testUnknownStoreCodeThrows400(): void
    {
        $this->storeRepository->method('getActiveStoreByCode')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(InvalidParameterException::class);
        $this->resolver->resolve('nope');
    }

    public function testFallsBackToFirstStoreWhenNoDefault(): void
    {
        $this->storeManager->method('getDefaultStoreView')->willReturn(null);
        $this->storeRepository->method('getList')->willReturn([$this->store]);

        $this->assertSame($this->store, $this->resolver->resolve(''));
    }

    /**
     * BUG-D4QK1Q: router-phase resolution must be pure data — it must never
     * mutate the current store during router matching.
     */
    public function testResolveAsDataDoesNotMutateCurrentStore(): void
    {
        $this->storeRepository->method('getActiveStoreByCode')->with('vi_vn')->willReturn($this->store);
        $this->storeManager->expects($this->never())->method('setCurrentStore');

        $this->assertSame($this->store, $this->resolver->resolveAsData('vi_vn'));
    }

    public function testResolveAsDataResolvesDefaultWithoutMutation(): void
    {
        $this->storeManager->method('getDefaultStoreView')->willReturn($this->store);
        $this->storeManager->expects($this->never())->method('setCurrentStore');

        $this->assertSame($this->store, $this->resolver->resolveAsData(null));
    }

    public function testResolveAsDataKeepsInvalidCodeContract(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->resolver->resolveAsData('not a code!');
    }
}
