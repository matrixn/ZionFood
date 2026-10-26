<?php

use App\Jobs\DeliverEmail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('protects the test email page and action from guests and ordinary users', function () {
    $this->get('/test-email')->assertRedirect('/login');
    $this->post('/test-email')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/test-email')->assertForbidden();
    $this->post('/test-email')->assertForbidden();
});

it('shows the configured admin recipient without sending on GET', function () {
    Queue::fake();
    config(['email_delivery.admin_email' => 'admin@example.test']);
    $user = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($user)->get('/test-email')->assertInertia(fn (Assert $page) => $page->component('admin/TestEmail')->where('recipient', 'admin@example.test'));
    Queue::assertNothingPushed();
});

it('always sends only to ADMIN_EMAIL and limits test actions to three per hour', function () {
    Queue::fake();
    config(['mail.default' => 'smtp', 'email_delivery.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]));
    for ($i = 0; $i < 3; $i++) {
        $this->from('/test-email')->post('/test-email', ['email' => 'other@example.test'])->assertRedirect('/test-email')->assertSessionHas('success');
    }
    $this->post('/test-email')->assertTooManyRequests();
    Queue::assertPushed(DeliverEmail::class, 3);
    Queue::assertPushed(DeliverEmail::class, fn ($job) => count($job->email->getTo()) === 1 && $job->email->getTo()[0]->getAddress() === 'admin@example.test');
});

it('refuses invalid admin email and non-delivery mailers', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]));
    config(['email_delivery.admin_email' => 'invalid']);
    $this->post('/test-email')->assertSessionHasErrors('email');
    config(['email_delivery.admin_email' => 'admin@example.test', 'mail.default' => 'log']);
    $this->post('/test-email')->assertSessionHasErrors('email');
    Queue::assertNothingPushed();
});
