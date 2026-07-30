<?php
 /************************************************************
  * *
  *  * Copyright © Secomm. All rights reserved.
  *  * See COPYING.txt for license details.
  *  *
  *  * @author    Secomm Teams
  * *  @project   ZaloPay
  */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Secomm_ZaloPay',
    __DIR__
);
