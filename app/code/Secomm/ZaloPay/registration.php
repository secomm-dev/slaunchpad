<?php
 /************************************************************
  * *
  *  * Copyright © Secomm. All rights reserved.
  *  * See COPYING.txt for license details.
  *  *
  *  * @author    Secomm Teams
  * *  @project   ZaloPay
  */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Secomm_ZaloPay',
    __DIR__
);
