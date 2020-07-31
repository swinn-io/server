<?php

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessagingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $threads = factory(Thread::class, 150)->create();
        $threads->each(function ($thread) {
            /**
             * @var Thread $thread
             */
            $messages = factory(Message::class, rand(1, 25))->create(['thread_id' => $thread->id]);

            // Set message sender as participants.
            $users_ids = $messages->pluck('user_id');

            // Add random recipients
            User::inRandomOrder()->limit(rand(0, 5))->get()->pluck('id')->each(function ($recipient) use ($users_ids) {
                $users_ids->push($recipient);
            });

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
