<?php

/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Api;

interface TracerInterface
{
    /**
     * @param array $data
     * @return mixed
     */
    public function getDataSent($data = []);
}
