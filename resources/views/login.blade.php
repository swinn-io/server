@extends('layout')
@section('content')
    <section class="w-full bg-white">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col lg:flex-row">
                <div class="relative w-full bg-cover lg:w-6/12 xl:w-7/12 bg-gradient-to-r from-white via-white to-gray-100">
                    <div class="relative flex flex-col items-center justify-center w-full h-full px-10 my-20 lg:px-16 lg:my-0">
                        <div class="flex flex-col items-start space-y-8 tracking-tight lg:max-w-3xl">
                            <div class="relative">
                                <p class="mb-2 font-medium text-gray-700 uppercase">Connect smarter</p>
                                <h2 class="text-5xl font-bold text-gray-900 xl:text-6xl">Choose any service provider to sign in or up!</h2>
                            </div>
                            <p class="text-2xl text-gray-700">No password required, no verification process. Start instantly.</p>
                            <a href="#_" class="invisible inline-block px-8 py-5 text-xl font-medium text-center text-white transition duration-200 bg-red-700 hover:bg-red-800 ease rounded-md">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>

                <div class="w-full bg-white lg:w-6/12 xl:w-5/12">
                    <div class="flex flex-col items-start justify-start w-full h-full p-10 lg:p-16 xl:p-24">
                        <h4 class="w-full text-3xl font-bold">Choose any service</h4>
                        <p class="text-lg text-gray-500">One click left to get authorized</p>
                        <div class="relative w-full mt-10 space-y-8">
                            <div class="relative">
                                <a href="{{ url("login/redirect/github?{$params}") }}" class="inline-block flex items-center w-full px-5 py-4 text-lg font-medium text-center text-white transition duration-200 bg-stone-900 hover:bg-stone-800 ease rounded-md">
                                    <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="pl-1">
                                        GitHub
                                    </span>
                                </a>
                                <a href="#" class="inline-block w-full px-5 py-4 mt-3 text-lg font-bold text-center text-gray-900 transition duration-200 bg-white border border-gray-300 hover:bg-gray-100 ease rounded-md">
                                    there will be more
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <div class="text-center mt-24">
        <div class="flex items-center justify-center">
            <svg fill="none" viewBox="0 0 24 24" class="w-12 h-12 text-blue-500" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 class="text-4xl tracking-tight">
            Authenticate by social accounts
        </h2>
        <span class="text-sm">
            Choose any OAuth services below:
        </span>
    </div>
    <div class="flex justify-center my-2 mx-4 md:mx-0">
        <form class="w-full max-w-xl">
            <div class="flex flex-wrap -mx-3 mb-6">

                <div class="flex items-center w-full mt-2">
                    <div class="w-full md:w-1/3 px-3 pt-4 mx-2">
                        <a href="{{ url("login/redirect/github?{$params}") }}" class='appearance-none flex items-center justify-center block w-full bg-gray-100 text-gray-700 p-3 leading-tight hover:bg-gray-200 hover:text-gray-700 focus:outline-none'>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span class="pl-1">Github</span>
                        </a>
                    </div>
                    {{--
                    <div class="w-full md:w-1/3 px-3 pt-4 mx-2">
                        <button class="appearance-none flex items-center justify-center block w-full bg-gray-100 text-gray-700 p-3 leading-tight hover:bg-gray-200 hover:text-gray-700 focus:outline-none">
                            <svg class="h-6 w-6 fill-current text-gray-700" viewBox="0 0 512 512">
                                <path d="M444.17,32H70.28C49.85,32,32,46.7,32,66.89V441.61C32,461.91,49.85,480,70.28,480H444.06C464.6,480,480,461.79,480,441.61V66.89C480.12,46.7,464.6,32,444.17,32ZM170.87,405.43H106.69V205.88h64.18ZM141,175.54h-.46c-20.54,0-33.84-15.29-33.84-34.43,0-19.49,13.65-34.42,34.65-34.42s33.85,14.82,34.31,34.42C175.65,160.25,162.35,175.54,141,175.54ZM405.43,405.43H341.25V296.32c0-26.14-9.34-44-32.56-44-17.74,0-28.24,12-32.91,23.69-1.75,4.2-2.22,9.92-2.22,15.76V405.43H209.38V205.88h64.18v27.77c9.34-13.3,23.93-32.44,57.88-32.44,42.13,0,74,27.77,74,87.64Z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="w-full md:w-1/3 px-3 pt-4 mx-2">
                        <button class="appearance-none flex items-center justify-center block w-full bg-gray-100 text-gray-700 p-3 leading-tight hover:bg-gray-200 hover:text-gray-700 focus:outline-none">
                            <svg class="h-6 w-6 fill-current text-gray-700" viewBox="0 0 512 512">
                                <path d="M496,109.5a201.8,201.8,0,0,1-56.55,15.3,97.51,97.51,0,0,0,43.33-53.6,197.74,197.74,0,0,1-62.56,23.5A99.14,99.14,0,0,0,348.31,64c-54.42,0-98.46,43.4-98.46,96.9a93.21,93.21,0,0,0,2.54,22.1,280.7,280.7,0,0,1-203-101.3A95.69,95.69,0,0,0,36,130.4C36,164,53.53,193.7,80,211.1A97.5,97.5,0,0,1,35.22,199v1.2c0,47,34,86.1,79,95a100.76,100.76,0,0,1-25.94,3.4,94.38,94.38,0,0,1-18.51-1.8c12.51,38.5,48.92,66.5,92.05,67.3A199.59,199.59,0,0,1,39.5,405.6,203,203,0,0,1,16,404.2,278.68,278.68,0,0,0,166.74,448c181.36,0,280.44-147.7,280.44-275.8,0-4.2-.11-8.4-.31-12.5A198.48,198.48,0,0,0,496,109.5Z"/>
                            </svg>
                        </button>
                    </div>
                    --}}
                </div>
            </div>
        </form>
    </div>
@endsection
