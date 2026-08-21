<?php

declare(strict_types=1);

it('confirms ArazzoRequestCompiler is entirely absent', function () {
    expect(class_exists('Alama\\Arazzo\\Runner\\ArazzoRequestCompiler'))->toBeFalse();
    expect(interface_exists('Alama\\Arazzo\\Runner\\Contracts\\RequestCompilerInterface'))->toBeFalse();
});
