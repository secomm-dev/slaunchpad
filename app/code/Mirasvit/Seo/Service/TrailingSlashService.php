<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Service;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContext;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Magento\UrlRewrite\Model\UrlRewrite;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Mirasvit\Seo\Model\Config;
use Psr\Log\LoggerInterface;

class TrailingSlashService
{
    /**
     * Entity type => cache tag prefix, mirroring Magento\UrlRewrite\Model\UrlRewrite::$entityToCacheTagMap
     * (module-catalog-url-rewrite/etc/di.xml). Used to batch cache invalidation instead of per-record.
     */
    private const ENTITY_CACHE_TAG_MAP = [
        'product'  => Product::CACHE_TAG,
        'category' => Category::CACHE_TAG,
    ];

    private $urlRewriteCollectionFactory;

    private $urlRewriteFactory;

    private $resourceConnection;

    private $cacheContext;

    private $eventManager;

    private $config;

    private $logger;

    /** @var array<string, array<int, int>> entityType => [entityId => entityId] pending cache invalidation */
    private $affectedEntities = [];

    public function __construct(
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        UrlRewriteFactory           $urlRewriteFactory,
        ResourceConnection          $resourceConnection,
        CacheContext                $cacheContext,
        EventManager                $eventManager,
        Config                      $config,
        LoggerInterface             $logger
    ) {
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->urlRewriteFactory           = $urlRewriteFactory;
        $this->resourceConnection          = $resourceConnection;
        $this->cacheContext                = $cacheContext;
        $this->eventManager                = $eventManager;
        $this->config                      = $config;
        $this->logger                      = $logger;
    }

    public function processUrlRewrites(?int $storeId = null, ?string $entityType = null)
    {
        if (!$this->config->getTrailingSlash($storeId)) {
            return;
        }

        try {
            $collection = $this->urlRewriteCollectionFactory->create();

            $collection->addFieldToFilter('redirect_type', 0);

            if ($storeId) {
                $collection->addFieldToFilter('store_id', $storeId);
            }

            if ($entityType) {
                $collection->addFieldToFilter('entity_type', $entityType);
            }

            $batchSize = 1000;
            $page      = 1;

            $collection->setPageSize($batchSize);

            do {
                $collection->setCurPage($page);
                $collection->clear();

                foreach ($collection as $urlRewrite) {
                    $this->processUrlRewrite($urlRewrite);
                }

                $page++;
            } while ($collection->count() >= $batchSize);

            $this->flushAffectedEntitiesCache();

        } catch (Exception $e) {
            $this->logger->error('Error processing URL rewrites: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function processUrlRewrite(UrlRewrite $urlRewrite): bool
    {
        $requestPath = $urlRewrite->getRequestPath();
        $storeId     = (int)$urlRewrite->getStoreId();

        if ($urlRewrite->getRedirectType() > 0) {
            return false;
        }

        $trailingSlashOption = $this->config->getTrailingSlash($storeId);

        if (!$trailingSlashOption) {
            return false;
        }

        if ($this->hasCorrectFormat($requestPath, $storeId)) {
            return false;
        }

        $newRequestPath = $this->formatUrl($requestPath, $storeId);

        return $this->updateUrlRewrite($urlRewrite, $newRequestPath, $requestPath, $storeId);
    }

    public function formatUrl(string $url, ?int $storeId = null): string
    {
        if (preg_match('/\.[a-zA-Z0-9]{1,5}$/', $url)) {
            return $url;
        }

        $trailingSlashOption = $this->config->getTrailingSlash($storeId);

        if ($trailingSlashOption === Config::TRAILING_SLASH) {
            return rtrim($url, '/') . '/';
        } elseif ($trailingSlashOption === Config::NO_TRAILING_SLASH) {
            if (substr($url, -1) === '/' && strlen($url) > 1) {
                return rtrim($url, '/');
            }
        }

        return $url;
    }

    public function hasCorrectFormat(string $url, ?int $storeId = null): bool
    {
        if (preg_match('/\.[a-zA-Z0-9]{1,5}$/', $url)) {
            return true;
        }

        $trailingSlashOption = $this->config->getTrailingSlash($storeId);

        $hasTrailingSlash = (substr($url, -1) === '/') && (substr($url, -2) !== '//');

        if ($trailingSlashOption === Config::TRAILING_SLASH) {
            return $hasTrailingSlash;
        } elseif ($trailingSlashOption === Config::NO_TRAILING_SLASH) {
            return !$hasTrailingSlash || $url === '/';
        }

        return true;
    }

    /**
     * Update the canonical rewrite's request path and maintain its redirect, using direct SQL so no
     * per-record cache invalidation fires. Affected entities are buffered and flushed in one batched
     * clean_cache_by_tags dispatch (see flushAffectedEntitiesCache) instead of one PURGE per record.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function updateUrlRewrite(UrlRewrite $urlRewrite, string $newRequestPath, string $oldRequestPath, int $storeId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('url_rewrite');

        $redirectCreated = false;

        try {
            $connection->beginTransaction();

            if ($this->urlRewriteExists($newRequestPath, $storeId)) {
                $existingUrlRewrite = $this->findRedirectToPath($newRequestPath, $oldRequestPath, $storeId);

                if ($existingUrlRewrite) {
                    try {
                        if ($this->urlRewriteExists($oldRequestPath, $storeId)) {
                            $connection->delete(
                                $table,
                                ['url_rewrite_id = ?' => (int)$existingUrlRewrite['url_rewrite_id']]
                            );
                        } else {
                            $connection->update(
                                $table,
                                [
                                    'request_path' => $oldRequestPath,
                                    'target_path'  => $newRequestPath,
                                    'description'  => (string)__('Reversed by TrailingSlashService'),
                                ],
                                ['url_rewrite_id = ?' => (int)$existingUrlRewrite['url_rewrite_id']]
                            );

                            $redirectCreated = true;
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Error reversing redirect: ' . $e->getMessage(), ['exception' => $e]);
                        $connection->rollBack();
                        return false;
                    }
                } else {
                    $connection->rollBack();
                    return false;
                }
            }

            if (!$redirectCreated) {
                try {
                    if (!$this->urlRewriteExists($oldRequestPath, $storeId)) {
                        $connection->insert($table, [
                            'entity_type'   => $urlRewrite->getEntityType(),
                            'entity_id'     => (int)$urlRewrite->getEntityId(),
                            'request_path'  => $oldRequestPath,
                            'target_path'   => $newRequestPath,
                            'redirect_type' => 301,
                            'store_id'      => $storeId,
                            'description'   => (string)__('Auto-generated by TrailingSlashService'),
                        ]);
                    } else {
                        $this->logger->info(
                            sprintf(
                                'Could not create redirect from %s to %s - conflict with existing URL rewrite',
                                $oldRequestPath,
                                $newRequestPath
                            )
                        );
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error creating redirect: ' . $e->getMessage(), ['exception' => $e]);
                }
            }

            if ($this->urlRewriteExists($newRequestPath, $storeId)) {
                $existingUrlRewrite = $this->findUrlRewrite($newRequestPath, $storeId);
                if ($existingUrlRewrite
                    && (int)$existingUrlRewrite['url_rewrite_id'] !== (int)$urlRewrite->getId()
                    && (int)$existingUrlRewrite['redirect_type'] === 0
                ) {
                    $this->logger->error(
                        sprintf(
                            'Cannot update URL rewrite ID %s from %s to %s - conflict with existing URL rewrite ID %s',
                            $urlRewrite->getId(),
                            $oldRequestPath,
                            $newRequestPath,
                            $existingUrlRewrite['url_rewrite_id']
                        )
                    );
                    $connection->rollBack();
                    return false;
                }
            }

            try {
                $connection->update(
                    $table,
                    ['request_path' => $newRequestPath],
                    ['url_rewrite_id = ?' => (int)$urlRewrite->getId()]
                );
                $connection->commit();

                $this->registerAffectedEntity((string)$urlRewrite->getEntityType(), (int)$urlRewrite->getEntityId());

                return true;
            } catch (Exception $e) {
                $this->logger->error('Error updating URL rewrite: ' . $e->getMessage(), ['exception' => $e]);
                $connection->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error('Error in transaction: ' . $e->getMessage(), ['exception' => $e]);
            $connection->rollBack();
            return false;
        }
    }

    private function urlRewriteExists(string $requestPath, int $storeId): bool
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('url_rewrite'), 'url_rewrite_id')
            ->where('request_path = ?', $requestPath)
            ->where('store_id = ?', $storeId)
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    private function findRedirectToPath(string $requestPath, string $targetPath, int $storeId): ?array
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('url_rewrite'))
            ->where('request_path = ?', $requestPath)
            ->where('target_path = ?', $targetPath)
            ->where('store_id = ?', $storeId)
            ->where('redirect_type > ?', 0)
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    private function findUrlRewrite(string $requestPath, int $storeId): ?array
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('url_rewrite'))
            ->where('request_path = ?', $requestPath)
            ->where('store_id = ?', $storeId)
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * Buffer an entity whose cache must be invalidated once, at the end of the bulk run.
     */
    private function registerAffectedEntity(string $entityType, int $entityId): void
    {
        if ($entityId <= 0 || !isset(self::ENTITY_CACHE_TAG_MAP[$entityType])) {
            return;
        }

        $this->affectedEntities[$entityType][$entityId] = $entityId;
    }

    /**
     * Dispatch a single clean_cache_by_tags event covering every affected entity, instead of one
     * dispatch (and one Varnish PURGE burst) per processed record. Mirrors the invalidation
     * Magento\UrlRewrite\Model\UrlRewrite::cleanCacheForEntity() performs per save, but batched.
     */
    private function flushAffectedEntitiesCache(): void
    {
        if (!$this->affectedEntities) {
            return;
        }

        $dispatch = false;

        foreach ($this->affectedEntities as $entityType => $entityIds) {
            if (!isset(self::ENTITY_CACHE_TAG_MAP[$entityType])) {
                continue;
            }

            $this->cacheContext->registerEntities(self::ENTITY_CACHE_TAG_MAP[$entityType], array_values($entityIds));
            $dispatch = true;
        }

        if ($dispatch) {
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $this->cacheContext]);
        }

        $this->affectedEntities = [];
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processRequestPath(string $requestPath, int $storeId): bool
    {
        $trailingSlashSetting = $this->config->getTrailingSlash($storeId);

        if (!$trailingSlashSetting || empty($requestPath)) {
            return true;
        }

        if ($requestPath === '/' || empty($requestPath)) {
            return true;
        }

        $urlParts = explode('?', $requestPath);
        $path = $urlParts[0];
        $query = isset($urlParts[1]) ? '?' . $urlParts[1] : '';

        if (strpos($path, '.') !== false ||
            strpos($path, 'api/') === 0 ||
            strpos($path, 'admin/') === 0) {
            return true;
        }

        $hasTrailingSlash = substr($path, -1) === '/';

        try {
            $rewrite = $this->findUrlRewrite($path, $storeId);

            if (!$rewrite) {
                $alternatePath = $hasTrailingSlash ? substr($path, 0, -1) : $path . '/';
                $rewrite = $this->findUrlRewrite($alternatePath, $storeId);

                if ($rewrite) {
                    $this->processUrlRewrite($this->createUrlRewriteModel($rewrite));
                    $this->flushAffectedEntitiesCache();
                    return true;
                }
            } else {
                if (($trailingSlashSetting === Config::TRAILING_SLASH && !$hasTrailingSlash) ||
                    ($trailingSlashSetting === Config::NO_TRAILING_SLASH && $hasTrailingSlash)) {
                    $this->processUrlRewrite($this->createUrlRewriteModel($rewrite));
                    $this->flushAffectedEntitiesCache();
                }
                return true;
            }
        } catch (Exception $e) {
            $this->logger->error('Error processing URL rewrite on-the-fly: ' . $e->getMessage(), [
                'request_path' => $requestPath,
                'store_id' => $storeId,
                'exception' => $e
            ]);
        }

        return false;
    }

    /**
     * Wrap a raw url_rewrite row in a UrlRewrite data model (no DB load, no save) so it can flow
     * through processUrlRewrite() without reintroducing per-record collection instantiation.
     */
    private function createUrlRewriteModel(array $row): UrlRewrite
    {
        $urlRewrite = $this->urlRewriteFactory->create();
        $urlRewrite->setData($row);

        return $urlRewrite;
    }
}
