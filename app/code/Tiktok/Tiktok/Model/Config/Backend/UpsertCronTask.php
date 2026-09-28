<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\App\CacheInterface;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Config\Backend\CronTask;

/**
 * Add/Update cron task information in core_config_data
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class UpsertCronTask extends Value
{
    /**
     * UpsertCronTask construct
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory $configValueFactory
     * @param \Tiktok\Tiktok\Model\Config\Backend\CronTask $cronTask
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $tiktokLogger
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ValueFactory $configValueFactory,
        private readonly CronTask $cronTask,
        private readonly TiktokLogger $tiktokLogger,
        private readonly CacheInterface $cache,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Add cron task
     *
     * @throws \Exception
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function afterSave()
    {
        $isEnabled = $this->getData('groups/general/fields/enable/value');
        if (!$isEnabled) {
            // Module is disabled or unexpected behavior
            return $this;
        }

        $scope = $this->getScope();
        $scopeId = $this->getScopeId();
        if ($scope !== 'websites' || $scopeId == null) {
            return $this;
        }

        if (!$this->isValueChanged()) {
            return $this;
        }

        $cronExprString = $this->getData('groups/sync/fields/sync_frequency/value');
        $exprPath = $this->cronTask->getExprConfigPath((string)$scopeId);
        $modelPath = $this->cronTask->getModelConfigPath((string)$scopeId);

        try {
            /** @var \Magento\Framework\App\Config\ValueInterface $exprValue */
            $exprValue = $this->configValueFactory->create()->load($exprPath, 'path');
            $exprValue->setValue($cronExprString)->setPath($exprPath)->save();
            $this->cache->clean(['crontab']);

            $this->configValueFactory->create()->load(
                $modelPath,
                'path'
            )->setValue(
                CronTask::CRON_MODEL
            )->setPath(
                $modelPath
            )->save();
        } catch (\Exception $e) {
            $this->tiktokLogger->error($e->getMessage());
        }

        return parent::afterSave();
    }
}
