@extends('layout')
@section('content')
    <div class="flex-center position-ref full-height">
        @if (Route::has('login'))
            <div class="top-right links">
                @auth
                    <a href="">{{ Auth::user()->name }}</a>
                    <a href="{{ route('logout') }}">Logout</a>
                @else
                    <a href="{{ route('login') }}">Login</a>
                @endauth
            </div>
        @endif

        <div class="content">
            <div class="title m-b-md">
                {{ config('app.name') }}
            </div>

            <div class="links">
                <a href="https://github.com/ikidnapmyself/pp-api">GitHub</a>
            </div>
        </div>
    </div>
@endsection
