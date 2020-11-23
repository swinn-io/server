<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="m-b-md">
                Authenticate with
            </div>

            <div class="links">
                <a href="{{ url("login/redirect/github?{$params}") }}">GitHub</a>
            </div>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
