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
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Reply as ReplyBlock;

/**
 * Class Files
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer
 */
class Reply extends AbstractElement
{
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
     * @param Layout $layout
     * @param array $data
     */
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        Layout $layout,
        $data = []
    ) {
        $this->_layout = $layout;

        parent::__construct(
            $factoryElement,
            $factoryCollection,
            $escaper,
            $data
        );

        $this->setType('reply_box');
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        /** @var ReplyBlock $replyBlock */
        $replyBlock = $this->_layout->createBlock(ReplyBlock::class);

        return $replyBlock->setTemplate('Mageplaza_RMA::request/form/reply.phtml')
            ->setId('mp_request_reply')
            ->setElement($this)
            ->setFormName('edit_form')
            ->toHtml();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'reply_box';
    }
}
