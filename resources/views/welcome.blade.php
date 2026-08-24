@extends('layout')

@section('meta-description', config('app.name').' — an open-source messaging service for the data era.')

@section('page-data')
    <script>
        window.__PAGE__ = 'Welcome';
        window.__PROPS__ = {};
    </script>
@endsection