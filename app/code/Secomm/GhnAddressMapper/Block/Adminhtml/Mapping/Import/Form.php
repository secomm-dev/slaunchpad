<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Block\Adminhtml\Mapping\Import;

use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    protected function _prepareForm()
    {
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getUrl('ghn_address_mapper/mapping/import'),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            ]
        ]);

        $fieldset = $form->addFieldset(
            'base_fieldset',
            ['legend' => __('Import GHN Address Mapping (CSV)')]
        );

        $fieldset->addField(
            'import_file',
            'file',
            [
                'name' => 'import_file',
                'label' => __('Select CSV File'),
                'title' => __('Select CSV File'),
                'required' => true,
                'note' => __('Allowed file type: .csv. File must contain headers: region_id, city_id, ghn_province_id, ghn_district_id, ghn_ward_code.')
            ]
        );

        $fieldset->addField(
            'update_existing',
            'checkbox',
            [
                'name' => 'update_existing',
                'label' => __('Update Existing Mappings'),
                'title' => __('Update Existing Mappings'),
                'value' => 1,
                'note' => __('If checked, existing mappings matching region_id and city_id will be updated.')
            ]
        );

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
