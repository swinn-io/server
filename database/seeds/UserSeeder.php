<?php

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class UserSeeder extends Seeder
{
    /**
     * Laravel Passport Client Repository.
     *
     * @var ClientRepository
     */
    private ClientRepository $clientRepository;

    /**
     * LoginService constructor.
     *
     * @param ClientRepository $repository
     */
    public function __construct(ClientRepository $repository)
    {
        $this->clientRepository = $repository;
    }
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(\App\Models\User::class, 50)->create()->each(function ($user){
            $this->clientRepository->create(
                $user->id,
                "{$user->provider_name}-{$user->provider_id}",
                'uri://',
                $user->provider_name,
                true
            );
        });
    }
}
