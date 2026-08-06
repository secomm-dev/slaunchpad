<?php

/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Traits;

use Secomm\Ahamove\Model\Config\Source\ApiRequest\Status;
use Exception;

trait ApiRespond
{
    /**
     * Return final message
     * @param $respond
     * @return mixed|string
     */
    protected function getMgs($respond): mixed
    {
        if ($err = $this->getErrorMgs($respond)) {
            return $err;
        } elseif ($ok = $this->getSuccessMgs($respond)) {
            return $ok;
        }
        return '';
    }

    /**
     * Error Message
     * @param $respond
     * @return mixed|string
     */
    protected function getErrorMgs($respond): mixed
    {
        $message = '';
        if ($this->isError($respond)) {
            if (isset($respond->status) && !empty($this->getMessages()[(int)$respond->status])) {
                $message = $this->getMessages()[(int)$respond->status];
            } else {
                return 'Response empty';
            }
        }

        return $message;
    }

    /**
     * Is error
     * @param $respond
     * @return bool
     */
    protected function isError($respond): bool
    {
        $status = (int)$respond->status;
        return $status !== 200 && $status !== 201;
    }

    /**
     * Return Message from Status
     * @return array
     */
    protected function getMessages(): array
    {
        return [
            Status::STATUS_CODE_SUCCESS => Status::STATUS_LABEL_SUCCESS,
            Status::STATUS_CODE_FAIL => Status::STATUS_LABEL_FAIL,
            Status::STATUS_CODE_BAD_REQUEST => Status::STATUS_LABEL_BAD_REQUEST,
            Status::STATUS_CODE_FORBIDDEN => Status::STATUS_LABEL_FORBIDDEN,
            Status::STATUS_CODE_NOT_FOUND => Status::STATUS_LABEL_NOT_FOUND,
            Status::STATUS_CODE_AUTHENTICATION_FAIL => Status::STATUS_LABEL_AUTHENTICATION_FAIL,
            Status::STATUS_CODE_SYSTEM_PROCESS_ERROR => Status::STATUS_LABEL_SYSTEM_PROCESS_ERROR,
            Status::STATUS_CODE_RESTARTING_FO => Status::STATUS_LABEL_RESTARTING_FO,
            Status::STATUS_CODE_ALL_OTHER_CASES_OF_FAILURE => Status::STATUS_LABEL_ALL_OTHER_CASES_OF_FAILURE,
            Status::STATUS_CODE_MAINTENANCE => Status::STATUS_LABEL_MAINTENANCE,
            Status::STATUS_CODE_TIMEOUT => Status::STATUS_LABEL_TIMEOUT,
            Status::STATUS_CODE_VPN_ERROR => Status::STATUS_LABEL_VPN_ERROR,
            Status::STATUS_CODE_MAX_EXCEEDED_ERROR => Status::STATUS_LABEL_MAX_EXCEEDED_ERROR,
        ];
    }

    /**
     * Success Message
     * @param $respond
     * @return mixed|string
     */
    protected function getSuccessMgs($respond): mixed
    {
        $message = '';
        if (!$this->isError($respond)) {
            if (isset($respond->status) && !empty($this->getMessages()[(int)$respond->status])) {
                $message = $this->getMessages()[(int)$respond->status];
            } else {
                return 'Successful';
            }
        }
        return $message;
    }

    /**
     * Is Authen error
     * @param $respond
     * @return bool
     */
    protected function isAuthenError($respond): bool
    {
        $status = (int)$respond->status;
        return $status === Status::STATUS_CODE_AUTHENTICATION_FAIL;
    }

    /**
     * @param $respond
     * @return bool
     */
    protected function isVoucherError($respond): bool
    {
        return isset($respond->content->responseBody->Code) && $respond->content->responseBody->Code == '4001';
    }

    /**
     * @param $respond
     * @param string $key
     * @return string
     * @throws Exception
     */
    protected function getReferenceKey($respond, string $key = ''): string
    {
        if (!$this->isError($respond)) {
            try {
                $responseContent = json_decode($respond->content);
                $key = $responseContent->SalesOrderNumber;
            } catch (Exception $e) {
                throw new Exception('No response referenceKey');
            }
        }
        return $key;
    }

    /**
     * @param $respond
     * @param string $key
     * @return string
     * @throws Exception
     */
    protected function getReferenceKeyItem($respond, string $key = ''): string
    {
        if (!$this->isError($respond)) {
            try {
                $responseContent = json_decode($respond->content);
                $key = $responseContent->InventoryLotId;
            } catch (Exception $e) {
                throw new Exception('No response referenceKey');
            }
        }
        return $key;
    }
}
