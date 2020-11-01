<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ThreadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Thread::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $subject = implode(' ', $this->faker->words);

        return [
            'subject' => Str::title($subject),
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterMaking(function (Thread $thread) {
            //
        })->afterCreating(function (Thread $thread) {
            $messages = Message::factory()->count(rand(1, 5))->create(['thread_id' => $thread->id]);

            // Set message sender as participants.
            $users_ids = $messages->pluck('user_id');

            // Add participants
            $thread->addParticipant($users_ids->unique()->toArray());

            // Mark as read for some the participant users and choose some lucky ones.
            $some_users = rand(0, $users_ids->count());
            $users_ids->random($some_users)->each(function ($user) use ($thread) {
                $thread->markAsRead($user);
            });
        });
    }
}
