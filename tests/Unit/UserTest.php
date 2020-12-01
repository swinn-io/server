<?php

namespace Tests\Unit;

use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use WithFaker;

    /**
     * @var UserServiceInterface
     */
    private $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserServiceInterface::class);
        $this->seed(UserSeeder::class);
    }

    /**
     * Check if threads method returns pagination of threads models of a user model.
     *
     * @return void
     */
    public function testServiceMethodFind()
    {
        $user = User::inRandomOrder()->first();
        $find = $this->service->find($user->id);
        $this->assertEquals($find, $user);
    }
}
