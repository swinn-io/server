@extends('layout')
@section('content')
    <div class="relative w-full px-8 text-gray-700 bg-white body-font">
        <div class="container flex flex-col flex-wrap items-center justify-between py-5 mx-auto md:flex-row max-w-7xl">
            <section class="text-gray-600 body-font overflow-hidden">
                <div class="container px-5 py-24 mx-auto">
                    <div class="-my-8 divide-y-2 divide-gray-100">
                        @foreach($threads as $thread)
                        <div class="py-8 flex flex-wrap md:flex-nowrap">
                            <div class="md:w-64 md:mb-0 mb-6 flex-shrink-0 flex flex-col">
                                <span class="font-semibold title-font text-gray-700">CATEGORY</span>
                                <span class="mt-1 text-gray-500 text-sm">12 Jun 2019</span>
                                <div class="flex -space-x-3 overflow-hidden">
                                    @foreach($thread->participants as $participant)
                                        <img class="relative z-30 hover:z-40 inline object-cover w-12 h-12 border-2 border-white rounded-full" src="https://ui-avatars.com/api/?name={{ $participant->user->name }}&color=7F9CF5&background=EBF4FF" alt="{{ $participant->user->name }}&color=7F9CF5&background=EBF4FF">
                                    @endforeach
                                </div>
                            </div>
                            <div class="md:flex-grow">
                                <h2 class="text-2xl font-medium text-gray-900 title-font mb-2">
                                    {{ $thread->subject }}
                                    <span class="inline-flex items-center justify-center px-2 py-1 mr-2 text-sm font-bold leading-none text-red-100 bg-red-600 rounded-full">9</span>
                                </h2>
                                <p class="leading-relaxed">Glossier echo park pug, church-key sartorial biodiesel vexillologist pop-up snackwave ramps cornhole. Marfa 3 wolf moon party messenger bag selfies, poke vaporware kombucha lumbersexual pork belly polaroid hoodie portland craft beer.</p>
                                <a class="text-indigo-500 inline-flex items-center mt-4" href="{{ route('thread.show', ['thread' => $thread->id]) }}">
                                    History
                                    <svg class="w-4 h-4 ml-2" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="M12 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
