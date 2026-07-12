<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FrontEndTest extends TestCase
{
    /**
     * Test if home page is there.
     *
     * @return void
     */
    public function test_page_controller_index_method()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(Config::string('app.name'));
    }

    public function test_dashboard_shows_real_unread_counts_and_participant_ids(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->create();

        // ThreadFactory's afterCreating hook seeds random messages/participants (picking from
        // all existing users, which in this isolated test DB means it can pick $user/$other).
        // Both Message and Participant use SoftDeletes, so a plain delete() would leave rows
        // behind that still collide with the participants_thread_id_user_id_unique index;
        // forceDelete() is required to reset to a clean, deterministic state.
        $thread->messages()->withTrashed()->get()->each->forceDelete();
        $thread->participants()->withTrashed()->get()->each->forceDelete();

        $thread->addParticipant([$user->id, $other->id]);
        Message::factory()->create(['thread_id' => $thread->id, 'user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('threads', function (array $threads) use ($thread, $other): bool {
            $found = collect($threads)->first(fn (mixed $t): bool => is_array($t) && $t['id'] === $thread->id);

            if (! is_array($found)) {
                return false;
            }

            $participants = $found['participants'];

            return $found['unread_count'] === 1
                && is_array($participants)
                && collect($participants)->contains(fn (mixed $p): bool => is_array($p) && is_array($p['user']) && $p['user']['id'] === $other->id);
        });

        // Confirm the same data is what actually reaches the rendered page's window.__PROPS__,
        // since this app is plain Blade (not Inertia) and assertViewHas only inspects the
        // Illuminate\View\View data, not the rendered HTML.
        $response->assertSee('__PROPS__', false);
        $response->assertSee((string) $thread->id, false);
        $response->assertSee('"unread_count":1', false);
    }
}
