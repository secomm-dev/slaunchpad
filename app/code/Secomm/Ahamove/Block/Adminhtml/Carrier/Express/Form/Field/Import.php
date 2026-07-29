<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Block\Adminhtml\Carrier\Express\Form\Field;

class Import extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setType('file');
    }

    /**
     * Enter description here...
     *
     * @return string
     */
    public function getElementHtml()
    {
        $html = '';

        $html .= '<input id="time_condition" type="hidden" name="' . $this->getName() . '" value="' . time() . '" />';

        $html .= <<<EndHTML
        <script>
        require(['prototype'], function(){
        Event.observe($('carriers_ahamove_ahamove_shipping_method_ahamove_express_condition_name'), 'change', checkConditionName.bind(this));
        function checkConditionName(event)
        {
            var conditionNameElement = Event.element(event);
            if (conditionNameElement && conditionNameElement.id) {
                $('time_condition').value = '_' + conditionNameElement.value + '/' + Math.random();
            }
        }
        });
        </script>
EndHTML;

        $html .= parent::getElementHtml();

        return $html;
    }
}
