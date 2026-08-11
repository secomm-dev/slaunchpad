<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator;

/**
 * Interface ValidatorInterface
 * @package Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator
 */
interface ValidatorInterface
{
    /**
     * Performs domain-related validation for business object
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject);
}
