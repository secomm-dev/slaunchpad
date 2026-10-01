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
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Files as FilesBlock;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class Files
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer
 */
class Files extends AbstractElement
{
    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * Images constructor.
     *
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param Registry $coreRegistry
     * @param Layout $layout
     * @param array $data
     */
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        Registry $coreRegistry,
        Layout $layout,
        $data = []
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->_layout = $layout;

        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);

        $this->setType('files');
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        return $this->_layout
            ->createBlock(FilesBlock::class)
            ->setTemplate('Mageplaza_RMA::request/form/gallery.phtml')
            ->setId('media_gallery_content')
            ->setElement($this)
            ->setFormName('edit_form')
            ->toHtml();
    }

    /**
     * @return mixed
     */
    public function getDataObject()
    {
        return $this->_coreRegistry->registry('mageplaza_rma_request');
    }

    /**
     * Get request files
     *
     * @return array|null
     */
    public function getFiles()
    {
        return HelperData::jsonDecode($this->getDataObject() ? $this->getDataObject()->getFiles() : '{}');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'files';
    }
}
