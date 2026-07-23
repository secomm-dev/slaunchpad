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
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Block\Adminhtml\Field;

/**
 * Block for shipping method field in OSC admin.
 */
class Shipping extends AbstractOrderField
{
    public const BLOCK_ID = 'mposc-shipping-method';
    public const BLOCK_SCOPE = [2, 3]; // position shipping

    /**
     * @return string
     */
    public function getBlockTitle()
    {
        return (string)__('Shipping Method');
    }
}
