<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

trait WithAccessToken
{
    /**
     * Access token.
     *
     * @var Client
     */
    protected $client;

    /**
     * Access token.
     *
     * @var string
     */
    protected $token;

    /**
     * Prepare the request with access token.
     *
     * @param User $user
     */
    public function prepareAccessTokenForRequest(User $user)
    {
        $clientRepo = app(ClientRepository::class);

        $this->client = $clientRepo->create(
            $user->id,
            'test-client',
            url('fake/redirection'),
            'test-provider',
            true
        );

        $this->requestAccessToken();
        $this->setRequestHeaders();
    }

    /**
     * Get access token.
     *
     * @return void
     */
    protected function requestAccessToken()
    {
        $response = $this->post(route('passport.token'), [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->client->id,
            'client_secret' => $this->client->secret,
            'scope'         => '*',
        ]);

        $content = json_decode($response->content(), true);
        $this->token = Arr::get($content, 'access_token');
    }

    public function setRequestHeaders()
    {
        // Behave like an API call
        $this->withHeader('Accept', 'application/json');
        $this->withHeader('Authorization', 'Bearer '.$this->token);
    }
}
