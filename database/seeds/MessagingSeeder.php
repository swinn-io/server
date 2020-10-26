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
        Thread::factory()
            ->count(150)
            ->create();
    }
}
