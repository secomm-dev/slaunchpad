#!/usr/bin/env bash
# TASK-YHCJ79 — run verify.js with the request-info password fields rendered.
# sociallogin/general/information_require (config_id 61) is NULL as found; it is
# set to "password" for the run and restored to NULL on exit, even on failure.
# Usage: bash run.sh [vi|en]   (from the project root or this folder)
set -u
ROOT=/var/www/projects/slaunchpad
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LANG_CODE="${1:-vi}"

sql() {
    php -r '$e=include "'"$ROOT"'/app/etc/env.php"; $d=$e["db"]["connection"]["default"];
        $p=new PDO("mysql:host=".$d["host"].";dbname=".$d["dbname"],$d["username"],$d["password"]);
        echo $p->exec($argv[1]), "\n";' "$1"
}
flush() { sudo -u secomm php "$ROOT/bin/magento" cache:flush >/dev/null 2>&1; }

restore() {
    sql 'UPDATE core_config_data SET value=NULL WHERE config_id=61 AND path="sociallogin/general/information_require"' >/dev/null
    flush
    echo "config restored (information_require = NULL)"
}
trap restore EXIT

sql 'UPDATE core_config_data SET value="password" WHERE config_id=61 AND path="sociallogin/general/information_require"' >/dev/null
flush
cd "$DIR" && timeout 1200 node verify.js "$LANG_CODE" 2>&1 | tee "results-$LANG_CODE.txt"
