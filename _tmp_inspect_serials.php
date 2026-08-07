<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$grns = DB::table('grns')->where('created_at', '>=', '2026-08-07 00:00:00')->select('id','grn_number','batch_number','created_at')->get();
echo "today's grns: ".json_encode($grns).PHP_EOL;

$items = DB::table('grn_items')->whereIn('grn_id', $grns->pluck('id'))->select('id','grn_id','product_id','product_variant_id')->get();
echo "their items: ".json_encode($items).PHP_EOL;

$serials = DB::table('grn_item_serials')->whereIn('grn_item_id', $items->pluck('id'))->get();
echo "their serials: ".json_encode($serials).PHP_EOL;
