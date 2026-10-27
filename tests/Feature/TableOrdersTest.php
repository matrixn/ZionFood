<?php

use App\Domain\Dining\TableSessions;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Restaurants\SyncDiningTables;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\StorefrontDomain;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

function tableOrdersVenue(string $slug = 'table-orders'): array
{
    [$owner,$location,$item] = tenantFixture($slug);
    $location->fulfillment()->update(['dine_in_enabled' => true]);
    app(SyncDiningTables::class)->execute($location, ['mode' => 'tables', 'tables' => [['code' => 'T1', 'area' => 'Salon', 'seats' => 4], ['code' => 'T2', 'area' => 'Salon', 'seats' => 4]]]);
    $table = DiningTable::where('location_id', $location->id)->first();
    $session = app(TableSessions::class)->open($table, $owner);

    return [$owner, $location, $item, $table, $session, Crypt::decryptString($session->token_encrypted)];
}
function tableOrdersPlace($location, $item, string $token): Order
{
    return app(PlaceOrderAction::class)->execute($location, [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => $token], (string) Str::uuid())['order'];
}
beforeEach(function () {
    $this->travelTo(now('UTC')->setTime(9, 0));
});
it('returns active snapshot products and keeps ready orders until completion', function () {
    [$u,$l,$item,$table,$session,$token] = tableOrdersVenue();
    $order = tableOrdersPlace($l, $item, $token);
    $item->update(['name' => 'Changed product', 'price' => 9999]);
    $url = '/api/v1/public/tables/'.$table->public_id.'/orders';
    $r = $this->withToken($token)->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('orders.0.items.0.name', 'Pizza')->assertJsonPath('orders.0.items.0.quantity', 2)->assertJsonPath('orders.0.total', 7000);
    expect($r->json('orders.0'))->not->toHaveKeys(['customer_name', 'customer_phone', 'tracking_token', 'tracking_token_encrypted', 'notes', 'dining_session_id']);
    foreach ([OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready] as $next) {
        $order = app(TransitionOrderStatus::class)->execute($u, $order, $next, $order->version);
    }
    $this->getJson($url)->assertJsonPath('orders.0.status', 'ready');
    app(TransitionOrderStatus::class)->execute($u, $order, OrderStatus::Completed, $order->version);
    $this->getJson($url)->assertExactJson(['orders' => [], 'next_cursor' => null]);
    expect(Order::find($order->id))->not->toBeNull();
});
it('excludes other tables sessions and tenants and rejects missing or QR bearer credentials', function () {
    [$u,$l,$item,$table,$session,$token] = tableOrdersVenue();
    tableOrdersPlace($l, $item, $token);
    $other = DiningTable::where('location_id', $l->id)->whereKeyNot($table->id)->first();
    $otherSession = app(TableSessions::class)->open($other, $u);
    $otherToken = Crypt::decryptString($otherSession->token_encrypted);
    $url = '/api/v1/public/tables/'.$table->public_id.'/orders';
    $this->getJson($url)->assertNotFound();
    $this->withToken(Crypt::decryptString($table->qr_token_encrypted))->getJson($url)->assertNotFound();
    $this->withToken($otherToken)->getJson($url)->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/public/tables/'.$other->public_id.'/orders')->assertNotFound();
    [,,$otherItem,$foreign,,$foreignToken] = tableOrdersVenue('foreign-table');
    $this->withToken($foreignToken)->getJson($url)->assertNotFound();
    StorefrontDomain::create(['organization_id' => $foreign->organization_id, 'location_id' => $foreign->location_id, 'hostname' => 'table-other.zionfood.test', 'verification_status' => 'verified', 'kind' => 'custom']);
    $this->withToken($token)->getJson('http://table-other.zionfood.test'.$url)->assertNotFound();
});
it('keeps read-only receipts after closing expiry and rotation without exposing earlier parties to new sessions', function () {
    [$u,$l,$item,$table,$session,$token] = tableOrdersVenue();
    $order = tableOrdersPlace($l, $item, $token);
    app(TableSessions::class)->close($u, $table);
    $session->update(['expires_at' => now()->subMinute()]);
    app(TableSessions::class)->rotate($u, $table);
    $url = '/api/v1/public/tables/'.$table->public_id.'/orders';
    $this->withToken($token)->getJson($url)->assertJsonPath('orders.0.public_id', $order->public_id);
    $new = app(TableSessions::class)->open($table, $u);
    $this->withToken(Crypt::decryptString($new->token_encrypted))->getJson($url)->assertExactJson(['orders' => [], 'next_cursor' => null]);
    $this->postJson('/api/v1/public/locations/'.$l->public_id.'/checkout', [...checkoutData($item), 'fulfillment' => 'dine_in', 'table_session_token' => $token], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $l->fulfillment()->update(['dine_in_enabled' => false]);
    $this->withToken($token)->getJson($url)->assertJsonPath('orders.0.public_id', $order->public_id);
});
it('removes only terminal orders while another order at the same table stays visible', function () {
    [$u,$l,$item,$table,$session,$token] = tableOrdersVenue();
    $first = tableOrdersPlace($l, $item, $token);
    $second = tableOrdersPlace($l, $item, $token);
    $third = tableOrdersPlace($l, $item, $token);
    app(TransitionOrderStatus::class)->execute($u, $first, OrderStatus::Cancelled, 1);
    app(TransitionOrderStatus::class)->execute($u, $second, OrderStatus::Rejected, 1, 'Unavailable');
    $this->withToken($token)->getJson('/api/v1/public/tables/'.$table->public_id.'/orders')->assertJsonCount(1, 'orders')->assertJsonPath('orders.0.public_id', $third->public_id);
});
it('paginates active receipts without leaking orders from a cursor in another session', function () {
    [$u,$l,$item,$table,$session,$token] = tableOrdersVenue();
    $original = tableOrdersPlace($l, $item, $token);
    for ($i = 0; $i < 31; $i++) {
        $copy = $original->replicate(['public_id', 'tracking_token_hash']);
        $copy->tracking_token_hash = hash('sha256', Str::random(64));
        $copy->save();
    }
    $url = '/api/v1/public/tables/'.$table->public_id.'/orders';
    $first = $this->withToken($token)->getJson($url)->assertJsonCount(30, 'orders');
    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();
    $this->getJson($url.'?cursor='.urlencode($cursor))->assertJsonCount(2, 'orders')->assertJsonPath('next_cursor', null);
    app(TableSessions::class)->close($u, $table);
    $next = app(TableSessions::class)->open($table, $u);
    $this->withToken(Crypt::decryptString($next->token_encrypted))->getJson($url.'?cursor='.urlencode($cursor))->assertExactJson(['orders' => [], 'next_cursor' => null]);

});
