<?php

// Backup / disposable restored-schema rehearsal / read-only post-install verification.
// This helper never installs migrations or posts sample transactions in the application database.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2); $mode = $argv[1] ?? '';
if (! in_array($mode, ['backup', 'rehearse', 'verify'], true)) { fwrite(STDERR, "Usage: php tests/Support/b2b_deployment_check.php backup|rehearse|verify [private-manifest.json]\n"); exit(1); }
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$app = require $root.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql'); $config['url'] = null;
foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $var) { if (isset($env[$var])) { $config[$key] = $env[$var]; } }
config(['database.default' => 'b2b_deploy', 'database.connections.b2b_deploy' => $config, 'cache.default' => 'array', 'session.driver' => 'array']);
$privateRoot = getenv('USERPROFILE').'/.codex/backups/inventory';
if (!is_dir($privateRoot)) mkdir($privateRoot, 0700, true);
$defaults = $privateRoot.'/.mysql-'.bin2hex(random_bytes(12)).'.cnf';
$quote = fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
$client = "[client]\n";
foreach (['host','port','user','password'] as $option) { $client .= $option.'='.$quote($config[$option === 'user' ? 'username' : $option] ?? '')."\n"; }
file_put_contents($defaults, $client);
$temporaryDatabase = null; $admin = null;

function snapshot(): array
{
    $result = [];
    foreach (['products','variations','variation_location_details','transactions','purchase_lines','transaction_sell_lines','transaction_payments','mfg_production_orders','mfg_production_batches','inventory_movements'] as $table) {
        $hash = hash_init('sha256'); $count = 0;
        foreach (DB::table($table)->orderBy('id')->cursor() as $row) { $fields = (array) $row; unset($fields['sales_channel']); ksort($fields); hash_update($hash, json_encode($fields, JSON_THROW_ON_ERROR)."\n"); $count++; }
        $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
    }
    return $result;
}

function assertSameSnapshot(array $expected): void
{
    if (snapshot() !== $expected) { throw new RuntimeException('Existing records differ from the private backup manifest.'); }
}

function readPage(string $url, array $query = [], bool $json = false): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
{
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'B2B restored-schema verification';
    $request = Illuminate\Http\Request::create($url, 'GET', $query, [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'], 'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html', 'HTTP_X_REQUESTED_WITH' => $json ? 'XMLHttpRequest' : '']);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class); $response = $kernel->handle($request); $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) { throw new RuntimeException('Read-only page check failed: '.$url.' HTTP '.$response->getStatusCode()); }
    return $response;
}

try {
    if ($mode === 'backup') {
        $before = snapshot(); $stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka')))->format('Ymd_His');
        $path = $privateRoot.'/before_b2b_'.$stamp.'.sql';
        $dump = new Process(['C:/xampp/xampp/mysql/bin/mysqldump.exe', '--defaults-extra-file='.$defaults, '--single-transaction', '--quick', '--routines', '--triggers', '--hex-blob', '--no-tablespaces', '--result-file='.$path, $config['database']], $root);
        $dump->setTimeout(180); $dump->mustRun();
        if (!is_file($path) || filesize($path) < 1024) { throw new RuntimeException('The private backup is missing or unexpectedly small.'); }
        assertSameSnapshot($before);
        $manifest = ['dump' => $path, 'dump_sha256' => hash_file('sha256', $path), 'created_at' => $stamp, 'snapshot' => $before];
        file_put_contents(substr($path, 0, -4).'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo 'PASS: private backup and existing-record checksums saved: '.substr($path, 0, -4).'.json'.PHP_EOL;
    } else {
        $manifestPath = $argv[2] ?? ''; $resolved = realpath($manifestPath); $allowed = realpath($privateRoot);
        if (!$resolved || !$allowed || !str_starts_with(strtolower(str_replace('\\', '/', $resolved)), strtolower(str_replace('\\', '/', $allowed)).'/')) { throw new RuntimeException('Use a manifest from the private inventory backup directory.'); }
        $manifest = json_decode(file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
        if (!hash_equals($manifest['dump_sha256'], hash_file('sha256', $manifest['dump']))) { throw new RuntimeException('The private SQL backup checksum changed.'); }
        if ($mode === 'verify') {
            assertSameSnapshot($manifest['snapshot']);
            foreach (['b2b_transfers','b2b_sale_details','b2b_returns','b2b_locations'] as $t) { if (DB::table($t)->exists()) throw new RuntimeException('Unexpected live B2B demonstration records.'); }
            $user = App\User::whereHas('roles', fn ($q) => $q->where('name', 'like', 'Admin#%'))->firstOrFail(); Illuminate\Support\Facades\Auth::setUser($user);
            foreach (['/b2b','/b2b/products','/b2b/inventory','/b2b/transfers','/b2b/customers','/b2b/pos','/b2b/sales','/b2b/dues','/b2b/reports','/b2b/movements','/manufacturing','/products/create'] as $url) { readPage($url); }
            foreach (['products','customers','sales','transfers','summary','movements'] as $resource) { readPage('/b2b/data/'.$resource, [], true); }
            assertSameSnapshot($manifest['snapshot']);
            echo 'PASS: existing-record checksums unchanged, no sample B2B stock/sales, all ten B2B pages and six JSON resources return HTTP 200.'.PHP_EOL;
        } else {
            // Refuse a backup capable of switching the import out of its disposable database.
            $sql = file_get_contents($manifest['dump']);
            if (preg_match('/^(?:CREATE\s+DATABASE|USE\s+)/mi', $sql)) { throw new RuntimeException('Backup must not contain database-switching statements.'); }
            unset($sql);
            $admin = new PDO('mysql:host='.$config['host'].';port='.$config['port'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $temporaryDatabase = 'codex_b2b_verify_'.getmypid().'_'.bin2hex(random_bytes(5));
            $admin->exec('CREATE DATABASE `'.$temporaryDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $import = new Process(['C:/xampp/xampp/mysql/bin/mysql.exe', '--defaults-extra-file='.$defaults, '--database='.$temporaryDatabase, '--binary-mode'], $root); $stream = fopen($manifest['dump'], 'rb'); $import->setInput($stream); $import->setTimeout(180); $import->mustRun(); fclose($stream);
            $clone = $config; $clone['database'] = $temporaryDatabase; config(['database.default' => 'b2b_verify', 'database.connections.b2b_verify' => $clone]);
            assertSameSnapshot($manifest['snapshot']);
            (require $root.'/Modules/B2B/Database/Migrations/2026_10_26_000003_create_b2b_workspace.php')->up();
            assertSameSnapshot($manifest['snapshot']);
            $user = App\User::whereHas('roles', fn ($q) => $q->where('name', 'like', 'Admin#%'))->firstOrFail(); Illuminate\Support\Facades\Auth::setUser($user);
            $location = App\BusinessLocation::where('business_id', $user->business_id)->where('is_active', 1)->firstOrFail();
            $product = (array) DB::table('products')->where('business_id', $user->business_id)->where('enable_stock', 1)->whereIn('type', ['single','variable'])->first();
            if (!$product) throw new RuntimeException('No suitable master product exists for the disposable schema check.');
            $sourceProductId = $product['id']; unset($product['id']);
            $sku = 'VERIFY-B2B-'.bin2hex(random_bytes(5));
            $product = array_merge($product, ['name' => 'B2B restored-schema verification', 'sku' => $sku, 'type' => 'single', 'manufacturing_type' => 'finished_good', 'is_inactive' => 0, 'created_by' => $user->id]);
            $productId = DB::table('products')->insertGetId($product);
            $group = (array) DB::table('product_variations')->where('product_id', $sourceProductId)->first(); unset($group['id']); $group['product_id'] = $productId; $group['name'] = 'DUMMY'; $group['is_dummy'] = 1;
            $groupId = DB::table('product_variations')->insertGetId($group);
            $v = (array) DB::table('variations')->where('product_id', $sourceProductId)->first(); unset($v['id']);
            $v = array_merge($v, ['product_id' => $productId, 'product_variation_id' => $groupId, 'name' => 'DUMMY', 'sub_sku' => $sku, 'deleted_at' => null, 'sell_price_inc_tax' => '999']);
            $variationId = DB::table('variations')->insertGetId($v); $catalog = app(Modules\B2B\Services\Catalog::class); $inventory = app(Modules\B2B\Services\InventoryService::class);
            $variation = $catalog->variation($user, $variationId); $tx = $inventory->transaction($user, $location->id, 'purchase', 'received', 'VERIFY-B2B-OPENING', 'retail');
            $inventory->receiptLine($tx, $variation, '500', '12'); $inventory->change($user, $variation, $location->id, '500');
            DB::table('product_locations')->insert(['product_id' => $productId, 'location_id' => $location->id]);
            $inventory->transfer($user, ['location_id' => $location->id, 'direction' => 'to_b2b', 'items' => [['variation_id' => $variationId, 'quantity' => '200']]], 'restored-schema-transfer');
            if ($catalog->stock($variationId, $location->id) !== '300.0000' || $catalog->stock($variationId, $catalog->bucket($user, $location->id)) !== '200.0000') { throw new RuntimeException('Restored-schema transfer did not preserve quantities.'); }
            $customer = app(Modules\B2B\Services\CustomerService::class)->save($user, ['name' => 'Disposable B2B verification buyer', 'mobile' => 'TEST-ONLY', 'credit_limit' => '10000', 'payment_term_days' => 14, 'is_active' => true], 'restored-schema-customer');
            $sale = app(Modules\B2B\Services\SalesService::class)->checkout($user, ['location_id' => $location->id, 'contact_id' => $customer['contact_id'], 'items' => [['variation_id' => $variationId, 'quantity' => '10', 'unit_price' => '35']]], 'restored-schema-sale');
            if ($catalog->stock($variationId, $location->id) !== '300.0000' || $catalog->stock($variationId, $catalog->bucket($user, $location->id)) !== '190.0000' || $sale['total'] !== '350.0000') { throw new RuntimeException('Restored-schema manually priced sale failed.'); }
            foreach (['/b2b','/b2b/products','/b2b/inventory','/b2b/transfers','/b2b/customers','/b2b/pos','/b2b/sales','/b2b/dues','/b2b/reports','/b2b/movements','/b2b/invoices/'.$sale['transaction_id'],'/manufacturing','/products/create','/products/'.$productId.'/edit'] as $url) { readPage($url); }
            $response = readPage('/products', ['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => [['data' => 'sku', 'name' => 'products.sku', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']]], 'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => $sku, 'regex' => 'false']], true);
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR); $row = collect($data['data'])->first(fn ($r) => (int) $r['id'] === $productId);
            if (!$row || !str_contains(strip_tags($row['current_stock']), '300')) { throw new RuntimeException('The existing product list did not display the reduced main quantity.'); }
            if (\App\Support\Decimal::value((string) DB::table('variations')->where('id', $variationId)->value('sell_price_inc_tax')) !== '999.0000') { throw new RuntimeException('The B2B sale changed the retail price.'); }
            echo 'PASS: restored backup migration preserves existing records; 500 -> 300 main + 200 B2B; manual-price sale leaves main 300 and B2B 190; real pages and existing product list pass.'.PHP_EOL;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL); $exitCode = 1;
} finally {
    if ($temporaryDatabase && preg_match('/^codex_b2b_verify_\d+_[a-f0-9]{10}$/D', $temporaryDatabase)) { DB::purge('b2b_verify'); $admin->exec('DROP DATABASE `'.$temporaryDatabase.'`'); }
    if (is_file($defaults)) unlink($defaults);
}
exit($exitCode ?? 0);
