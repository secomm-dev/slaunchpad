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

namespace Mageplaza\RMA\Ui\Component\Listing;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\MassAction as UiMassAction;
use Mageplaza\RMA\Model\ResourceModel\Status\CollectionFactory;
use Mageplaza\RMA\Model\Status;

/**
 * Class MassAction
 * @package Mageplaza\RMA\Ui\Component\Listing
 */
class MassAction extends UiMassAction
{
    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var AuthorizationInterface
     */
    protected $_authorization;

    /**
     * @var CollectionFactory
     */
    protected $_requestStatusColFact;

    /**
     * MassAction constructor.
     *
     * @param ContextInterface $context
     * @param UrlInterface $url
     * @param AuthorizationInterface $authorization
     * @param CollectionFactory $collectionFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        CollectionFactory $collectionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->_url = $url;
        $this->_authorization = $authorization;
        $this->_requestStatusColFact = $collectionFactory;

        parent::__construct(
            $context,
            $components,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    public function prepare()
    {
        parent::prepare();

        $requestStatusCol = $this->_requestStatusColFact->create();
        $actions = [];
        /** @var array $requestStatusCol */
        foreach ($requestStatusCol as $requestStatus) {
            /** @var Status $requestStatus */
            $actions[] = [
                'type' => $requestStatus->getId(),
                'label' => $requestStatus->getLabel(),
                'url' => $this->_url->getUrl(
                    'mprma/request/massStatus',
                    ['status_id' => $requestStatus->getId()]
                ),
            ];
        }
        $additionalActions = [
            'component' => 'uiComponent',
            'type' => 'status',
            'label' => 'Status'
        ];

        $additionalActions['actions'] = $actions;
        $config = $this->getConfiguration();
        $config['actions'][] = $additionalActions;
        if ($this->_isAllowedAction('Mageplaza_RMA::request_delete')) {
            $deleteAction = [
                'component' => 'uiComponent',
                'type' => 'delete',
                'label' => 'Delete',
                'url' => $this->_url->getUrl('mprma/request/massDelete'),
                'confirm' => [
                    'title' => __('Delete Requests'),
                    'message' => __('Are you sure you want to delete selected Requests?')
                ]
            ];
            $config['actions'][] = $deleteAction;
        }
        $this->setData('config', $config);
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     *
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
