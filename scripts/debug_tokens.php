<?php
require __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->bootstrap();
$admin = App\Models\AdminProfile::first();
if (! $admin) {
    echo "no admin\n";
    exit(0);
}
$relation = $admin->tokens();
echo $relation->toSql() . "\n";
print_r($relation->getBindings());
$kernel->terminate($request, new Illuminate\Http\Response());
