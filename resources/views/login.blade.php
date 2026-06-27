@extends('layout')

@section('page-data')
    <script>
        window.__PAGE__ = 'Login';
        window.__PROPS__ = { params: @json($params) };
    </script>
@endsection