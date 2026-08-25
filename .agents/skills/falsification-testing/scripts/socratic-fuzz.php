<?php

declare(strict_types=1);

// Socratic fuzz — Hegelian agon: mutate a valid Arazzo YAML into grue variants
// and run FixtureHarness / Validator to see if tests kill them.
// Usage: php socratic-fuzz.php [--iterations 50] [--source LoginAndRetrievePets.arazzo.yaml] [--json] [--package core]
// Exit: 0 if fuzz killed >= threshold, 1 if not hostile enough, 2 usage

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
    fwrite(STDERR, "usage: php socratic-fuzz.php [--iterations 50] [--source LoginAndRetrievePets.arazzo.yaml] [--json] [--package core]\n");
    exit(0);
}
$json=$has('--json');
$iterations=(int)($get('--iterations')??'50');
$source=$get('--source')??'LoginAndRetrievePets.arazzo.yaml';
$package=$get('--package')??'core';

$srcPath = "{$root}/{$source}";
if (!file_exists($srcPath)) $srcPath = "{$root}/packages/{$package}/tests/Conformance/corpus/oai/1.0.0/LoginAndRetrievePets.arazzo.yaml";
if (!file_exists($srcPath)) $srcPath = "{$root}/LoginAndRetrievePets.arazzo.yaml";
if (!file_exists($srcPath)) {
    fwrite(STDERR, "error: source not found: {$source}\n");
    exit(2);
}
$baseYaml=file_get_contents($srcPath);
if ($baseYaml===false) { fwrite(STDERR,"cannot read source\n"); exit(2); }

// Mutators — each is a grue variant that should be rejected or handled
$mutators = [
    'duplicate_stepId' => fn(string $y): string => preg_replace('/stepId:\s*loginStep/', "stepId: loginStep\n      - stepId: loginStep", $y, 1),
    'cycle_dependsOn' => fn(string $y): string => str_replace('steps:', "workflows:\n  - workflowId: evil\n    steps:\n      - stepId: a\n        dependsOn: [b]\n      - stepId: b\n        dependsOn: [a]\nsteps:", $y),
    'missing_source' => fn(string $y): string => str_replace('sourceDescriptions:', "sourceDescriptions: []\n# removed:", $y),
    'empty_workflow' => fn(string $y): string => preg_replace('/workflows:\s*\n.*steps:/s', "workflows:\n  - workflowId: empty\n    steps: []\n# original:", $y),
    'negative_maxSteps' => fn(string $y): string => $y . "\n# x-maxSteps: -1\n",
    'null_stepId' => fn(string $y): string => str_replace('stepId: loginStep', 'stepId: ""', $y),
    'goto_missing_target' => fn(string $y): string => str_replace('onSuccess:', "onSuccess:\n        - type: goto\n          stepId: doesNotExist\n      onSuccess:", $y),
    'retry_exhaustion' => fn(string $y): string => $y . "\nonFailure:\n  - type: retry\n    retryAfter: 0\n    retryLimit: 1000\n",
    'unicode_stepId' => fn(string $y): string => str_replace('loginStep', "loginStep-🚀-unicode", $y),
    '10MB_yaml' => fn(string $y): string => $y . str_repeat("\n# padding: " . str_repeat("x", 1000), 100),
];

$keys=array_keys($mutators);
$results=[];
$killed=0;
$survived=0;
for($i=0;$i<$iterations;$i++){
    $mutKey=$keys[$i % count($keys)];
    $mut=$mutators[$mutKey];
    $yaml=$mut($baseYaml);
    // Write to temp and try to parse/validate — killed if parse or validate fails (adversary caught)
    $tmp=tempnam(sys_get_temp_dir(),'arazzo-fuzz-');
    file_put_contents($tmp,$yaml);
    $killedThis=false;
    // Try loader + parser + validator if available
    $code=0;
    $out=[];
    // Use php one-liner to try parsing
    $escTmp=escapeshellarg($tmp);
    $checkCmd="php -r 'require \"{$root}/vendor/autoload.php\"; use Alama\\Arazzo\\Parser\\Loader; use Alama\\Arazzo\\Parser\\Parser; use Alama\\Arazzo\\Parser\\Decoders\\NativeJsonDecoder; use Alama\\Arazzo\\Parser\\Decoders\\SymfonyYamlDecoder; try { \$l=new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder()); \$d=(new Parser())->parse(\$l->load('{$tmp}')); echo \"PARSE_OK\"; } catch (Throwable \$e) { echo \"PARSE_FAIL:\".\$e->getMessage(); exit(1); }' 2>&1";
    exec($checkCmd,$out,$code);
    $outStr=implode("\n",$out);
    if ($code!==0 || str_contains($outStr,'PARSE_FAIL')) {
        $killedThis=true; // parse rejection is a kill (adversary caught)
    } else {
        // If parse ok, try validator
        $valCmd="php -r 'require \"{$root}/vendor/autoload.php\"; use Alama\\Arazzo\\Parser\\Loader; use Alama\\Arazzo\\Parser\\Parser; use Alama\\Arazzo\\Parser\\Decoders\\NativeJsonDecoder; use Alama\\Arazzo\\Parser\\Decoders\\SymfonyYamlDecoder; use Alama\\Arazzo\\Validator\\Validator; use Alama\\Arazzo\\Validator\\RuleSet; \$l=new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder()); \$doc=(new Parser())->parse(\$l->load('{$tmp}')); \$r=(new Validator(RuleSet::default()))->validate(\$doc); echo \$r->isValid()?\"VALID\":\"INVALID:\".count(\$r->errors);' 2>&1";
        $out2=[]; $code2=0;
        exec($valCmd,$out2,$code2);
        $out2Str=implode("\n",$out2);
        if (str_contains($out2Str,'INVALID')) $killedThis=true;
        else $killedThis=false; // survived = valid but mutated — should be invalid, so survived is bad (test gap)
        // For mutators that are not necessarily invalid (e.g. unicode), surviving is ok — don't penalize
        if (in_array($mutKey,['unicode_stepId','10MB_yaml'],true) && str_contains($out2Str,'VALID')) $killedThis=false;
    }
    unlink($tmp);
    if ($killedThis) $killed++; else $survived++;
    $results[]=['iteration'=>$i+1,'mutator'=>$mutKey,'killed'=>$killedThis];
    if ($i>=20 && $iterations>50) { /* keep fast */ }
}

$killRate=$iterations>0? round($killed/$iterations,3):0;
$pass=$killRate>=0.5; // at least half should be killed (hostile enough)
$outData=[
    'source'=>$source,
    'iterations'=>$iterations,
    'killed'=>$killed,
    'survived'=>$survived,
    'kill_rate'=>$killRate,
    'pass'=>$pass,
    'mutators'=>array_count_values(array_column($results,'mutator')),
    'results'=>array_slice($results,0,10),
    'advice'=>$pass? 'agon severe — fuzz hostile enough':'fuzz not hostile enough — add mutators or validator rules',
];

if($json){
    echo json_encode($outData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit($pass?0:1);
}
echo "Socratic fuzz — {$iterations} iterations from {$source}\n";
echo "  Killed: {$killed} / {$iterations} ({$killRate}) | Survived: {$survived} → " . ($pass?"PASS":"FAIL") . "\n";
foreach(array_count_values(array_column($results,'mutator')) as $k=>$cnt){
    echo "  {$k}: {$cnt}\n";
}
echo "  " . ($pass?"":"tip: surviving grue variants are gaps — add validator rule or test") . "\n";
exit($pass?0:1);
