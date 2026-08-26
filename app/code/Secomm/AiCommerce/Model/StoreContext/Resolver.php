<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model\StoreContext;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiCommerce\Service\InvalidStoreException;

/**
 * Resolves the public store context from the single V1 mechanism: the
 * `store` query parameter (SPEC-TASK-QV3R7T plan rev 2 §3).
 *
 * - no `store` param → documented default: default store view of the
 *   installation (Magento StoreManager default, never a hardcoded code)
 * - valid active code → that store view
 * - invalid/inactive/disabled code → deterministic 400 invalid_store
 *
 * The resolved store is made the current store so delegating Magento
 * services (pricing, url finder, image urls) evaluate in the right scope.
 */
class Resolver
{
    public const PARAM_STORE = 'store';

    /**
     * @param StoreRepositoryInterface $storeRepository store registry
     * @param StoreManagerInterface $storeManager store manager (default + current store)
     */
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Resolve the store view for the current request and make it current.
     *
     * Controller-phase contract (unchanged): the resolved store becomes the
     * current store so delegating Magento services evaluate in its scope.
     *
     * @param string|null $storeCode raw `store` query parameter value
     * @return StoreInterface resolved active store view
     * @throws InvalidStoreException when the code is not an active frontend store
     */
    public function resolve(?string $storeCode): StoreInterface
    {
        return $this->emulate($this->resolveAsData($storeCode));
    }

    /**
     * Resolve the target store view as DATA — no global store mutation.
     *
     * Router-phase contract (BUG-D4QK1Q): the custom router resolves the
     * request's TARGET store from the same single V1 mechanism (`store` query
     * parameter, absent → installation default) WITHOUT touching the current
     * store, then runs store-scoped enabled/endpoint-path checks against the
     * resolved store id. Router and controllers therefore share one
     * resolution contract and cannot diverge.
     *
     * @param string|null $storeCode raw `store` query parameter value
     * @return StoreInterface resolved active store view
     * @throws InvalidStoreException when the code is not an active frontend store
     */
    public function resolveAsData(?string $storeCode): StoreInterface
    {
        $storeCode = trim((string) $storeCode);

        if ($storeCode === '') {
            $store = $this->storeManager->getDefaultStoreView();

            if ($store === null) {
                // No default configured: fall back to the first store view.
                $stores = $this->storeRepository->getList();
                $store = $stores[0] ?? null;
            }

            if ($store === null) {
                throw new InvalidStoreException(__('Invalid store code.'));
            }

            return $store;
        }

        if (!preg_match('/^[a-z0-9_]{1,32}$/i', $storeCode)) {
            throw new InvalidStoreException(__('Invalid store code.'));
        }

        try {
            return $this->storeRepository->getActiveStoreByCode($storeCode);
        } catch (NoSuchEntityException $e) {
            throw new InvalidStoreException(__('Invalid store code.'));
        }
    }

    /**
     * Make the resolved store the current store for this request.
     *
     * @param StoreInterface $store resolved store view
     * @return StoreInterface the same store view
     */
    private function emulate(StoreInterface $store): StoreInterface
    {
        try {
            $this->storeManager->setCurrentStore($store->getId());
        } catch (NoSuchEntityException $e) {
            throw new InvalidStoreException(__('Invalid store code.'));
        }

        return $store;
    }
}
