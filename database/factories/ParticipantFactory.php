<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Participant::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'thread_id' => Thread::inRandomOrder()->first(),
            'user_id' => User::inRandomOrder()->first(),
            'last_read' => now(),
        ];
    }
}
