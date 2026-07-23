<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Setup\Patch\Data;

use Exception;
use Magento\Cms\Model\BlockFactory;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Customer\Api\AddressMetadataInterface as AddressInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Backend\Datetime;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Framework\Validator\ValidateException;
use Magento\Quote\Setup\QuoteSetup;
use Magento\Sales\Setup\SalesSetup;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreRepository;
use Mageplaza\Osc\Helper\Data as OscHelper;

/**
 * Class InitializeColumns
 * Mageplaza\Osc\Setup\Patch\Data
 */
class InitializeColumns implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var OscHelper
     */
    protected $oscHelper;

    /**
     * @var Config
     */
    protected $resourceConfig;

    /**
     * @var StoreRepository
     */
    protected $storeRepository;

    /**
     * @var BlockFactory
     */
    protected $blockFactory;

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var string
     */
    private $viewDir = '';

    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * @var File
     */
    private $ioFile;

    /**
     * @var DriverFile
     */
    private $driverFile;

    /**
     * @var ReadInterface
     */
    private $mediaDirectory;

    /**
     * InitializeColumns constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Filesystem $filesystem
     * @param Config $resourceConfig
     * @param StoreRepository $storeRepository
     * @param BlockFactory $blockFactory
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        Filesystem $filesystem,
        Config $resourceConfig,
        StoreRepository $storeRepository,
        BlockFactory $blockFactory,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        Reader $moduleReader,
        File $ioFile,
        DriverFile $driverFile
    ) {
        $this->moduleDataSetup      = $moduleDataSetup;
        $this->fileSystem           = $filesystem;
        $this->resourceConfig       = $resourceConfig;
        $this->storeRepository      = $storeRepository;
        $this->blockFactory         = $blockFactory;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory  = $attributeSetFactory;
        $this->serializer           = $serializer;
        $this->scopeConfig          = $scopeConfig;
        $this->cacheTypeList        = $cacheTypeList;
        $this->moduleReader         = $moduleReader;
        $this->ioFile               = $ioFile;
        $this->driverFile           = $driverFile;
        $this->mediaDirectory       = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * ResetDdlCache
     */
    public function resetDdlCache()
    {
        $this->cacheTypeList->cleanType('db_ddl');
    }

    /**
     * Apply
     *
     * @return void
     * @throws LocalizedException
     * @throws ValidateException
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup->startSetup();
        $this->insertBlock();
        $this->updateSealBlock($setup);
        $this->copyDefaultSeal();
        $this->insertCustomAttr($setup);

        $setup->endSetup();
    }

    /**
     * InsertBlock
     *
     * @return $this
     * @throws Exception
     */
    private function insertBlock()
    {
        $block = $this->getSealBlockData();

        $cmsBlock = $this->blockFactory->create()->load($block['identifier'], 'identifier');
        if (!$cmsBlock->getId()) {
            $cmsBlock->setData($block)->save();
        }

        return $this;
    }

    /**
     * GetSealBlockData
     *
     * @return array
     */
    private function getSealBlockData()
    {
        $sealContent = '
            <div class="osc-trust-seals" style="text-align: center;">
                <div class="trust-seals-badges">
                    <a href="https://en.wikipedia.org/wiki/Trust_seal" target="_blank">
                        <img src="{{view url=Mageplaza_Osc/css/images/seal.png}}">
                    </a>
                </div>
                <div class="trust-seals-text">
                    <p>This is a demonstration of trust badge. Please contact your SSL or Security provider to have trust badges embed properly</p>
                </div>
            </div>';

        return [
            'title'      => __('One Step Checkout Seal Content'),
            'identifier' => 'osc-seal-content',
            'content'    => $sealContent,
            'stores'     => [Store::DEFAULT_STORE_ID],
            'is_active'  => 1
        ];
    }

    /**
     * UpdateSealBlock
     *
     * @param $setup
     *
     * @throws LocalizedException
     */
    private function updateSealBlock($setup)
    {
        $stores = $this->storeRepository->getList();
        if (!is_array($stores)) {
            return;
        }
        foreach ($stores as $store) {
            $storeId = $store->getId();
            if ($this->isEnableStaticBlock($storeId)) {
                continue;
            }

            $config = $this->getStaticBlockList($storeId);
            if ($config && is_array($config)) {
                foreach ($config as $key => $row) {
                    if ((int ) $row['position'] === 4) {
                        if (!isset($blockId)) {
                            $blockId = $row['block'];
                        }
                        unset($config[$key]);
                    }
                }

                $data = [
                    'osc/display_configuration/seal_block/is_enabled_seal_block' => 1,
                    'osc/block_configuration/list'                               => $this->serializer->serialize($config)
                ];
                if (isset($blockId)) {
                    $data['osc/display_configuration/seal_block/seal_static_block'] = $blockId;
                }
                $this->saveConfig($setup, $data, $storeId);
            }
        }
    }

    /**
     * Save config value
     *
     * @param ModuleDataSetupInterface $setup
     * @param array $data
     * @param int $scopeId
     *
     * @return $this
     * @throws LocalizedException
     */
    private function saveConfig($setup, $data, $scopeId)
    {
        $scope = $scopeId ? 'stores' : 'default';

        $connection = $setup->getConnection();
        foreach ($data as $path => $value) {
            $select = $connection->select()->from(
                $this->resourceConfig->getMainTable()
            )
                ->where('path = ?', $path)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', $scopeId);

            $row = $connection->fetchRow($select);

            $newData = ['scope' => $scope, 'scope_id' => $scopeId, 'path' => $path, 'value' => $value];
            if ($row) {
                $col = $this->resourceConfig->getIdFieldName();

                $whereCondition = [$col . '=?' => $row[$col]];
                $connection->update($this->resourceConfig->getMainTable(), $newData, $whereCondition);
            } elseif ($scope === 'default') {
                $connection->insert($this->resourceConfig->getMainTable(), $newData);
            }
        }

        return $this;
    }

    /**
     * Copy default seal images
     */
    private function copyDefaultSeal()
    {
        $path = $this->getModuleDir() . '/Files/Seal/default';

        $files = $this->driverFile->readDirectory($path);

        $mediaTemplateSamplePath = $this->mediaDirectory->getAbsolutePath('mageplaza/osc/seal/default/');
        $this->ioFile->checkAndCreateFolder($mediaTemplateSamplePath);

        foreach ($files as $file) {
            $fileName = $this->ioFile->getPathInfo($file)['basename'];

            $this->ioFile->read(
                $file,
                $mediaTemplateSamplePath . $fileName
            );
        }
    }

    /**
     * @return string
     */
    protected function getModuleDir()
    {
        if (!$this->viewDir) {
            $this->viewDir = $this->moduleReader->getModuleDir(
                '',
                'Mageplaza_Osc'
            );
        }

        return $this->viewDir;
    }

    /**
     * InsertCustomAttr
     *
     * @param SalesSetup $setup
     *
     * @throws LocalizedException
     * @throws ValidateException
     */
    private function insertCustomAttr($setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(AddressInterface::ENTITY_TYPE_ADDRESS);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet     = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $fields = [
            'mposc_field_1' => array_merge(
                $this->getDefaultAttr(),
                [
                    'label' => 'Custom Field 1',
                    'input' => 'text',
                ]
            ),
            'mposc_field_2' => array_merge(
                $this->getDefaultAttr(),
                [
                    'label' => 'Custom Field 2',
                    'input' => 'text',
                ]
            ),
            'mposc_field_3' => array_merge(
                $this->getDefaultAttr(),
                [
                    'label'          => 'Custom Field 3',
                    'input'          => 'date',
                    'frontend'       => \Magento\Eav\Model\Entity\Attribute\Frontend\Datetime::class,
                    'backend'        => Datetime::class,
                    'input_filter'   => 'date',
                    'validate_rules' => $this->serializer->serialize(['input_validation' => 'date']),
                ]
            ),
        ];

        foreach ($fields as $code => $attr) {
            $customerSetup->addAttribute(AddressInterface::ATTRIBUTE_SET_ID_ADDRESS, $code, $attr);

            $customerSetup->getEavConfig()->getAttribute(AddressInterface::ENTITY_TYPE_ADDRESS, $code)->addData(
                [
                    'attribute_set_id'   => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'used_in_forms'      => [
                        'customer_register_address',
                        'customer_address_edit',
                        'adminhtml_customer_address',
                        'onestepcheckout_index_index'
                    ]
                ]
            )->save();
        }
    }

    /**
     * GetDefaultAttr
     *
     * @return array
     */
    private function getDefaultAttr()
    {
        return [
            'type'                  => 'static',
            'required'              => false,
            'visible'               => true,
            'user_defined'          => true,
            'sort_order'            => 200,
            'position'              => 200,
            'system'                => false,
            'is_used_in_grid'       => true,
            'is_visible_in_grid'    => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true
        ];
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    private function isEnableStaticBlock($storeId = null)
    {
        $configPath = 'osc/block_configuration/is_enabled_block';

        return $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array|bool|float|int|string|null
     */
    private function getStaticBlockList($storeId = null)
    {
        $configPath  = 'osc/block_configuration/list';
        $configValue = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE, $storeId);
        if (!$configValue) {
            return [];
        }

        return $this->serializer->unserialize($configValue);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
        return '2.1.10';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
