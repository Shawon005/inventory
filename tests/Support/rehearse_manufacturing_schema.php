<?php

// Apply only bundled CREATE/ALTER definitions, never INSERTs or the application's schema.
require __DIR__.'/../../vendor/autoload.php';
$root = dirname(__DIR__, 2);
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$name = 'codex_mfg_rehearsal_'.getmypid().'_'.bin2hex(random_bytes(5));
$pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.($env['DB_PORT'] ?? '3306'), $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false; $exitCode = 1;
try {
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created = true;
    $pdo->exec('USE `'.$name.'`');
    $sql = file_get_contents($root.'/db/invantory (1).sql');
    preg_match_all('/^CREATE TABLE `[^`]+` \(.*?^\) ENGINE=[^;]+;/ms', $sql, $creates);
    preg_match_all('/^ALTER TABLE `[^`]+`\s+[^;]+;/ms', $sql, $alters);
    foreach (array_merge($creates[0], $alters[0]) as $statement) { $pdo->exec($statement); }
    foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $name, 'DATABASE_URL' => ''] as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $value; $_SERVER[$key] = $value; }
    $app = require $root.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $config = config('database.connections.mysql'); $config['database'] = $name; $config['url'] = null;
    foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $var) { if (isset($env[$var])) { $config[$key] = $env[$var]; } }
    config(['database.default' => 'mfg_rehearsal', 'database.connections.mfg_rehearsal' => $config, 'cache.default' => 'array']);
    $foundation = require $root.'/database/migrations/2026_10_25_000001_add_manufacturing_stock_foundation.php';
    $manufacturing = require $root.'/Modules/Manufacturing/Database/Migrations/2026_10_25_000002_create_manufacturing_tables.php';
    $b2b = require $root.'/Modules/B2B/Database/Migrations/2026_10_26_000003_create_b2b_workspace.php';
    $foundation->up(); $manufacturing->up(); $b2b->up();
    $tables = Illuminate\Support\Facades\Schema::hasTable('mfg_production_orders') && Illuminate\Support\Facades\Schema::hasTable('inventory_movements');
    if (! $tables) { throw new RuntimeException('Expected manufacturing tables were not installed.'); }
    $b2b->down(); $manufacturing->down(); $foundation->down();
    if (Illuminate\Support\Facades\Schema::hasColumn('products', 'manufacturing_type')) { throw new RuntimeException('Empty rollback did not restore the core schema.'); }
    echo 'PASS: '.count($creates[0]).' bundled core table definitions; manufacturing and B2B migrations apply and empty rollback succeeds.'.PHP_EOL;
    $exitCode = 0;
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
} finally {
    if ($created && preg_match('/^codex_mfg_rehearsal_\d+_[a-f0-9]{10}$/D', $name)) { $pdo->exec('DROP DATABASE `'.$name.'`'); }
}
exit($exitCode);
