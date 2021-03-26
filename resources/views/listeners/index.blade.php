<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="bg-pink-500 text-center p-10">
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
    </div>
</x-app-layout>
