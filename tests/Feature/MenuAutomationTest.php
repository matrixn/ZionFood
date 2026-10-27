<?php

use App\Domain\Menus\CompleteMenuContent;
use App\Domain\Menus\ManageMenuContent;
use App\Domain\Menus\MenuContent;
use App\Domain\Menus\NutritionEstimate;
use App\Events\WorkspaceNotificationChanged;
use App\Jobs\GenerateMenuContent;
use App\Models\LocationStaff;
use App\Models\MenuAiSetting;
use App\Models\MenuContentDraft;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function estimateOutput(): array
{
    return ['values' => ['energy_kcal' => 500, 'fat_g' => 20, 'saturates_g' => 5, 'carbohydrate_g' => 50, 'sugars_g' => 4, 'protein_g' => 30, 'salt_g' => 2, 'fibre_g' => null], 'assumptions' => ['Proporții aproximative pentru ingredientele furnizate.']];
}
function automationSettings(): void
{
    Queue::fake();
    Http::preventStrayRequests();
    MenuAiSetting::create(['id' => 1, 'enabled' => true, 'provider' => 'groq', 'model' => 'openai/gpt-oss-20b', 'api_key' => 'fake_test_key', 'daily_limit' => 100]);
}

it('derives one portion and rejects ambiguous weights and impossible nutritional output', function () {
    [$owner,$location,$item] = tenantFixture('auto-inputs');
    $item->update(['portion_size' => '0,35 kg', 'description' => 'roșii, feta, ulei']);
    $engine = app(NutritionEstimate::class);
    $inputs = $engine->inputs($item);
    expect($inputs['portion_grams'])->toBe(350.0);
    $result = $engine->fromOutput(estimateOutput(), $inputs);
    expect($result['per_serving']['energy_kcal'])->toBe(500.0)->and($result['values']['energy_kcal'])->toBe(142.857)->and($result['values']['energy_kj'])->toBe(597.714)->and($result['estimated'])->toBeTrue()->and($result['values']['fibre_g'])->toBeNull();
    $item->update(['portion_size' => '100/300 g']);
    expect($engine->inputs($item)['portion_grams'])->toBeNull();
    expect(fn () => $engine->requireInputs($engine->inputs($item)))->toThrow(ValidationException::class);
    $output = estimateOutput();
    $output['values']['saturates_g'] = 25;
    expect(fn () => $engine->fromOutput($output, $inputs))->toThrow(ValidationException::class);
});

it('generates combined estimates and translations without publishing and delivers a durable scoped notification', function () {
    automationSettings();
    [$owner,$location,$item] = tenantFixture('auto-combined');
    $item->update(['portion_size' => '350 g', 'description' => 'Feta, roșii, ulei']);
    $base = '/app/locations/'.$location->public_id.'/menu-content';
    $result = ['nutrition' => estimateOutput(), 'description' => 'Feta coaptă cu roșii.', 'allergens' => ['milk'], 'may_contain' => [], 'labels' => ['vegetarian'], 'translations' => ['en' => menuTranslation()], 'notes' => 'Verificați proporțiile.'];
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($result)]]]])]);
    $this->actingAs($owner)->postJson($base.'/generate', ['item' => $item->public_id, 'kind' => 'analyze', 'languages' => ['en'], 'options' => ['nutrition', 'food', 'rewrite', 'translate']])->assertAccepted();
    $draft = MenuContentDraft::firstOrFail();
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('ready')->and($item->fresh()->content)->toBeNull();
    expect($draft->getRawOriginal('input'))->not->toContain('Feta');
    Http::assertSent(function ($request) {
        $schema = json_decode(json_encode($request['response_format']['json_schema']['schema']), true);
        expect($schema['properties']['nutrition']['additionalProperties'])->toBeFalse();
        expect($schema['properties']['nutrition']['properties']['values']['required'])->toContain('energy_kcal');

        return ! str_contains($request->body(), 'fake_test_key');
    });
    $notice = WorkspaceNotification::firstOrFail();
    app(CompleteMenuContent::class)->finish($draft->id, 'failed');
    expect(WorkspaceNotification::count())->toBe(1)->and($draft->fresh()->status)->toBe('ready');
    $this->getJson('/app/notifications')->assertOk()->assertJsonPath('notifications.0.draft', $draft->public_id)->assertJsonMissingPath('notifications.0.proposal');
    $this->getJson($base.'/drafts/'.$draft->public_id)->assertOk()->assertJsonPath('source_current', true)->assertJsonPath('can_write', true);
    $this->postJson('/app/notifications/'.$notice->public_id.'/read')->assertOk();
    $price = $item->price;
    $this->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'approve', 'sections' => ['nutrition', 'allergens', 'description', 'translations'], 'changes' => ['description' => 'Descriere corectată.']])->assertOk();
    $public = app(MenuContent::class)->publicData($item->fresh());
    expect($public['nutrition']['estimated'])->toBeTrue()->and($public['nutrition']['per_serving']['energy_kcal'])->toEqual(500)->and($public['nutrition'])->not->toHaveKey('assumptions')->and($public)->not->toHaveKey('nutrition_inputs')->and($item->fresh()->description)->toBe('Descriere corectată.')->and($item->fresh()->price)->toBe($price)->and($public['translations']['en']['name'])->toBe('Translated dish');
    expect($item->fresh()->content['labels'])->toBe([])->and($draft->fresh()->proposal['description'])->toBe('Descriere corectată.')->and($draft->fresh()->report['approved_sections'])->not->toContain('labels');
});

it('revalidates notification recipient permissions and rejects foreign review and read requests', function () {
    automationSettings();
    [$owner,$location,$item] = tenantFixture('auto-access');
    [$foreign] = tenantFixture('auto-access-foreign');
    $staff = User::factory()->create();
    $membership = OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['menu' => 'manage']]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $staff->id]);
    $draft = app(ManageMenuContent::class)->draft($staff, $location, $item, 'rewrite');
    app(CompleteMenuContent::class)->finish($draft->id, 'ready', ['proposal' => ['description' => 'Rescris']]);
    $notice = WorkspaceNotification::firstOrFail();
    $detail = '/app/locations/'.$location->public_id.'/menu-content/drafts/'.$draft->public_id;
    $this->actingAs($foreign)->getJson('/app/notifications')->assertJsonCount(0, 'notifications');
    $this->getJson($detail)->assertForbidden();
    $this->postJson('/app/notifications/'.$notice->public_id.'/read')->assertNotFound();
    $membership->update(['permissions' => ['menu' => 'view']]);
    $this->actingAs($staff)->getJson($detail)->assertOk()->assertJsonPath('can_write', false);
    $this->postJson($detail, ['action' => 'approve'])->assertForbidden();
    $membership->update(['permissions' => ['menu' => 'none']]);
    $this->getJson('/app/notifications')->assertJsonCount(0, 'notifications');
    $this->getJson($detail)->assertForbidden();
    expect((new WorkspaceNotificationChanged($staff->id, $notice->public_id))->broadcastWith())->toBe(['notification_id' => $notice->public_id]);
});

it('keeps failed and stale generation visible and prevents stale approvals or unsolicited sections', function () {
    automationSettings();
    [$owner,$location,$item] = tenantFixture('auto-errors');
    $manager = app(ManageMenuContent::class);
    $draft = $manager->draft($owner, $location, $item, 'rewrite');
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{}']]]])]);
    (new GenerateMenuContent($draft->id))->handle();
    expect($draft->fresh()->status)->toBe('failed')->and(WorkspaceNotification::count())->toBe(1);
    $draft = $manager->draft($owner, $location, $item, 'rewrite');
    app(CompleteMenuContent::class)->finish($draft->id, 'ready', ['proposal' => ['description' => 'Rescris']]);
    $base = '/app/locations/'.$location->public_id.'/menu-content';
    $this->actingAs($owner)->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'approve', 'changes' => ['allergens' => ['milk']]])->assertUnprocessable();
    $item->update(['name' => 'Changed']);
    $this->getJson($base.'/drafts/'.$draft->public_id)->assertJsonPath('source_current', false);
    $this->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'approve'])->assertConflict();
    $this->postJson($base.'/drafts/'.$draft->public_id, ['action' => 'reject'])->assertOk();
    expect($draft->fresh()->status)->toBe('rejected');
    $this->postJson($base.'/items/'.$item->public_id.'/gloriafood', ['source_item' => ['id' => 1]])->assertNotFound();
    $this->postJson($base.'/generate', ['item' => $item->public_id, 'kind' => 'calculation', 'languages' => []])->assertUnprocessable();
});

it('saves minimal inputs with optimistic locking and invalidates previous nutrition on change', function () {
    [$owner,$location,$item] = tenantFixture('auto-save');
    $schema = app(MenuContent::class);
    $manager = app(ManageMenuContent::class);
    $content = [...$schema->empty(), 'nutrition_inputs' => ['portion_grams' => 350, 'ingredients_text' => 'Feta, roșii', 'preparation' => 'Copt']];
    $item = $manager->save($owner, $location, $item, $content, $schema->hash($item));
    expect($item->portion_size)->toBe('350 g');
    $content = $item->content;
    $content['nutrition'] = app(NutritionEstimate::class)->fromOutput(estimateOutput(), $content['nutrition_inputs']);
    $item = $manager->save($owner, $location, $item, $content, $schema->hash($item));
    expect($item->content['nutrition'])->not->toBeNull();
    $hash = $schema->hash($item);
    $content = $item->content;
    $content['nutrition_inputs']['portion_grams'] = 400;
    $item = $manager->save($owner, $location, $item, $content, $hash);
    expect($item->content['nutrition'])->toBeNull()->and($item->portion_size)->toBe('400 g');
    $this->actingAs($owner)->putJson('/app/locations/'.$location->public_id.'/menu-content/items/'.$item->public_id, ['content' => $content, 'hash' => $hash])->assertConflict();
});
