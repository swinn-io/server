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

        $this->resetFactorySeededThreadData($thread);

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

    public function test_viewing_a_thread_marks_it_read_and_exposes_participants_and_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->create();

        $this->resetFactorySeededThreadData($thread);

        $thread->addParticipant([$user->id, $other->id]);
        Message::factory()->count(2)->create(['thread_id' => $thread->id, 'user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('thread.show', ['thread' => $thread->id]));

        $response->assertOk();
        $response->assertViewHas('thread', function (array $data) use ($other): bool {
            if ($data['messages_count'] !== 2 || ! is_array($data['participants'])) {
                return false;
            }

            return collect($data['participants'])->contains(fn (mixed $p): bool => is_array($p) && is_array($p['user']) && $p['user']['id'] === $other->id);
        });

        $participant = $thread->participants()->where('user_id', $user->id)->firstOrFail()->fresh();
        $this->assertNotNull($participant);
        $this->assertTrue($participant->last_read?->greaterThan(now()->subMinute()));
    }

    /**
     * Documents current behavior, not a designed authorization guarantee: ThreadController::show()
     * performs no explicit authorization check. A non-participant viewing an existing thread ID
     * currently gets a 404 only as a side effect of MessageService::markAsRead()'s internal
     * firstOrFail() throwing ModelNotFoundException. This pins that behavior down so it can't
     * regress silently (e.g. if firstOrFail() were ever swapped for the vendor's first()).
     */
    public function test_viewing_a_thread_you_are_not_a_participant_of_returns_404(): void
    {
        $user = User::factory()->create();
        $thread = Thread::factory()->create();
        $this->resetFactorySeededThreadData($thread);

        $response = $this->actingAs($user)->get(route('thread.show', ['thread' => $thread->id]));

        $response->assertNotFound();
    }

    /**
     * ThreadFactory's afterCreating hook seeds random messages/participants (picking from all
     * existing users, which in isolated tests means it can pick this test's own users). Both
     * Message and Participant use SoftDeletes, so a plain delete() would leave rows behind that
     * still collide with the participants_thread_id_user_id_unique index; forceDelete() is
     * required to reset to a clean, deterministic state.
     */
    private function resetFactorySeededThreadData(Thread $thread): void
    {
        $thread->messages()->withTrashed()->get()->each->forceDelete();
        $thread->participants()->withTrashed()->get()->each->forceDelete();
    }
}
