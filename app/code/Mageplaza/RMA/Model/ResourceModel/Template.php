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

namespace Mageplaza\RMA\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Mageplaza\RMA\Model\Template as TemplateModel;

/**
 * Class Template
 * @package Mageplaza\RMA\Model\ResourceModel
 */
class Template extends AbstractDb
{
    /**
     * @var string
     */
    protected $_templateContentTable;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        $connectionName = null
    ) {
        parent::__construct(
            $context,
            $connectionName
        );

        $this->_templateContentTable = $this->getTable('mageplaza_rma_template_content');
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_template', 'template_id');
    }

    /**
     * @param AbstractModel|TemplateModel $object
     *
     * @return AbstractDb
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->_saveStoreRelation($object);

        return parent::_afterSave($object);
    }

    /**
     * @param TemplateModel $object
     *
     * @return $this
     */
    protected function _saveStoreRelation($object)
    {
        $templateId = $object->getId();
        $contents = $object->getContents();
        $updateContents = $object->getUpdateContents();
        $adapter = $this->getConnection();
        if ($updateContents) {
            $adapter->delete($this->_templateContentTable, ['template_id = ?' => (int)$templateId]);
            foreach ($updateContents as $store => $content) {
                $adapter->insertMultiple($this->_templateContentTable, [
                    'template_id' => (int)$templateId,
                    'store_id' => $store,
                    'content' => $content
                ]);
            }
        } else {
            $adapter->delete($this->_templateContentTable, ['template_id = ?' => (int)$templateId]);
        }

        if (empty($contents)) {
            return $this;
        }
        $data = [];
        foreach ($contents as $content) {
            $data[] = [
                'template_id' => (int)$templateId,
                'store_id' => (int)$content['store'],
                'content' => $content['content']
            ];
        }
        $adapter->insertMultiple($this->_templateContentTable, $data);

        return $this;
    }

    /**
     * @param TemplateModel $template
     *
     * @return array
     */
    public function getStoreViewTemplates($template)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()->from($this->_templateContentTable)
            ->where(
                'template_id = ?',
                (int)$template->getId()
            );

        return $adapter->fetchAll($select);
    }

    /**
     * Store contents getter
     *
     * @param TemplateModel $template
     *
     * @return array
     */
    public function getStoreContents($template)
    {
        $select = $this->getConnection()->select()
            ->from(['tsc' => $this->_templateContentTable], [])
            ->where('template_id = ?', $template->getId())
            ->columns([
                'store_id',
                'content',
            ]);

        return $this->getConnection()->fetchPairs($select);
    }
}
