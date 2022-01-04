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
                                <span class="font-semibold title-font text-gray-700">CATEGORY</span>
                                <span class="mt-1 text-gray-500 text-sm">12 Jun 2019</span>
                                <div class="-space-x-4">
                                    <img class="relative z-30 inline object-cover w-12 h-12 border-2 border-white rounded-full" src="https://images.pexels.com/photos/2589653/pexels-photo-2589653.jpeg?auto=compress&cs=tinysrgb&h=650&w=940" alt="Profile image"/>
                                    <img class="relative z-20 inline object-cover w-12 h-12 border-2 border-white rounded-full" src="https://images.pexels.com/photos/2955305/pexels-photo-2955305.jpeg?auto=compress&cs=tinysrgb&h=650&w=940" alt="Profile image"/>
                                    <img class="relative z-10 inline object-cover w-12 h-12 border-2 border-white rounded-full" src="https://images.pexels.com/photos/2589653/pexels-photo-2589653.jpeg?auto=compress&cs=tinysrgb&h=650&w=940" alt="Profile image"/>
                                </div>
                            </div>
                            <div class="md:flex-grow">
                                <h2 class="text-2xl font-medium text-gray-900 title-font mb-2">
                                    {{ $message->user->name }}
                                </h2>
                                <p class="leading-relaxed">{{ print_r($message->body, true) }}</p>
                                <a class="text-indigo-500 inline-flex items-center mt-4" href="">Learn More
                                    <svg class="w-4 h-4 ml-2" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="M12 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
