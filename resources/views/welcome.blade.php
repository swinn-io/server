@extends('layout')
@section('content')

    <!-- Section 1 -->
    <section class="px-2 pt-32 bg-white md:px-0">
        <div class="container items-center max-w-6xl px-5 mx-auto space-y-6 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-left text-gray-900 sm:text-5xl md:text-6xl md:text-center">
                <span class="block">Level-up your <span class="block mt-1 text-red-700 lg:inline lg:mt-0">messaging experience!</span></span>
            </h1>
            <p class="w-full mx-auto text-base text-left text-gray-500 md:max-w-md sm:text-lg lg:text-2xl md:max-w-3xl md:text-center">
                It's open-source messaging service for data era.
            </p>
            <div class="relative flex flex-col justify-center md:flex-row md:space-x-4">
                <a href="{{ route('login') }}" class="flex items-center w-full px-6 py-3 mb-3 text-lg text-white bg-red-700 md:mb-0 hover:bg-red-900 md:w-auto rounded-md">
                    Try It Free
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
                <a href="https://github.com/swinn-io" class="flex items-center px-6 py-3 text-gray-500 bg-gray-100 hover:bg-gray-200 hover:text-gray-600 rounded-md">
                    Learn More
                </a>
            </div>
        </div>
        <div class="container items-center max-w-4xl px-5 mx-auto mt-16 text-center">
            <img src="{{ asset('img/welcome.svg') }}" alt="">
        </div>
    </section>
@endsection
