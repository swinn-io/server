@extends('layout')

@section('page-data')
    <script>
        window.__PAGE__ = 'Dashboard';
        window.__PROPS__ = { threads: @json($threads) };
    </script>
@endsection