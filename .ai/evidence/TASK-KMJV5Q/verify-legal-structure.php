<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(State::class)->setAreaCode('adminhtml');
$structure = $om->get(\Magento\Config\Model\Config\Structure::class);
$section = $structure->getElement('launchpad_footer');
echo "section: ", $section->getLabel(), "\n";
foreach ($section->getChildren() as $group) {
    echo "group: ", $group->getId(), " / ", $group->getLabel(), "\n";
    foreach ($group->getChildren() as $field) {
        echo "  field: ", $field->getId(), " / ", $field->getLabel(), "\n";
    }
}
