<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = \App\Models\User::first() ?? \App\Models\User::factory()->create();
echo $u->createToken('test')->plainTextToken;
