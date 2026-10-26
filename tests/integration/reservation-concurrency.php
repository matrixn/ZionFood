<?php

use App\Domain\Reservations\CreateReservation;
use App\Domain\Tenancy\Actions\OnboardOrganization;
use App\Models\Reservation;
use App\Models\RestaurantLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Use a dedicated MariaDB database ending in _test.');
}
if (($argv[1] ?? null) === 'book') {
    try {
        $result = app(CreateReservation::class)->execute(RestaurantLocation::findOrFail($argv[2]), ['guest_name' => 'Concurrency Guest', 'guest_phone' => '0700000000', 'party_size' => 4, 'starts_at' => $argv[3]], $argv[4]);
        echo $result['reservation']->public_id;
        exit(0);
    } catch (ValidationException) {
        exit(3);
    }
}
$slug = 'booking-race-'.strtolower(Str::random(10));
$owner = User::create(['name' => 'Concurrency Owner', 'email' => $slug.'@example.test', 'password' => Str::password(64)]);
$location = app(OnboardOrganization::class)->execute($owner, ['organization_name' => $slug, 'restaurant_name' => $slug, 'slug' => $slug, 'location_name' => 'Test', 'address' => 'Test street']);
$location->hours()->update(['opens_at' => '00:00', 'closes_at' => '23:59']);
$location->update(['setup_profile' => ['seating' => ['mode' => 'capacity', 'capacity' => 4], 'reservations' => ['confirmation' => 'automatic', 'duration_minutes' => 60, 'minimum_notice_minutes' => 0, 'max_party_size' => 4]]]);
$location->fulfillment()->update(['reservations_enabled' => true]);
$location->refresh();
$day = now($location->timezone)->addDay()->format('Y-m-d');
foreach ([false, true] as $replay) {
    $time = $day.($replay ? 'T14:00' : 'T12:00');
    $key = (string) Str::uuid();
    $children = array_map(fn ($i) => new Process([PHP_BINARY, __FILE__, 'book', (string) $location->id, $time, $replay ? $key : (string) Str::uuid()]), [1, 2]);
    foreach ($children as $child) {
        $child->start();
    }
    foreach ($children as $child) {
        $child->wait();
    }
    $codes = array_map(fn ($child) => $child->getExitCode(), $children);
    sort($codes);
    if ($codes !== ($replay ? [0, 0] : [0, 3]) || Reservation::where('location_id', $location->id)->where('starts_at', CarbonImmutable::parse($time, $location->timezone)->utc())->count() !== 1 || ($replay && $children[0]->getOutput() !== $children[1]->getOutput())) {
        throw new RuntimeException('Reservation concurrency failed: '.implode(' ', array_map(fn ($child) => $child->getErrorOutput().$child->getOutput(), $children)));
    }
}
echo "PASS: concurrent requests cannot overbook capacity; identical concurrent retries produce one reservation.\n";
