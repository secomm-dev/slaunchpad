# Create Magento 2 Admin UI Component Grid

## Purpose
Use this skill to build a paginated, filterable, sortable admin grid listing a custom entity — e.g. "Store Pickup Locations" or "Product Labels" — using Magento's UI Component system.

## Prerequisites
- Read `AGENTS.md` Section 7.2 — UI components via XML, collections extending core
- Read `project-context/05-conventions.md` for admin route and ACL conventions
- A module already created (use `create-module`)
- A DB table and model/resource model for the entity already exist (use `create-db-schema`)

## Input
- **Entity name** (e.g. `StoreLocation`)
- **Admin route ID** (e.g. `acme_pickup`)
- **ACL resource ID** (e.g. `Acme_StorePickup::locations`)
- **Columns** (e.g. name, address, postcode, is_active, created_at)

## Generated Files
- `etc/adminhtml/routes.xml`
- `etc/adminhtml/menu.xml`
- `Controller/Adminhtml/StoreLocation/Index.php`
- `view/adminhtml/layout/acme_pickup_storelocation_index.xml`
- `view/adminhtml/ui_component/acme_storelocation_listing.xml`
- `Model/ResourceModel/StoreLocation/Collection.php`
- `Ui/DataProvider/StoreLocationDataProvider.php`

## Step-by-Step

### Step 1: Register the admin route
`etc/adminhtml/routes.xml`:
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="admin">
        <route id="acme_pickup" frontName="acme_pickup">
            <module name="Acme_StorePickup" before="Magento_Backend"/>
        </route>
    </router>
</config>
```

### Step 2: Add the menu item (optional)
`etc/adminhtml/menu.xml`:
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd">
    <menu>
        <add id="Acme_StorePickup::pickup"
             title="Store Pickup"
             module="Acme_StorePickup"
             sortOrder="100"
             resource="Acme_StorePickup::pickup"/>
        <add id="Acme_StorePickup::pickup_locations"
             title="Manage Locations"
             module="Acme_StorePickup"
             sortOrder="10"
             action="acme_pickup/storelocation"
             resource="Acme_StorePickup::locations"
             parent="Acme_StorePickup::pickup"/>
    </menu>
</config>
```

### Step 3: Create the controller
`Controller/Adminhtml/StoreLocation/Index.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Controller\Adminhtml\StoreLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_StorePickup::locations';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Acme_StorePickup::pickup_locations');
        $resultPage->getConfig()->getTitle()->prepend(__('Store Pickup Locations'));
        return $resultPage;
    }
}
```

### Step 4: Create the layout handle
The handle name MUST match `{frontName}_{controllerFolder}_{action}` in lowercase. Here: `acme_pickup_storelocation_index`.

`view/adminhtml/layout/acme_pickup_storelocation_index.xml`:
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="acme_storelocation_listing"/>
        </referenceContainer>
    </body>
</page>
```

### Step 5: Create the collection
`Model/ResourceModel/StoreLocation/Collection.php` — extends `AbstractCollection` and binds the model + resource model.
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model\ResourceModel\StoreLocation;

use Acme\StorePickup\Model\ResourceModel\StoreLocation as StoreLocationResource;
use Acme\StorePickup\Model\StoreLocation as StoreLocationModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(StoreLocationModel::class, StoreLocationResource::class);
    }
}
```

### Step 6: Create the data provider
`Ui/DataProvider/StoreLocationDataProvider.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Ui\DataProvider;

use Acme\StorePickup\Model\ResourceModel\StoreLocation\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class StoreLocationDataProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $data = [];
        foreach ($this->getCollection() as $item) {
            $data[$item->getId()] = $item->getData();
        }
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($data),
        ];
    }
}
```

### Step 7: Create the listing component XML
`view/adminhtml/ui_component/acme_storelocation_listing.xml`:
```xml
<?xml version="1.0"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">acme_storelocation_listing.acme_storelocation_listing_data_source</item>
        </item>
    </argument>
    <settings>
        <buttons>
            <button name="add">
                <label>New Location</label>
                <class>primary</class>
                <url path="*/*/new"/>
            </button>
        </buttons>
        <spinner>acme_storelocation_columns</spinner>
        <deps>
            <dep>acme_storelocation_listing.acme_storelocation_listing_data_source</dep>
        </deps>
    </settings>
    <dataSource name="acme_storelocation_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <updateUrl path="mui/index/render"/>
        </settings>
        <aclResource>Acme_StorePickup::locations</aclResource>
        <dataProvider class="Acme\StorePickup\Ui\DataProvider\StoreLocationDataProvider"
                      name="acme_storelocation_listing_data_source">
            <settings>
                <requestFieldName>id</requestFieldName>
                <primaryFieldName>location_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>
    <listingToolbar name="listing_top">
        <settings>
            <sticky>true</sticky>
        </settings>
        <bookmark name="bookmarks"/>
        <columnsControls name="columns_controls"/>
        <filters name="listing_filters"/>
        <paging name="listing_paging"/>
    </listingToolbar>
    <columns name="acme_storelocation_columns">
        <selectionsColumn name="ids">
            <settings>
                <indexField>location_id</indexField>
            </settings>
        </selectionsColumn>
        <column name="location_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">ID</label>
                <sorting>asc</sorting>
            </settings>
        </column>
        <column name="name">
            <settings>
                <filter>text</filter>
                <label translate="true">Location Name</label>
            </settings>
        </column>
        <column name="address">
            <settings>
                <filter>text</filter>
                <label translate="true">Address</label>
            </settings>
        </column>
        <column name="postcode">
            <settings>
                <filter>text</filter>
                <label translate="true">Postcode</label>
            </settings>
        </column>
        <column name="is_active" component="Magento_Ui/js/grid/columns/select">
            <settings>
                <options class="Magento\Config\Model\Config\Source\Yesno"/>
                <filter>select</filter>
                <dataType>select</dataType>
                <label translate="true">Active</label>
            </settings>
        </column>
        <column name="created_at" component="Magento_Ui/js/grid/columns/date">
            <settings>
                <filter>dateRange</filter>
                <dataType>date</dataType>
                <label translate="true">Created</label>
            </settings>
        </column>
    </columns>
</listing>
```

### Step 8: Compile and verify
```bash
bin/magento setup:di:compile
bin/magento cache:clean
```

## Coding Rules Applied
- **UI Component grid via XML** (AGENTS.md 7.2): `ui_component/*.xml` is the standard, not the legacy `grid block`
- **Collection extends `AbstractCollection`** and binds model + resource in `_construct`
- **ACL resource** declared both in the controller (`ADMIN_RESOURCE`) and `<aclResource>` in the dataSource — enforces menu + grid access
- **Layout handle name = `{frontName}_{controller}_{action}`** in lowercase — Magento derives it from the URL

## Verification
- [ ] Log into admin → menu "Store Pickup > Manage Locations" appears and links to the grid
- [ ] Grid URL `/admin/acme_pickup/storelocation/index/` loads rows with filters and pagination
- [ ] `bin/magento cache:clean` after layout XML edits (layout is cached)
- [ ] Clicking filters / changing sort re-renders via AJAX to `mui/index/render`
- [ ] A non-admin user WITHOUT `Acme_StorePickup::locations` role gets 403 — confirms ACL wiring

## Common Mistakes
- **Wrong layout handle name**: handle is `acme_pickup_storelocation_index` (frontName_controllerFolder_action, all lowercase). `acme_pickup_storeLocation_index` (camelCase) silently does not apply → blank page with no error.
- **Missing `routes.xml`**: the frontName is unknown → 404 on the grid URL even though the controller exists.
- **Collection not extending `AbstractCollection`** or `_init()` mapping the wrong model/resource → "Item with the same ID already exists" or empty grid.
- **`ui_component` name mismatch**: the `<uiComponent name="..."/>` in the layout MUST equal the listing file name (`acme_storelocation_listing`). Mismatch loads nothing.
- **ACL resource typo between controller, menu.xml, and listing XML**: one of them wrong breaks the menu visibility or the grid render with a 403 that looks like an empty grid.
- **Forgetting `setup:di:compile`** after adding the data provider — the constructor signature change is not picked up until compile in production mode.
