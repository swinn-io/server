@extends('layout')

@section('meta-description', config('app.name').' — real-time messaging threads for teams')

@section('page-data')
    <script>
        window.__PAGE__ = 'Welcome';
        window.__PROPS__ = {};
    </script>
@endsection