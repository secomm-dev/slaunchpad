<?php
require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(\Magento\Framework\App\State::class);
$state->setAreaCode('frontend');
$helper = $om->get(\Mageplaza\SocialLoginPro\Helper\Data::class);
var_dump(['btnPosition' => $helper->getSocialBtnPosition(), 'style' => $helper->getStyleManagement()]);
