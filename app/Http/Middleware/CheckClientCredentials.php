<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Laravel\Passport\Http\Middleware\CheckClientCredentials as BaseMiddleware;
use Laravel\Passport\Token;
use Lcobucci\JWT\Parser;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class CheckClientCredentials extends BaseMiddleware
{
    /**
     * Get client with user by bearer token.
     *
     * @return \Illuminate\Database\Eloquent\HigherOrderBuilderProxy|\Illuminate\Support\HigherOrderCollectionProxy|mixed
     */
    public function getClientByToken()
    {
        $bearerToken = request()->bearerToken();
        $tokenId = (new Parser())->parse($bearerToken)->getClaim('jti');

        return Token::find($tokenId)->client->load('user');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$scopes
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        $psr = (new PsrHttpFactory(
            new ServerRequestFactory,
            new StreamFactory,
            new UploadedFileFactory,
            new ResponseFactory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException;
        }

        $this->validate($psr, $scopes);

        // Set user by machine to machine client.
        $client = $this->getClientByToken();
        $request->setUserResolver(function () use ($client) {
            return optional($client)->user ?? null;
        });

        return $next($request);
    }

}
