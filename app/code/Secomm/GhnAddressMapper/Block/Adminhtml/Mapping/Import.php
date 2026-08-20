<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Block\Adminhtml\Mapping;

use Magento\Backend\Block\Widget\Form\Container;

class Import extends Container
{
    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_blockGroup = 'Secomm_GhnAddressMapper';
        $this->_controller = 'adminhtml_mapping';
        $this->_mode = 'import';

        parent::_construct();

        $this->buttonList->update('save', 'label', __('Start Import'));
        $this->buttonList->update('save', 'class', 'primary');

        $this->buttonList->add(
            'download_sample',
            [
                'label' => __('Download Sample CSV'),
                'onclick' => 'setLocation(\'' . $this->getUrl('ghn_address_mapper/mapping/downloadSample') . '\')',
                'class' => 'action-default'
            ],
            -1
        );
    }

    public function getHeaderText()
    {
        return __('Import GHN Address Mapping');
    }
}
