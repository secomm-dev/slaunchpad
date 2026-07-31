<?php
/**
 * MoMo Return (browser redirect) controller.
 *
 * Lenient: the authoritative confirmation is the Notify (IPN). Here we only
 * look at MoMo's resultCode to decide success/failure page.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;

class ReturnAction extends Action implements HttpGetActionInterface
{
    public const RESULT_CODE = 'resultCode';

    /**
     * Redirect the customer based on MoMo's resultCode.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultCode = (int)$this->getRequest()->getParam(self::RESULT_CODE, -1);

        if ($resultCode === 0) {
            return $this->_redirect('checkout/onepage/success');
        }

        $this->messageManager->addErrorMessage(
            __('MoMo payment was not completed. Please try again.')
        );

        return $this->_redirect('checkout/cart');
    }
}
