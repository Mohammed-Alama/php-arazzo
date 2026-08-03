<?php

declare(strict_types=1);
use Alama\Arazzo\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class)->in(__DIR__);

uses(RefreshDatabase::class)->in('Persistence');
