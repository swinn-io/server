@extends('layout')

@section('page-data')
    <script>
        window.__PAGE__ = 'Thread';
        window.__PROPS__ = { thread: @json($thread) };
    </script>
@endsection