# Create / Modify a Magento 2 Table (Declarative Schema)

## Purpose
Use this skill to create a new table or alter an existing one in Magento 2.4.x using Declarative Schema (`db_schema.xml`) — the ONLY supported mechanism per AGENTS.md. NEVER use `InstallSchema` / `UpgradeSchema` classes.

## Prerequisites
- Read `AGENTS.md` Section 7.2 — Declarative Schema for ALL database changes
- Read `project-context/05-conventions.md` for table naming convention (e.g. `{vendor}_{entity}`)
- A module already created (use `create-module`)
- CLI access to run `bin/magento setup:db-declaration:generate-whitelist`

## Input
- **Table name** (e.g. `acme_store_location`)
- **Columns** with types
- **Indexes** (for query performance)
- **Foreign keys** (to `store`, `catalog_product_entity`, etc.)

## Generated Files
- `etc/db_schema.xml`
- `etc/db_schema_whitelist.json` (generated, committed)

## Column Type Reference
| Magento type | SQL equivalent | Use for |
|--------------|----------------|---------|
| `int` | INT | IDs, counters |
| `smallint` | SMALLINT | booleans stored as 0/1, status codes |
| `bigint` | BIGINT | large counts, foreign keys to BIGINT entities |
| `decimal` (precision,scale) | DECIMAL(12,4) | prices, quantities |
| `varchar` (length) | VARCHAR(255) | short strings, SKUs |
| `text` | TEXT | unbounded text (descriptions) |
| `boolean` | TINYINT(1) | true/false |
| `timestamp` | TIMESTAMP | created_at / updated_at |
| `date` | DATE | calendar dates |
| `blob` | BLOB | binary |

## Index & Constraint Patterns
```xml
<index referenceId="ACME_STORE_LOCATION_POSTCODE" indexType="btree">
    <column name="postcode"/>
</index>
<constraint xsi:type="primary" referenceId="PRIMARY">
    <column name="location_id"/>
</constraint>
<constraint xsi:type="unique" referenceId="ACME_STORE_LOCATION_NAME">
    <column name="name"/>
</constraint>
<constraint xsi:type="foreign" referenceId="FK_ACME_STORE_LOC_STORE_ID"
            table="acme_store_location" column="store_id"
            referenceTable="store" referenceColumn="store_id"
            onDelete="CASCADE"/>
```

## Step-by-Step

### Step 1: Write `etc/db_schema.xml`
Create the table for "Store Pickup Locations" with address columns, indexes, and FKs to `store` and `catalog_product_entity`.

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="acme_store_location" resource="default" engine="innodb"
           comment="Acme Store Pickup Location">
        <column xsi:type="int" name="location_id" padding="10" unsigned="true" nullable="false"
                identity="true" comment="Location ID"/>
        <column xsi:type="varchar" name="name" length="255" nullable="false"
                comment="Location display name"/>
        <column xsi:type="varchar" name="street" length="255" nullable="false"
                comment="Street address"/>
        <column xsi:type="varchar" name="city" length="100" nullable="false" comment="City"/>
        <column xsi:type="varchar" name="region" length="100" nullable="true" comment="Region/state"/>
        <column xsi:type="varchar" name="postcode" length="20" nullable="false" comment="Postcode"/>
        <column xsi:type="varchar" name="country_id" length="2" nullable="false" comment="ISO country code"/>
        <column xsi:type="decimal" name="latitude" scale="8" precision="11" nullable="true"
                comment="GPS latitude"/>
        <column xsi:type="decimal" name="longitude" scale="8" precision="11" nullable="true"
                comment="GPS longitude"/>
        <column xsi:type="boolean" name="is_active" nullable="false" default="1"
                comment="Is location active"/>
        <column xsi:type="int" name="store_id" padding="10" unsigned="true" nullable="false"
                default="0" comment="Magento store view ID"/>
        <column xsi:type="timestamp" name="created_at" on_update="false" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Created At"/>
        <column xsi:type="timestamp" name="updated_at" on_update="true" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Updated At"/>

        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="location_id"/>
        </constraint>
        <constraint xsi:type="unique" referenceId="ACME_STORE_LOC_NAME_STORE">
            <column name="name"/>
            <column name="store_id"/>
        </constraint>
        <constraint xsi:type="foreign" referenceId="FK_ACME_STORE_LOC_STORE_ID"
                    table="acme_store_location" column="store_id"
                    referenceTable="store" referenceColumn="store_id"
                    onDelete="CASCADE"/>

        <index referenceId="ACME_STORE_LOCATION_POSTCODE" indexType="btree">
            <column name="postcode"/>
        </index>
        <index referenceId="ACME_STORE_LOCATION_CITY" indexType="btree">
            <column name="city"/>
        </index>
        <index referenceId="ACME_STORE_LOCATION_IS_ACTIVE" indexType="btree">
            <column name="is_active"/>
        </index>
    </table>
</schema>
```

### Step 2: Generate the whitelist (CRITICAL — do not skip)
The whitelist tells Magento which columns/types it is allowed to manage. Without it, `setup:upgrade` silently does nothing for your table.

```bash
bin/magento setup:db-declaration:generate-whitelist --module-name=Acme_StorePickup
```
This generates/updates `etc/db_schema_whitelist.json`. Commit it to version control.

### Step 3: Apply the schema
```bash
bin/magento setup:upgrade
bin/magento cache:clean
```

### Step 4: Modify an existing table (add a column later)
Append a `<column>` inside the existing `<table>` block, regenerate the whitelist, then run `setup:upgrade`. To DROP a column or table, remove it from `db_schema.xml`, regenerate the whitelist, and run `setup:upgrade` — declarative schema diff will issue the DROP.

```xml
<!-- Adding a phone column later -->
<column xsi:type="varchar" name="phone" length="32" nullable="true" comment="Phone"/>
```
```bash
bin/magento setup:db-declaration:generate-whitelist --module-name=Acme_StorePickup
bin/magento setup:upgrade
```

## Coding Rules Applied
- **Declarative Schema ONLY** (AGENTS.md 7.2): `db_schema.xml` for create/alter/drop — NEVER `InstallSchema` / `UpgradeSchema`
- **Whitelist generation required**: `setup:db-declaration:generate-whitelist` after every `db_schema.xml` edit, otherwise changes are silently ignored
- **Foreign keys carry `onDelete`**: explicit `CASCADE` / `SET NULL` prevents orphan rows and ambiguous referential behavior
- **`setup:upgrade` applies the diff**: schema is idempotent — running it repeatedly is safe

## Verification
- [ ] `SHOW CREATE TABLE acme_store_location\G` in MySQL shows all columns, keys, and the FK to `store`
- [ ] `bin/magento setup:db-declaration:generate-whitelist --module-name=Acme_StorePickup` exits 0 and `etc/db_schema_whitelist.json` lists every column
- [ ] `bin/magento setup:upgrade` reports `Schema import/exports` with no errors
- [ ] Insert a row then delete the parent `store` row → the location row is CASCADE-deleted (FK verified)
- [ ] `SHOW INDEX FROM acme_store_location` shows the `postcode`, `city`, `is_active` indexes

## Common Mistakes
- **Forgetting the whitelist step**: the most common cause of "table silently not created". `setup:upgrade` runs, no error, no table. Always regenerate the whitelist after editing `db_schema.xml`.
- **Wrong type mapping**: using `int` for a price column instead of `decimal` truncates values. Use `decimal` with explicit `precision`/`scale` for money (typically 12,4).
- **FK name collision**: `referenceId` must be unique across the whole database. Names like `FK_STORE_ID` will collide with Magento core. Prefix with your vendor/entity: `FK_ACME_STORE_LOC_STORE_ID`.
- **Missing `onDelete`**: a FK without `onDelete` defaults to `RESTRICT`, which blocks deleting parent rows and causes cryptic "Cannot delete or update a parent row" errors. Always specify `CASCADE` or `SET NULL`.
- **Not committing `db_schema_whitelist.json`**: the next developer runs `setup:upgrade` and nothing happens because the whitelist is in `.gitignore`. Whitelist is part of the code, commit it.
- **Renaming a column**: declarative schema sees a drop + add and the data is lost. To rename safely, use the `<column>` `disabled="true"` trick or a data patch first; never assume a rename is auto-detected.
- **Running `setup:upgrade` without regenerating whitelist after adding a column**: the new column never appears. Edit XML → regenerate whitelist → setup:upgrade, every time.
