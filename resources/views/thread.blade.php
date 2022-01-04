@extends('layout')
@section('content')
    <div class="relative w-full px-8 text-gray-700 bg-white body-font">
        <div class="container flex flex-col flex-wrap items-center justify-between py-5 mx-auto md:flex-row max-w-7xl">

            <!-- Section 1 -->
            <section class="w-full text-gray-700 bg-white body-font">
                <div class="flex flex-col flex-wrap items-center justify-between py-5 mx-auto md:flex-row max-w-7xl">
                    <a href="{{ route('thread.show', ['thread' => $thread->id]) }}" class="relative z-10 flex items-center w-auto text-1xl font-extrabold leading-none text-black select-none">
                        {{ $thread->subject }}
                    </a>
                    <div class="relative z-10 inline-flex items-center space-x-3 md:ml-5 lg:justify-end">
                        <span class="inline-flex rounded-md shadow-sm">
                            <a href="#" class="inline-flex items-center justify-center px-4 py-2 text-base font-medium leading-6 text-white whitespace-no-wrap bg-red-600 border border-red-700 rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                NEW
                            </a>
                        </span>
                    </div>
                </div>
            </section>

            <section class="text-gray-600 body-font overflow-hidden">
                <div class="container px-5 py-24 mx-auto">
                    @foreach($thread->messages as $message)
                    <div class="-my-8 divide-y-2 divide-gray-100">
                        <div class="py-8 flex flex-wrap md:flex-nowrap">
                            <div class="md:w-64 md:mb-0 mb-6 flex-shrink-0 flex flex-col">
                                <span class="font-semibold title-font text-gray-700">{{ $message->user->name }}</span>
                                <span class="mt-1 text-gray-500 text-sm">{{ $message->user->created_at }}</span>
                                <div class="">
                                    <img class="relative z-10 inline object-cover w-20 h-20 border-2 border-white rounded-full" src="https://ui-avatars.com/api/?name={{ $message->user->name }}&color=7F9CF5&background=EBF4FF" alt="Profile image"/>
                                </div>
                            </div>
                            <div class="md:flex-grow">
                                <p class="mt-0 text-xs mb-2">{{ $message->user->created_at }}</p>
                                <p class="leading-relaxed">{{ print_r($message->body, true) }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
