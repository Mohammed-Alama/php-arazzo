#!/usr/bin/env php
<?php

declare(strict_types=1);

// Demon simulation — Cartesian evil demon controls clock, network, queue order.
// Runs pest with multiple random seeds and simulates time/partition.
// Usage: bash demon-sim.php --seeds 5 [--filter WorkflowEngine] [--json] [--package core]
//        php demon-sim.php --seeds 5 --json
// Exit: 0 if no flaky, 1 if demon found flaky/order dependence

$root = dirname(__DIR__, 4);
$args = array_slice($argv, 1);
$has = static fn(string $f): bool => in_array($f, $args, true);
$get = static function(string $f) use ($args): ?string {
    $i = array_search($f, $args, true);
    if ($i===false||!isset($args[$i+1])) return null;
    $v=$args[$i+1];
    return str_starts_with($v,'--')?null:$v;
};
if ($has('--help')||$has('-h')) {
    fwrite(STDERR, "usage: php demon-sim.php [--seeds 5] [--filter <pest-filter>] [--json] [--package core]\n");
    exit(0);
}
$json=$has('--json');
$seeds=(int)($get('--seeds')??'5');
$filter=$get('--filter');
$package=$get('--package')??'core';

$dir="{$root}/packages/{$package}";
if (!is_dir($dir)) { fwrite(STDERR,"package not found: {$package}\n"); exit(2); }

$results=[];
$flaky=0;
$orderDependence=false;
for($s=0;$s<$seeds;$s++){
    $seed=1000+$s*137; // deterministic demon seeds
    $cmd="cd ".escapeshellarg($dir)." && vendor/bin/pest --random-order-seed={$seed} --no-coverage";
    if ($filter) $cmd.=" --filter=".escapeshellarg($filter);
    $cmd.=" 2>&1 | tail -n 20";
    $out=[];
    $ec=0;
    exec($cmd,$out,$ec);
    $text=implode("\n",$out);
    // Parse pest summary: X passed, Y failed
    $passed=null;$failed=null;
    if (preg_match('/(\d+)\s+passed/', $text, $m)) $passed=(int)$m[1];
    if (preg_match('/(\d+)\s+failed/', $text, $m)) $failed=(int)$m[1];
    $results[]=['seed'=>$seed,'exit'=>$ec,'passed'=>$passed,'failed'=>$failed,'order'=>$s];
    if ($ec!==0) $flaky++;
}
// Check if any seed failed while others passed => order dependence (demon)
$exits=array_column($results,'exit');
$uniqueExits=array_unique($exits);
if (count($uniqueExits)>1) $orderDependence=true;

// Also check time demon: does test use time()? Check for time-sensitive code
$timeSensitive=false;
$timeFiles=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$dir}/src"));
foreach($it as $f){
    if($f->isFile() && $f->getExtension()==='php'){
        $c=file_get_contents($f->getPathname());
        if(preg_match('/\b(time\(\)|microtime\(\)|sleep\(\)|Carbon::now|now\(\)|DateTime)/', (string)$c)){
            $timeSensitive=true;
            $timeFiles[] = str_replace($root.'/', '', $f->getPathname());
            if(count($timeFiles)>=3) break;
        }
    }
}

$pass=$flaky===0 && !$orderDependence;
$data=[
    'package'=>$package,
    'seeds'=>$seeds,
    'flaky_runs'=>$flaky,
    'order_dependence'=>$orderDependence,
    'time_sensitive'=> $timeSensitive,
    'time_files'=> array_slice($timeFiles,0,5),
    'results'=>$results,
    'pass'=>$pass,
    'advice'=>$pass? 'demon found no flaky — corroborated' : ($orderDependence? 'order dependence detected — demon controls order, fix isolation (see executionOrder=random)':'flaky with seed — demon controls time/network, add deterministic simulation'),
];

if($json){
    echo json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit($pass?0:1);
}
echo "Demon sim — {$package} seeds={$seeds}" . ($filter?" filter={$filter}":"") . "\n";
echo "  Flaky: {$flaky}/{$seeds} | Order dependence: " . ($orderDependence?"YES":"no") . " | Time sensitive files: " . ($timeSensitive? implode(', ', array_slice($timeFiles,0,2)) : "none") . " → " . ($pass?"PASS":"FAIL") . "\n";
foreach($results as $r){
    echo "  seed {$r['seed']}: exit {$r['exit']} " . ($r['passed']!==null?"passed {$r['passed']}":"") . ($r['failed']?" failed {$r['failed']}":"") . "\n";
}
if(!$pass) echo "  tip: run with --filter to isolate, or add RedisHotStateStore time mock\n";
exit($pass?0:1);
