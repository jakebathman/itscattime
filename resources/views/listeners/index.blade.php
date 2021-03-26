@extends('layout')

@section('content')
    <div class="text-white text-xl">
        <h3 class="mb-5 font-bold">Listeners:</h3>
        @foreach ($channels as $channel)
        <div class="mb-2">
            <a href="{{ route('listeners.start', ['channel' => $channel['name']]) }}">
                [{{ $channel['status'] }}] Start {{ $channel['name'] }}
            </a>
        </div>
        @endforeach
    </div>
    <div>
        <a href="{{ route('listeners.restart') }}">
            Restart all
        </a>
    </div>
@endsection
