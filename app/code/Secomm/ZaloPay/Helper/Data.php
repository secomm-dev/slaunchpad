<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Secomm\ZaloPay\Gateway\Helper\Authorization;

class Data extends AbstractHelper
{
    public function __construct(
        Context                 $context,
        protected Authorization $authorization
    ) {
        parent::__construct($context);
    }

    /**
     * Check if the callback is valid or not
     *
     * @param array $data - is the query string that zalopay passes into the redirect link ($_GET)
     * @return bool
     * - true: valid
     * - false: invalid
     */
    public function verifyRedirect(array $data): bool
    {
        $reqChecksum = $data["checksum"];
        $key2 = $this->authorization->getKey2();
        $checksum = $this->redirect($data, $key2);
        return $reqChecksum === $checksum;
    }

    /**
     * @param array $params
     * @param string|null $key2
     * @return string
     */
    public function redirect(array $params, string $key2 = null): string
    {
        return $this->compute($params['appid'] . "|" . $params['apptransid'] . "|" . $params['pmcid'] . "|" . $params['bankcode']
            . "|" . $params['amount'] . "|" . $params['discountamount'] . "|" . $params["status"], $key2);
    }

    /**
     * @param string $params
     * @param string|null $key
     * @return string
     */
    public function compute(string $params, string $key = null): string
    {
        if (is_null($key)) {
            $key = $this->authorization->getKey1();
        }
        return hash_hmac("sha256", $params, $key);
    }

    /**
     * Use a regular expression to retain only alphanumeric characters
     *
     * @param $inputString
     * @return string
     */
    public function removeSpecialChars($inputString): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $inputString);
    }
}
