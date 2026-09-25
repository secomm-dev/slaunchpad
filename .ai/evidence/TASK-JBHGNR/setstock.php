<?php
// TASK-JBHGNR test data: php .ai/evidence/TASK-JBHGNR/setstock.php oos|restore .ai/evidence/TASK-JBHGNR/stock-backup.json (run from repo root)
// usage: setstock.php oos|restore <backup.json>
require "app/bootstrap.php";
$b=\Magento\Framework\App\Bootstrap::create(BP,$_SERVER); $om=$b->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$save=$om->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class);
$f=$om->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class);
$oos=['bed-haven-queen-sage','bed-haven-king-sage','sofa-meridian-3seat-cream','sofa-meridian-3seat-sage','sofa-meridian-3seat-charcoal','sofa-meridian-corner-charcoal','table-ovale-8seat'];
$backup=json_decode(file_get_contents($argv[2]),true);
$items=[];
foreach($backup as $row){
  if($argv[1]==='oos' && !in_array($row['sku'],$oos,true)) continue;
  $i=$f->create(); $i->setSku($row['sku']); $i->setSourceCode('default');
  if($argv[1]==='oos'){ $i->setQuantity(0); $i->setStatus(0);} else { $i->setQuantity((float)$row['quantity']); $i->setStatus((int)$row['status']); }
  $items[]=$i;
}
$save->execute($items); echo "saved ",count($items),"\n";
