<x-jet-form-section submit="updateContactInformation">
    <x-slot name="title">
        {{ __('Contact Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your contact\'s information.') }}
    </x-slot>

    <x-slot name="form">
        <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
            <div class="mt-2" x-show="! photoPreview">
                @component('components.qrcode', ['content' => $contact->id])@endcomponent
            </div>
        </div>
        <div class="col-span-6 sm:col-span-4">
            {{ $contact->name }}
        </div>
        <div class="col-span-6 sm:col-span-4">
            {{ __('Created At: :date', ['date' => $contact->user->created_at]) }}
        </div>
    </x-slot>
</x-jet-form-section>
