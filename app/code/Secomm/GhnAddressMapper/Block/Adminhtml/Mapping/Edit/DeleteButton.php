<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Block\Adminhtml\Mapping\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\RequestInterface;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        protected UrlInterface $urlBuilder,
        protected RequestInterface $request
    ) {
    }

    public function getButtonData(): array
    {
        $data = [];
        $id = $this->request->getParam('entity_id');
        if ($id) {
            $data = [
                'label' => __('Delete Mapping'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to delete this mapping?'
                ) . '\', \'' . $this->urlBuilder->getUrl('*/*/delete', ['entity_id' => $id]) . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
