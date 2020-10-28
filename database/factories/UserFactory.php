<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $name = $this->faker->name;

        return [
            'name'          => $name,
            'provider_name' => collect(['github', 'linkedin', 'google', 'instagram'])->random(),
            'provider_id'   => Str::uuid()->toString(),
            'email'         => null,
            'notify_via'    => [],
            'access_token'  => Str::random(40),
            'refresh_token' => Str::random(40),
            'profile'       => [
                'name'   => $name,
                'avatar' => $this->faker->url,
            ],
        ];
    }
}
