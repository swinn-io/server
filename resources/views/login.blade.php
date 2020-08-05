@extends('layout')
@section('content')
    <div class="flex-center position-ref full-height">
        <div class="content">
            <div class="m-b-md">
                Authenticate with
            </div>

            <div class="links">
                <a href="{{ url("login/redirect/github?{$params}") }}">GitHub</a>
            </div>
        </div>
    </div>
@endsection
