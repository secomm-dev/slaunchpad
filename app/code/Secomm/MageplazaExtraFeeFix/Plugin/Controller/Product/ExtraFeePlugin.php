<?php
declare(strict_types=1);

namespace Secomm\MageplazaExtraFeeFix\Plugin\Controller\Product;

use Mageplaza\ExtraFee\Controller\Product\ExtraFee;

/**
 * Normalizes the `super_attribute` request param before Mageplaza ExtraFee 4.5.4
 * feeds it to reset() (PHP 8 TypeError on null) in ExtraFee::execute().
 */
class ExtraFeePlugin
{
    /**
     * Only normalize the missing/null case; valid selections are left untouched.
     *
     * @param ExtraFee $subject
     * @return void
     */
    public function beforeExecute(ExtraFee $subject): void
    {
        $request = $subject->getRequest();
        $superAttribute = $request->getParam('super_attribute');

        if ($superAttribute === null) {
            $request->setParam('super_attribute', []);
        }
    }
}
