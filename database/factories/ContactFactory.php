<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $user = User::inRandomOrder()->first();
        $contact = User::factory()->create();
        return [
            'name'  => $contact->name,
            'user_id'   => $user->id,
            'source_type'   => User::class,
            'source_id' => $contact->id
        ];
    }
}
