<?php
/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source\ApiRequest;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    const STATUS_CODE_FAIL = 0;
    const STATUS_CODE_PENDING = 0;
    const STATUS_CODE_SUCCESSFUL_ALL = 1;
    const STATUS_CODE_PARTIAL_SUCCESS = 2;
    const STATUS_CODE_FAIL_ALL = 3;
    const STATUS_CODE_SYSTEM_FAIL = 4;
    const STATUS_CODE_SUCCESS = 200;
    const STATUS_CODE_CREATED = 201;
    const STATUS_CODE_NOT_FOUND = 404;
    const STATUS_CODE_TOKEN_NOT_FOUND = 404;
    const STATUS_CODE_BAD_REQUEST = 400;
    const STATUS_CODE_AUTHENTICATION_FAIL = 401;
    const STATUS_CODE_FORBIDDEN = 403;

    const STATUS_CODE_NOT_ACCEPTABLE = 406;
    const STATUS_CODE_REQUEST_TIMEOUT = 408;
    const STATUS_CODE_CONFICL = 409;
    const STATUS_CODE_SYSTEM_PROCESS_ERROR = 500;
    const STATUS_CODE_RESTARTING_FO = 501;
    const STATUS_CODE_ALL_OTHER_CASES_OF_FAILURE = 502;
    const STATUS_CODE_MAINTENANCE = 503;
    const STATUS_CODE_TIMEOUT = 504;
    const STATUS_CODE_VPN_ERROR = 505;
    const STATUS_CODE_MAX_EXCEEDED_ERROR = 429;
    const STATUS_CODE_ALL_NOT_FOUND_4040 = 4040;

    const STATUS_LABEL_FAIL = 'Failure: Structure incorrect / Params incorrect format...';
    const STATUS_LABEL_SUCCESS = 'Success';
    const STATUS_LABEL_NOT_FOUND = 'Not Found';
    const STATUS_LABEL_BAD_REQUEST = 'Bad Request';
    const STATUS_LABEL_AUTHENTICATION_FAIL = 'Authentication Fail';
    const STATUS_LABEL_FORBIDDEN = 'Forbidden';
    const STATUS_LABEL_SYSTEM_PROCESS_ERROR = 'System Process Error';
    const STATUS_LABEL_RESTARTING_FO = 'Restarting F&O';
    const STATUS_LABEL_ALL_OTHER_CASES_OF_FAILURE = 'All other cases of failure';
    const STATUS_LABEL_MAINTENANCE = 'Maintenance';
    const STATUS_LABEL_TIMEOUT = 'Timeout';
    const STATUS_LABEL_VPN_ERROR = 'VPN error';
    const STATUS_LABEL_MAX_EXCEEDED_ERROR = 'Too many requests';


    /**
     * @var array
     */
    protected $options;

    public function toOptionArray()
    {
        if ($this->options === null) {
            foreach ($this->getOptions() as $value => $label) {
                $this->options[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }
        }
        return $this->options;
    }

    public function getOptions()
    {
        return [
            self::STATUS_CODE_SUCCESS => self::STATUS_LABEL_SUCCESS,
            self::STATUS_CODE_FAIL => self::STATUS_LABEL_FAIL,
            self::STATUS_CODE_BAD_REQUEST => self::STATUS_LABEL_BAD_REQUEST,
            self::STATUS_CODE_NOT_FOUND => self::STATUS_LABEL_NOT_FOUND,
            self::STATUS_CODE_FORBIDDEN => self::STATUS_LABEL_FORBIDDEN,
            self::STATUS_CODE_AUTHENTICATION_FAIL => self::STATUS_LABEL_AUTHENTICATION_FAIL,
            self::STATUS_CODE_SYSTEM_PROCESS_ERROR => self::STATUS_LABEL_SYSTEM_PROCESS_ERROR,
            self::STATUS_CODE_RESTARTING_FO => self::STATUS_LABEL_RESTARTING_FO,
            self::STATUS_CODE_ALL_OTHER_CASES_OF_FAILURE => self::STATUS_LABEL_ALL_OTHER_CASES_OF_FAILURE,
            self::STATUS_CODE_MAINTENANCE => self::STATUS_LABEL_MAINTENANCE,
            self::STATUS_CODE_TIMEOUT => self::STATUS_LABEL_TIMEOUT,
            self::STATUS_CODE_VPN_ERROR => self::STATUS_LABEL_VPN_ERROR,
            self::STATUS_CODE_MAX_EXCEEDED_ERROR => self::STATUS_LABEL_MAX_EXCEEDED_ERROR,
        ];
    }
}
