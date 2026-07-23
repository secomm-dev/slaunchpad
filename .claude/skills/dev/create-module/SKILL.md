# Create Magento 2 Module

## Purpose
Use this skill when you need to create a new Magento 2 module from scratch — the foundational step before adding any custom functionality (plugins, observers, API endpoints, cron jobs, etc.).

## Prerequisites
- Read `AGENTS.md` Section 7.2 (Coding Standards) — Magento Luma base rules
- Read `project-context/03-tech-stack.md` for Magento version and `project-context/05-conventions.md` for vendor namespace
- A working Magento 2.4.x installation with CLI access (`bin/magento`)
- Composer, file write access to `app/code/`

## Input
- **Vendor name** (e.g., `Acme`)
- **Module name** (e.g., `StorePickup`)
- **Dependencies** on other modules (e.g., `Magento_Catalog`, `Magento_Checkout`)

## Generated Files
- `app/code/{Vendor}/{Module}/registration.php`
- `app/code/{Vendor}/{Module}/etc/module.xml`
- `app/code/{Vendor}/{Module}/etc/di.xml` (empty, ready for use)
- `app/code/{Vendor}/{Module}/composer.json`

## Step-by-Step

### Step 1: Confirm module name and check for conflicts
Confirm the `Vendor_Module` name. Before creating, verify no conflict exists.

```bash
bin/magento module:status | grep "Vendor_Module"
```
If it returns nothing, the name is free. If it returns a match, choose a different name.

### Step 2: Create `registration.php`
Magento uses this file to register the component as a module.

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Acme_StorePickup',
    __DIR__
);
```

The string `'Acme_StorePickup'` MUST match the folder path `app/code/Acme/StorePickup` exactly.

### Step 3: Create `etc/module.xml`
Declare the module and its load sequence (dependencies). Modules listed in `<sequence>` are loaded before this one.

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Acme_StorePickup" setup_version="1.0.0">
        <sequence>
            <module name="Magento_Catalog"/>
            <module name="Magento_Checkout"/>
            <module name="Magento_InventoryApi"/>
        </sequence>
    </module>
</config>
```

`setup_version` is still required for upgrade-compatibility tooling even with declarative schema. `<sequence>` is a load-order hint, not an installer dependency — modules NOT listed are still installed but may load in any order.

### Step 4: Create `composer.json`
Required if the module will ever be installed via Composer (production deployment best practice).

```json
{
    "name": "acme/module-store-pickup",
    "description": "Acme Store Pickup — lets customers select a pickup location at checkout.",
    "type": "magento2-module",
    "version": "1.0.0",
    "license": "proprietary",
    "require": {
        "php": "~8.1.0||~8.2.0",
        "magento/framework": "*",
        "magento/module-catalog": "*",
        "magento/module-checkout": "*"
    },
    "autoload": {
        "files": [
            "registration.php"
        ],
        "psr-4": {
            "Acme\\StorePickup\\": ""
        }
    }
}
```

### Step 5: Create empty `etc/di.xml`
Pre-create this so the next developer can drop in DI config without creating the file.

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
</config>
```

### Step 6: Enable the module and run setup upgrade
Magento will not recognize the new module until `setup:upgrade` runs.

```bash
bin/magento module:enable Acme_StorePickup
bin/magento setup:upgrade
bin/magento cache:clean
```

### Step 7: Verify the module is enabled

```bash
bin/magento module:status | grep "Acme_StorePickup"
```
It should appear under "List of enabled modules", NOT under "List of disabled modules".

## Coding Rules Applied
- **Declarative Schema only**: `setup_version` is for compatibility tooling; real DB changes go in `db_schema.xml` (use the `create-db-schema` skill), never `InstallSchema`/`UpgradeSchema`
- **PSR-12 base + Magento extension conventions**: file header copyright, PSR-4 namespace mapping folder structure
- **`bin/magento setup:upgrade`** required after every new module or `module.xml` change
- **Sequence declares intent**: list real dependencies so upgrade tooling and compile phases order correctly

## Verification
- [ ] `bin/magento module:status | grep Acme_StorePickup` lists it under enabled modules
- [ ] `bin/magento setup:upgrade` completed with no errors
- [ ] `ls app/code/Acme/StorePickup/etc/module.xml` exists
- [ ] No errors in `var/log/system.log` referencing the new module
- [ ] `bin/magento setup:di:compile` succeeds (catches DI/sequence issues early)

## Common Mistakes
- **Forgetting `setup:upgrade`**: module files exist on disk but Magento does not load it → fatal "Class not found" or 404. Always run `setup:upgrade` after creating/enabling.
- **Component name mismatch in `registration.php`**: e.g. folder is `StorePickup` but registration says `Store_Pickup` → module silently fails to register. The underscores and casing MUST match the folder.
- **Missing `setup_version`**: `module.xml` without `setup_version` causes "Schema not declared" warnings and breaks the upgrade-compatibility tool. Always include it.
- **Listing a non-installed module in `<sequence>`**: causes setup to abort with "Unknown module". Only list modules guaranteed to be present.
- **Wrong PSR-4 path in `composer.json`**: namespace `Acme\\StorePickup\\` mapped to wrong folder → autoloader cannot find classes. Namespace `Acme\StorePickup` maps to root `""` of the module folder.
