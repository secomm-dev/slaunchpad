<?php
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);
$om->get(\Magento\Framework\Event\ManagerInterface::class)
    ->dispatch('admin_system_config_changed_section_launchpad_footer', ['website' => null, 'store' => null]);
echo "EVENT DISPATCHED\n";
