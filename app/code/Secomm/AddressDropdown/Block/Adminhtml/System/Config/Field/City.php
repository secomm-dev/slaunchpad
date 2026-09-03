<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.scomm.vn)
 * See COPYING.txt for license details.
 *
 * SL-013 / FEAT-007 (DEC-019/025): generic config `city` field renderer that injects a
 * dependent city dropdown (city_select) next to the native city input. The native input is
 * kept as the config VALUE CARRIER so Magento persists the selected city name into
 * core_config_data unchanged.
 *
 * Country-agnostic + data-driven: city options are loaded by the companion
 * cascade JS (Secomm_AddressDropdown/js/config/address-city) via the generic GraphQL
 * resolver GetListCity (area=adminhtml). VN behaviour/label/validate is
 * owned by Secomm_VietNamAddress (DEC-019). No country=='VN' logic lives here.
 *
 * TASK-6MKF0V: the sub_city select was retired — the cascade stops at the city level.
 *
 * Mirrors the admin order address renderer pattern (SL-012) but for the system.xml config
 * form (Magento_Config), not a sales block-form or ui_component.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class City extends Field
{
    /**
     * Render the native config input (value carrier) wrapped together with a dependent
     * city select. The wrapper carries the data-mage-init that drives the generic
     * cascade JS.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $cityInputId = (string) $element->getHtmlId();

        $this->setElement($element);
        $this->setData('city_input_id', $cityInputId);
        $this->setData('city_select_id', $cityInputId . '_secomm_city_select');
        $this->setData('current_city', (string) ($element->getValue() ?? ''));
        $this->setTemplate('Secomm_AddressDropdown::system/config/field/city.phtml');

        return $this->_toHtml();
    }
}
