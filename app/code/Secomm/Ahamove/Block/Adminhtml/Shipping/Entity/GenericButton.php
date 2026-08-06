<?php

namespace Secomm\Ahamove\Block\Adminhtml\Shipping\Entity;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class GenericButton
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param Context $context
     */
    public function __construct(
        RequestInterface $request,
        Context          $context
    )
    {
        $this->request = $request;
        $this->context = $context;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Wrap button specific options to settings array.
     *
     * @param string $label
     * @param string $class
     * @param string $onclick
     * @param array $dataAttribute
     * @param int $sortOrder
     *
     * @return array
     */
    protected function wrapButtonSettings(
        string $label,
        string $class,
        string $onclick = '',
        array  $dataAttribute = [],
        int    $sortOrder = 0
    ): array
    {
        return [
            'label' => $label,
            'on_click' => $onclick,
            'data_attribute' => $dataAttribute,
            'class' => $class,
            'sort_order' => $sortOrder
        ];
    }

    protected function getEntityId()
    {
        return $this->request->getParam('id');
    }

    /**
     * Get url.
     *
     * @param string $route
     * @param array $params
     *
     * @return string
     */
    protected function getUrl(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
