<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Message>
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $bodies = [
            ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy', 'intensity' => 3]],
            ['type' => 'currency', 'version' => '1.0', 'payload' => ['amount' => 142.50, 'currency_code' => 'USD']],
            ['type' => 'location', 'version' => '1.0', 'payload' => ['lat' => 51.5074, 'lng' => -0.1278]],
            ['type' => 'status', 'version' => '1.0', 'payload' => ['state' => 'dispatched', 'reason' => 'carrier_collected']],
            ['type' => 'metric', 'version' => '1.0', 'payload' => ['quantity' => 'temperature', 'value' => 22.4, 'unit' => 'celsius']],
        ];

        return [
            'thread_id' => Thread::inRandomOrder()->first()->id,
            'user_id' => User::inRandomOrder()->first()->id,
            'body' => collect($bodies)->random(),
        ];
    }
}
