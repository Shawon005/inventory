<?php

// InnoDB race tests only: refuse every database outside the disposable namespace.
require __DIR__.'/../../vendor/autoload.php';
if (! preg_match('/^codex_mfg_test_\d+_[a-f0-9]{10}$/D', $argv[1] ?? '')) { exit(1); }
$env = Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/../../.env'));
foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $argv[1], 'DB_HOST' => $env['DB_HOST'], 'DB_PORT' => $env['DB_PORT'] ?? '3306', 'DB_USERNAME' => $env['DB_USERNAME'], 'DB_PASSWORD' => $env['DB_PASSWORD'] ?? '', 'DATABASE_URL' => ''] as $name => $value) {
    putenv($name.'='.$value); $_ENV[$name] = $value; $_SERVER[$name] = $value;
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql'); $config['url'] = null; $config['database'] = $argv[1];
foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $name) { if (isset($env[$name])) { $config[$key] = $env[$name]; } }
config(['database.default' => 'mfg_worker', 'database.connections.mfg_worker' => $config, 'cache.default' => 'array']);
try {
    if ($argv[2] === 'stock') {
        $variation = Illuminate\Support\Facades\DB::table('variations')->where('id', $argv[3])->first();
        app(App\Services\InventoryStockService::class)->change(1, $variation->product_id, $variation->id, 1, App\Support\Decimal::sub('0', $argv[4]));
    } else {
        $user = Mockery::mock(App\User::class)->makePartial(); $user->forceFill(['id' => 1, 'business_id' => 1]);
        $user->shouldReceive('can')->andReturn(true); $user->shouldReceive('permitted_locations')->andReturn('all');
        if ($argv[2] === 'b2b-transfer') {
            app(Modules\B2B\Services\InventoryService::class)->transfer($user, ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => (int) $argv[3], 'quantity' => $argv[4]]]], 'race-transfer-'.$argv[5]);
        } elseif ($argv[2] === 'b2b-sale') {
            app(Modules\B2B\Services\SalesService::class)->checkout($user, ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => (int) $argv[3], 'quantity' => $argv[4], 'unit_price' => '10']]], 'race-sale-'.$argv[3].'-'.$argv[5]);
        } else {
            app(Modules\Manufacturing\Services\ProductionService::class)->start($user, (int) $argv[3], ['quantity' => $argv[4]], 'concurrent-start-001');
        }
    }
    fwrite(STDOUT, 'worker-posted'.PHP_EOL); exit(0);
} catch (Illuminate\Validation\ValidationException $e) { fwrite(STDOUT, 'worker-rejected'.PHP_EOL); exit(2); }
catch (Throwable $e) { fwrite(STDERR, get_class($e).': '.$e->getMessage()); exit(1); }
