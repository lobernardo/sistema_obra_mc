<?php

use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Lookup fixtures shared by the suites. The `finalizado` status and the
| history event types are inserted by a migration (RF-42), so every fixture
| resolves lookup rows by slug with `firstOrCreate` instead of inserting them
| blindly, which would collide with the migrated rows.
|
*/

/**
 * Ensures one `statuses` row per {@see StatusSlug} case, with
 * `sort_order` = case position + 1, reusing rows that already exist.
 *
 * @param  (Closure(StatusSlug): string)|null  $nameFor
 * @return array<string, Status>
 */
function seedWorkflowStatuses(?Closure $nameFor = null): array
{
    $statuses = [];

    foreach (StatusSlug::cases() as $index => $slug) {
        $attributes = ['slug' => $slug->value, 'sort_order' => $index + 1];

        if ($nameFor !== null) {
            $attributes['name'] = $nameFor($slug);
        }

        $statuses[$slug->value] = Status::query()->firstOrCreate(
            ['slug' => $slug->value],
            Status::factory()->raw($attributes),
        );
    }

    return $statuses;
}

/**
 * Ensures one `event_types` row per {@see EventTypeSlug} case,
 * reusing rows that already exist.
 *
 * @return array<string, EventType>
 */
function seedHistoryEventTypes(): array
{
    $eventTypes = [];

    foreach (EventTypeSlug::cases() as $slug) {
        $eventTypes[$slug->value] = EventType::query()->firstOrCreate(
            ['slug' => $slug->value],
            EventType::factory()->raw(['slug' => $slug->value]),
        );
    }

    return $eventTypes;
}
