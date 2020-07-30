<?php

use App\Models\Message;
use App\Models\Thread;
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
        $threads->each(function ($item) {
            $messages = factory(Message::class, rand(1, 25))->create(['thread_id' => $item->id]);
            $users = $messages->pluck('user_id')->unique()->toArray();
            $item->addParticipant($users);
        });
    }
}
