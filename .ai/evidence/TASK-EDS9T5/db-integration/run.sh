#!/usr/bin/env zsh
# Driver: real-DB integration proof for getBlockingAttemptByQuoteId().
# Creates a THROWAWAY database, runs the production repository against real
# MariaDB inside the phpfpm container, drops the database. Never touches the
# shared `magento` database or any file outside /tmp in the container.
set -euo pipefail

W=/home/thanhle/Sites/Secomm/slaunchpad/compose/slaunchpad-workspaces/zalopay-payment-first
HOSTDIR=/tmp/zalopay-r5/it
CTDIR=/tmp/zalopay_it
DB=zalopay_r5_it

echo "=== 1. Create throwaway database $DB (root, db container) ==="
docker exec slaunchpad-db-1 mariadb -uroot -pmagento -e "CREATE DATABASE IF NOT EXISTS $DB; GRANT ALL PRIVILEGES ON $DB.* TO 'magento'@'%'; FLUSH PRIVILEGES;" 2>/dev/null

echo "=== 2. Apply DDL (from db_schema.xml, FKs omitted) ==="
docker exec -i slaunchpad-db-1 mariadb -uroot -pmagento "$DB" < "$HOSTDIR/ddl.sql" 2>/dev/null

echo "=== 3. Copy production worktree classes (verbatim) + scaffolding into container /tmp ==="
docker exec slaunchpad-phpfpm-1 rm -rf "$CTDIR"
docker exec slaunchpad-phpfpm-1 mkdir -p "$CTDIR/classes/Secomm/ZaloPay"
docker cp "$W/app/code/Secomm/ZaloPay/Api" slaunchpad-phpfpm-1:"$CTDIR/classes/Secomm/ZaloPay/Api"
docker exec slaunchpad-phpfpm-1 mkdir -p "$CTDIR/classes/Secomm/ZaloPay/Model/ResourceModel/PaymentAttempt"
docker cp "$W/app/code/Secomm/ZaloPay/Model/PaymentAttempt.php" slaunchpad-phpfpm-1:"$CTDIR/classes/Secomm/ZaloPay/Model/PaymentAttempt.php"
docker cp "$W/app/code/Secomm/ZaloPay/Model/PaymentAttemptRepository.php" slaunchpad-phpfpm-1:"$CTDIR/classes/Secomm/ZaloPay/Model/PaymentAttemptRepository.php"
docker cp "$W/app/code/Secomm/ZaloPay/Model/ResourceModel/PaymentAttemptResource.php" slaunchpad-phpfpm-1:"$CTDIR/classes/Secomm/ZaloPay/Model/ResourceModel/PaymentAttemptResource.php"
docker cp "$W/app/code/Secomm/ZaloPay/Model/ResourceModel/PaymentAttempt/PaymentAttemptCollection.php" slaunchpad-phpfpm-1:"$CTDIR/classes/Secomm/ZaloPay/Model/ResourceModel/PaymentAttempt/PaymentAttemptCollection.php"
docker cp "$HOSTDIR/classes" slaunchpad-phpfpm-1:"$CTDIR/"
docker cp "$HOSTDIR/run_it.php" slaunchpad-phpfpm-1:"$CTDIR/run_it.php"

echo "=== 4. Run integration scenarios against real MariaDB ==="
set +e
docker exec slaunchpad-phpfpm-1 php "$CTDIR/run_it.php"
RC=$?
set -e

echo "=== 5. Drop throwaway database ==="
docker exec slaunchpad-db-1 mariadb -uroot -pmagento -e "DROP DATABASE IF EXISTS $DB;" 2>/dev/null

echo "=== 6. Cleanup container /tmp ==="
docker exec slaunchpad-phpfpm-1 rm -rf "$CTDIR"

exit $RC
