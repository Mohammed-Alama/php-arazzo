<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Http\Controllers\ArazzoApiController;
use Alama\Arazzo\Laravel\Http\Controllers\WebhookResumeController;
use Alama\Arazzo\Laravel\LaravelArazzoServiceProvider;

it('provides legacy class aliases', function (): void {
    // The service provider's register() method already creates the class aliases
    // This test just verifies they exist and point to the correct classes
    expect(class_exists('Alama\LaravelArazzo\LaravelArazzoServiceProvider'))->toBeTrue()
        ->and(new ReflectionClass('Alama\LaravelArazzo\LaravelArazzoServiceProvider')->getName())->toBe(LaravelArazzoServiceProvider::class)
        ->and(class_exists('Alama\LaravelArazzo\Http\Controllers\ArazzoApiController'))->toBeTrue()
        ->and(new ReflectionClass('Alama\LaravelArazzo\Http\Controllers\ArazzoApiController')->getName())->toBe(ArazzoApiController::class)
        ->and(class_exists('Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController'))->toBeTrue()
        ->and(new ReflectionClass('Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController')->getName())->toBe(WebhookResumeController::class);
});
