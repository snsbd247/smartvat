<?php

use App\Modules\Notification\Models\AppNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('creates a notification when a purchase order is raised', function () {
    $supplier = Supplier::create(['company_id' => $this->company->id, 'code' => 'S1', 'name' => 'Sup', 'status' => 'active']);
    $product = makeProduct($this->company, 'P1');

    app(PurchaseService::class)->createOrder([
        'supplier_id' => $supplier->id,
        'lines' => [['product_id' => $product->id, 'qty' => 5, 'unit_price' => 20]],
    ]);

    $n = AppNotification::first();
    expect($n)->not->toBeNull();
    expect($n->type)->toBe('approval');
    expect($n->title)->toContain('awaiting approval');
});

it('lists, counts and marks notifications read', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    app(NotificationService::class)->notify('info', 'Hello', 'world', '/');
    app(NotificationService::class)->notify('info', 'Second');

    $this->withHeaders($this->headers)->getJson('/api/notifications')->assertOk()->assertJsonCount(2);
    $this->withHeaders($this->headers)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 2);

    $id = AppNotification::first()->id;
    $this->withHeaders($this->headers)->postJson("/api/notifications/{$id}/read")->assertOk();
    $this->withHeaders($this->headers)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 1);

    $this->withHeaders($this->headers)->postJson('/api/notifications/read-all')->assertOk();
    $this->withHeaders($this->headers)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 0);
});

it('does not leak another user-targeted notification', function () {
    $me = makeUserInCompany($this->company, 'admin');
    $other = makeUserInCompany($this->company, 'admin');
    app(NotificationService::class)->notify('info', 'For other', null, null, $other->id);

    Sanctum::actingAs($me);
    $this->withHeaders($this->headers)->getJson('/api/notifications')->assertOk()->assertJsonCount(0);
});
