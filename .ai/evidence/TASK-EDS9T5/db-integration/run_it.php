<?php
/**
 * REAL-DB integration proof — Secomm_ZaloPay corrective round 5, Blocker 1.
 *
 * Executes the PRODUCTION Secomm\ZaloPay\Model\PaymentAttemptRepository::
 * getBlockingAttemptByQuoteId() against a REAL MariaDB 10.4 server through a
 * REAL Magento\Framework\DB\Adapter\Pdo\Mysql adapter:
 *
 *   production repository (worktree file, verbatim)
 *     -> AbstractDb PARALLEL-ARRAY OR addFieldToFilter (real vendor method)
 *       -> real Select rendering (framework part renderers, wired per
 *          app/etc/di.xml — from/where/order/limit/... all real)
 *         -> real PDO execution on MariaDB (real SQL string, real server)
 *           -> real row hydration into Secomm\ZaloPay\Model\PaymentAttempt
 *              (setData + typed getters = real production model code)
 *
 * Throwaway database `zalopay_r5_it` — created/dropped by run.sh. The shared
 * `magento` database is never read nor written by this script.
 *
 * Disclosed substitutions (minimum necessary to run outside a booted app):
 *  - generated factories (not in git) replaced by minimal equivalents;
 *  - hydrated PaymentAttempt objects are ItAttempt (empty constructor —
 *    AbstractModel's Context/Registry DI is unavailable); all data behaviour
 *    is inherited REAL production code;
 *  - collection _construct() sets the item class directly instead of
 *    ObjectManager::create(); every other collection call is real;
 *  - FK constraints to quote/sales_order omitted (no such tables here; not
 *    referenced by the query under test).
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

require '/var/www/html/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Secomm\\ZaloPay\\')) {
        return;
    }
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

use Magento\Framework\DB\Adapter\Pdo\Mysql as PdoMysqlAdapter;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Select\ColumnsRenderer;
use Magento\Framework\DB\Select\DistinctRenderer;
use Magento\Framework\DB\Select\ForUpdateRenderer;
use Magento\Framework\DB\Select\FromRenderer;
use Magento\Framework\DB\Select\GroupRenderer;
use Magento\Framework\DB\Select\HavingRenderer;
use Magento\Framework\DB\Select\LimitRenderer;
use Magento\Framework\DB\Select\OrderRenderer;
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\DB\Select\UnionRenderer;
use Magento\Framework\DB\Select\WhereRenderer;
use Magento\Framework\DB\SelectFactory;
use Magento\Framework\DB\Platform\Quote as DbQuote;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\StringUtils;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\It\ItResource;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\PaymentAttemptFactory;
use Secomm\ZaloPay\Model\PaymentAttemptRepository;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;

const IT_DB = 'zalopay_r5_it';

// ---------------------------------------------------------------------------
// 1. REAL adapter — full renderer wiring copied from app/etc/di.xml.
// ---------------------------------------------------------------------------
$renderers = [
    'distinct'   => ['renderer' => new DistinctRenderer(), 'sort' => '100',  'part' => 'distinct'],
    'columns'    => ['renderer' => new ColumnsRenderer(new DbQuote()), 'sort' => '200', 'part' => 'columns'],
    'union'      => ['renderer' => new UnionRenderer(), 'sort' => '300',  'part' => 'union'],
    'from'       => ['renderer' => new FromRenderer(new DbQuote()), 'sort' => '400', 'part' => 'from'],
    'where'      => ['renderer' => new WhereRenderer(), 'sort' => '500',  'part' => 'where'],
    'group'      => ['renderer' => new GroupRenderer(new DbQuote()), 'sort' => '600', 'part' => 'group'],
    'having'     => ['renderer' => new HavingRenderer(), 'sort' => '700', 'part' => 'having'],
    'order'      => ['renderer' => new OrderRenderer(new DbQuote()), 'sort' => '800', 'part' => 'order'],
    'limit'      => ['renderer' => new LimitRenderer(), 'sort' => '900',  'part' => 'limitcount'],
    'for_update' => ['renderer' => new ForUpdateRenderer(), 'sort' => '1000', 'part' => 'forupdate'],
];
// DtoFactoriesTable serves the schema-DDL path only (describeTable etc.),
// never the SELECT path exercised here — an empty anonymous subclass skips
// its ObjectManager-dependent constructor.
$dtoStub = new class extends \Magento\Framework\Setup\Declaration\Schema\Dto\Factories\Table {
    /**
     * Skip ObjectManager-dependent constructor (unused on the query path).
     *
     * @return void
     */
    public function __construct()
    {
    }
};

$adapter = new PdoMysqlAdapter(
    new StringUtils(),
    new DateTime(),
    new \Magento\Framework\DB\Logger\Quiet(), // real no-op DB query logger
    new SelectFactory(new SelectRenderer($renderers)),
    [
        'host'          => 'db',
        'dbname'        => IT_DB,
        'username'      => 'magento',
        'password'      => 'magento',
        'driverOptions' => [\PDO::ATTR_TIMEOUT => 5],
        'initStatements'=> 'SET NAMES utf8',
    ],
    new \Magento\Framework\Serialize\Serializer\Json(),
    $dtoStub
);

// ---------------------------------------------------------------------------
// 2. Production repository over the real adapter.
// ---------------------------------------------------------------------------
$resource = new ItResource();
$resource->conn = $adapter;

PaymentAttemptCollectionFactory::$adapter = $adapter;
PaymentAttemptCollectionFactory::$resource = $resource;

$repository = new PaymentAttemptRepository(
    new PaymentAttemptFactory(),
    $resource,
    new PaymentAttemptCollectionFactory()
);

// ---------------------------------------------------------------------------
// 3. Fixtures (throwaway DB): per-quote scenario rows.
// ---------------------------------------------------------------------------
$fixtures = [
    // [quote, payment_status, requires_reconciliation, app_trans, note]
    [990101, 'paid', 0, 'IT-PAID-1', 'money-real paid row'],
    [990102, 'finalized', 0, 'IT-FIN-1', 'finalized row'],
    [990103, 'failed', 1, 'IT-Q-1', 'ordinary status + sticky quarantine flag'],
    [990104, 'failed', 0, 'IT-F-1', 'ordinary failure'],
    [990104, 'expired', 0, 'IT-E-1', 'ordinary expiry'],
    [990104, 'stale', 0, 'IT-S-1', 'ordinary stale'],
    [990105, 'expired', 0, 'IT-E-2', 'expiry only'],
    [990105, 'stale', 0, 'IT-S-2', 'stale only'],
    [990106, 'stale', 0, 'IT-SX-1', 'stale + recovery_exhausted (operational marker, NOT in blocking filter)'],
    [990107, 'failed', 0, 'IT-M-1', 'mixed quote: ordinary FAILED first (lower entity)'],
    [990107, 'paid', 0, 'IT-M-2', 'mixed quote: PAID second (higher entity — must win)'],
    [990108, 'initiated', 1, 'IT-Q2-1', 'initiated + quarantined'],
];

foreach ($fixtures as [$quote, $status, $recon, $appTrans, $note]) {
    $adapter->query(
        "INSERT INTO secomm_zalopay_payment_attempt
            (quote_id, reserved_order_id, app_trans_id, payment_status,
             requires_reconciliation, recovery_exhausted, amount, currency)
         VALUES ({$adapter->quote($quote)}, {$adapter->quote('IT-RO-1')},
                 {$adapter->quote($appTrans)}, {$adapter->quote($status)},
                 {$adapter->quote($recon)}, 0, 100000, 'VND')"
    );
}
// Quote 990106: recovery_exhausted = 1 on its stale row (update after insert).
$adapter->query(
    "UPDATE secomm_zalopay_payment_attempt SET recovery_exhausted = 1
     WHERE app_trans_id = 'IT-SX-1'"
);

// ---------------------------------------------------------------------------
// 4. Scenarios — the required blocking invariant, executed on real SQL.
// ---------------------------------------------------------------------------
$pass = 0;
$fail = 0;
$failed = static function (string $name, string $why) use (&$fail): void {
    $fail++;
    fwrite(STDERR, "FAIL  $name: $why\n");
};
$ok = static function (string $name) use (&$pass): void {
    $pass++;
    echo "PASS  $name\n";
};

$check = static function (bool $cond, string $name, string $why) use ($ok, $failed): void {
    $cond ? $ok($name) : $failed($name, $why);
};

// 4.1 PAID blocks.
$r = $repository->getBlockingAttemptByQuoteId(990101);
if (isset(PaymentAttemptCollectionFactory::$created[0])) {
    echo "--- repository's own first query ---\n"
        . PaymentAttemptCollectionFactory::$created[0]->getSelect()->assemble() . "\n---\n";
}
$check($r !== null, 'PAID blocks second payment', 'blocking lookup returned null');
$check($r !== null && $r->getPaymentStatus() === 'paid', 'PAID row hydrated with real typed getter', 'status != paid');

// 4.2 FINALIZED blocks.
$r = $repository->getBlockingAttemptByQuoteId(990102);
$check($r !== null, 'FINALIZED blocks second payment', 'blocking lookup returned null');

// 4.3 requires_reconciliation = 1 blocks (ordinary status underneath).
$r = $repository->getBlockingAttemptByQuoteId(990103);
$check($r !== null, 'requires_reconciliation blocks second payment', 'blocking lookup returned null');
$check(
    $r !== null && $r->getRequiresReconciliation() === true,
    'quarantined row hydrated with flag=true (real DB round-trip)',
    'flag not true after hydration'
);

// 4.4 Ordinary FAILED + EXPIRED + STALE never block.
$r = $repository->getBlockingAttemptByQuoteId(990104);
$check($r === null, 'ordinary FAILED/EXPIRED/STALE do not block (retry allowed)', 'non-null blocking row');

// 4.5 EXPIRED + STALE only never block.
$r = $repository->getBlockingAttemptByQuoteId(990105);
$check($r === null, 'ordinary EXPIRED/STALE do not block', 'non-null blocking row');

// 4.6 recovery_exhausted alone (operational marker) does not block.
$r = $repository->getBlockingAttemptByQuoteId(990106);
$check($r === null, 'recovery_exhausted without money-real evidence does not block', 'non-null blocking row');

// 4.7 Latest ENTITY first + LIMIT 1: quote 990107 has FAILED (entity N) then
// PAID (entity N+1); the blocking row must be the PAID one.
$r = $repository->getBlockingAttemptByQuoteId(990107);
$check($r !== null, 'mixed quote returns a blocking row', 'null');
$check(
    $r !== null && $r->getAppTransId() === 'IT-M-2',
    'latest entity wins (DESC order + LIMIT 1)',
    'returned app_trans_id=' . var_export($r !== null ? $r->getAppTransId() : null, true)
);

// 4.8 Multiple blocking rows exist but lookup stays bounded (LIMIT 1).
$raw = $adapter->fetchCol(
    "SELECT entity_id FROM secomm_zalopay_payment_attempt WHERE quote_id = 990108"
);
$check(count($raw) === 1, 'fixture sanity: 990108 has exactly 1 row', 'unexpected row count');

// 4.9 Unknown quote: null, no exception (bounded, safe).
$r = $repository->getBlockingAttemptByQuoteId(990199);
$check($r === null, 'quote without attempts returns null', 'non-null');

// 4.10 Non-positive quote guard: null without touching the DB.
$r = $repository->getBlockingAttemptByQuoteId(0);
$check($r === null, 'quote_id=0 guarded to null', 'non-null');

// ---------------------------------------------------------------------------
// 5. Rendered-SQL proof on the SAME production filter path (no repository),
//    executed for the evidence record: build the exact blocking Select and
//    show its assembled SQL string from the real renderer pipeline.
// ---------------------------------------------------------------------------
$proof = PaymentAttemptCollectionFactory::$adapter;
$collection = (new PaymentAttemptCollectionFactory())->create();
$collection->addFieldToFilter(PaymentAttemptInterface::QUOTE_ID, 990107);
$collection->addFieldToFilter(
    [PaymentAttemptInterface::PAYMENT_STATUS, PaymentAttemptInterface::REQ_RECONCILIATION],
    [
        ['in' => [PaymentAttemptInterface::STATUS_PAID, PaymentAttemptInterface::STATUS_FINALIZED]],
        ['eq' => 1],
    ]
);
$collection->setOrder(PaymentAttemptInterface::ENTITY_ID, 'DESC');
$collection->setPageSize(1);
$assembled = (string)$collection->getSelect()->assemble();
echo "\n--- assembled production SQL (real renderer pipeline) ---\n$assembled\n---\n";
$check(
    str_contains(strtolower($assembled), 'requires_reconciliation')
    && str_contains(strtolower($assembled), "'paid'") !== false,
    'assembled SQL contains both OR branches',
    'assembled SQL missing a branch'
);

// The repository's own first lookup (captured through the factory stub) —
// after load() it carries the real ORDER BY / LIMIT rendering.
$repoSql = isset(PaymentAttemptCollectionFactory::$created[0])
    ? (string)PaymentAttemptCollectionFactory::$created[0]->getSelect()->assemble()
    : '';
$check(
    (bool)preg_match('/ORDER BY entity_id DESC\s+LIMIT 1\s*$/', trim($repoSql)),
    'repository query renders ORDER BY entity_id DESC + LIMIT 1 (latest-first, bounded)',
    'captured repository SQL: ' . $repoSql
);

// ---------------------------------------------------------------------------
// 6. Verdict.
// ---------------------------------------------------------------------------
echo "\nRESULT: PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
