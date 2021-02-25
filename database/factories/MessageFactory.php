<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $data = collect([
            [
                'type' => 'currency',
                'payload' => [
                    'base' => 'USD',
                    'date' => '2021-01-01',
                    'rates' => [
                        'CAD' => 1.260046,
                        'CHF' => 0.933058,
                        'EUR' => 0.806942,
                        'GBP' => 0.719154,
                    ],
                ],
            ],
            [
                'type' => 'weather',
                'payload' => [
                    'coord' => [
                        'lon' => -122.08,
                        'lat' => 37.39,
                    ],
                    'weather' => [
                        0 => [
                            'id' => 800,
                            'main' => 'Clear',
                            'description' => 'clear sky',
                            'icon' => '01d',
                        ],
                    ],
                    'base' => 'stations',
                    'main' => [
                        'temp' => 282.55,
                        'feels_like' => 281.86,
                        'temp_min' => 280.37,
                        'temp_max' => 284.26,
                        'pressure' => 1023,
                        'humidity' => 100,
                    ],
                    'visibility' => 16093,
                    'wind' => [
                        'speed' => 1.5,
                        'deg' => 350,
                    ],
                    'clouds' => [
                        'all' => 1,
                    ],
                    'dt' => 1560350645,
                    'sys' => [
                        'type' => 1,
                        'id' => 5122,
                        'message' => 0.0139,
                        'country' => 'US',
                        'sunrise' => 1560343627,
                        'sunset' => 1560396563,
                    ],
                    'timezone' => -25200,
                    'id' => 420006353,
                    'name' => 'Mountain View',
                    'cod' => 200,
                ],
            ],
        ])->random();

        return [
            'thread_id' => Thread::inRandomOrder()->first()->id,
            'user_id'   => User::inRandomOrder()->first()->id,
            'body'      => $data,
        ];
    }
}
