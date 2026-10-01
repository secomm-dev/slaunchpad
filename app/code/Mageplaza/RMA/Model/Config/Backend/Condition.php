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
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model\Config\Backend;

use DateTime;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class OrderCondition
 * @package Mageplaza\RMA\Model\Config\Backend
 */
class Condition extends Value
{
    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Condition constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RequestInterface $request
     * @param HelperData $helperData
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        RequestInterface $request,
        HelperData $helperData,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_request    = $request;
        $this->_helperData = $helperData;

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
     * @return Value|void
     * @throws Exception
     */
    public function beforeSave()
    {
        $conditions = $this->_request->getParam('rule');
        $data       = $this->_convertFlatToRecursive($conditions);
        $value      = $this->_helperData->serialize($data['conditions'][1]);
        $this->setValue($value);

        parent::beforeSave();
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _convertFlatToRecursive(array $data)
    {
        $arr = [];
        foreach ($data as $key => $value) {
            if (($key === 'conditions' || $key === 'actions') && is_array($value)) {
                foreach ($value as $id => $valueData) {
                    $paths = explode('--', $id);
                    $node  = &$arr;
                    foreach ($paths as $path) {
                        if (!isset($node[$key][$path])) {
                            $node[$key][$path] = [];
                        }
                        $node = &$node[$key][$path];
                    }
                    if (is_array($valueData)) {
                        foreach ($valueData as $k => $v) {
                            $node[$k] = $v;
                        }
                    }
                }
            } else {
                /** Convert dates into \DateTime */
                if ($value && in_array($key, ['from_date', 'to_date'], true)) {
                    $value = new DateTime($value);
                }
                $this->setData($key, $value);
            }
        }

        return $arr;
    }
}
