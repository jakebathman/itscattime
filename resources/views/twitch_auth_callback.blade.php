@extends('layout')

@section('content')
    <div class="text-white text-2xl">
        @if ($message ?? '')
            {{ $message }}
        @else
            {{ json_encode($data) }}
        @endif
    </div>
@endsection
