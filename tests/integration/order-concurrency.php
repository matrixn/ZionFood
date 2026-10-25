<?php

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Jobs\DeliverOutbox;
use App\Models\CheckoutIdempotencyKey;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Use a dedicated MariaDB database ending in _test.');
}

if (($argv[1] ?? null) === 'checkout') {
    $location = RestaurantLocation::findOrFail($argv[2]);
    $item = MenuItem::findOrFail($argv[3]);
    $result = app(PlaceOrderAction::class)->execute($location, ['customer_name' => 'Concurrency fixture', 'customer_phone' => '0700000000', 'fulfillment' => 'pickup', 'items' => [['product_id' => $item->public_id, 'quantity' => 1]]], $argv[4]);
    echo $result['order']->public_id;
    exit(0);
}
if (($argv[1] ?? null) === 'transition') {
    try {
        app(TransitionOrderStatus::class)->execute(User::findOrFail($argv[2]), Order::findOrFail($argv[3]), OrderStatus::Accepted, 1);
        exit(0);
    } catch (HttpException $e) {
        if ($e->getStatusCode() === 409) {
            exit(3);
        } throw $e;
    }
}
$slug = 'concurrency-'.strtolower(Str::random(10));
$user = User::create(['name' => 'Concurrency Owner', 'email' => $slug.'@example.test', 'password' => Str::password(64)]);
$location = app(OnboardOrganization::class)->execute($user, ['organization_name' => $slug, 'restaurant_name' => $slug, 'slug' => $slug, 'location_name' => 'Test', 'address' => 'Test street']);
$location->update(['accepting_orders' => true]);
$location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
$category = MenuCategory::create(['organization_id' => $location->organization_id, 'menu_id' => $location->menus()->first()->id, 'name' => 'Test']);
$item = MenuItem::create(['organization_id' => $location->organization_id, 'category_id' => $category->id, 'name' => 'Test product', 'price' => 2500]);
$key = (string) Str::uuid();
$children = [new Process([PHP_BINARY, __FILE__, 'checkout', (string) $location->id, (string) $item->id, $key]), new Process([PHP_BINARY, __FILE__, 'checkout', (string) $location->id, (string) $item->id, $key])];
foreach ($children as $child) {
    $child->start();
}
foreach ($children as $child) {
    $child->wait();
    if (! $child->isSuccessful()) {
        throw new RuntimeException($child->getErrorOutput().$child->getOutput());
    }
}
if ($children[0]->getOutput() !== $children[1]->getOutput() || CheckoutIdempotencyKey::where('location_id', $location->id)->where('key', $key)->count() !== 1 || Order::where('location_id', $location->id)->count() !== 1) {
    throw new RuntimeException('Concurrent checkout duplicated an order.');
}
$order = Order::where('location_id', $location->id)->firstOrFail();
$children = [new Process([PHP_BINARY, __FILE__, 'transition', (string) $user->id, (string) $order->id]), new Process([PHP_BINARY, __FILE__, 'transition', (string) $user->id, (string) $order->id])];
foreach ($children as $child) {
    $child->start();
}
$codes = [];
foreach ($children as $child) {
    $child->wait();
    $codes[] = $child->getExitCode();
}
sort($codes);
if ($codes !== [0, 3] || $order->fresh()->version !== 2 || $order->history()->count() !== 2) {
    throw new RuntimeException('Concurrent transitions were not serialized correctly.');
}
echo "PASS: two simultaneous checkouts created one order; two simultaneous status updates produced one success and one conflict.\n";
$queue = Queue::fake();
DB::beginTransaction();
app(PlaceOrderAction::class)->execute($location, ['customer_name' => 'Commit fixture', 'customer_phone' => '0700000000', 'fulfillment' => 'pickup', 'items' => [['product_id' => $item->public_id, 'quantity' => 1]]], (string) Str::uuid());
if ($queue->pushed(DeliverOutbox::class)->isNotEmpty()) {
    throw new RuntimeException('Outbox job dispatched before commit.');
}
DB::commit();
if ($queue->pushed(DeliverOutbox::class)->count() !== 1) {
    throw new RuntimeException('Outbox job missing after commit.');
}
echo "PASS: outbox delivery was queued only after the database commit.\n";
