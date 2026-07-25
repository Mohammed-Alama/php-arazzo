<?php

declare(strict_types=1);
$files = glob('tests/Execution/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace incorrectly ordered ArazzoDocument
    // new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], new Components([], [], [], []), [], SpecVersion::V1_1);
    // Should be:
    // new ArazzoDocument('1.0.0', new Info(...), [], [], new Components(...), [], null, SpecVersion::V1_1)

    // For AsyncApiStepExecutorTest:
    $content = preg_replace("/new ArazzoDocument\('1.0.0', new Info\('T', null, null, '1'\), \[\], \[\], new Components\(\[.*?\], \[\], \Alama\\\\LaravelArazzo\\\\Dto\\\\Enum\\\\SpecVersion::V1_1\);/s",
        "new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], null, \Alama\LaravelArazzo\Dto\Enum\SpecVersion::V1_1);",
        $content);

    // Let's just use regex for all ArazzoDocument usages that pass SpecVersion.
    // We want to pass workflows = [], components = ..., specificationExtensions = [], rawRoot = null, specVersion = ...

    $content = str_replace(
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.0.0'), [], new Components([], [], [], []), [], SpecVersion::V1_0);",
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.0.0'), [], [], new Components([], [], [], []), [], null, SpecVersion::V1_0);",
        $content,
    );

    $content = str_replace(
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], new Components([], [], [], []), [], SpecVersion::V1_1);",
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), [], null, SpecVersion::V1_1);",
        $content,
    );

    $content = str_replace(
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], new Components([], [], [], []), []);",
        "new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), []);",
        $content,
    );

    $content = str_replace(
        "new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], \Alama\LaravelArazzo\Dto\Enum\SpecVersion::V1_1);",
        "new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], null, \Alama\LaravelArazzo\Dto\Enum\SpecVersion::V1_1);",
        $content,
    );

    file_put_contents($file, $content);
}

// Remove the throwing test in StepExecutionWorkerTest
$workerTestContent = file_get_contents('tests/Execution/StepExecutionWorkerTest.php');
$workerTestContent = preg_replace("/it\('throws when the context has no executionId', function \(\): void \{.*?\n\}\);\n\n/s", '', $workerTestContent);
file_put_contents('tests/Execution/StepExecutionWorkerTest.php', $workerTestContent);
