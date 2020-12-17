<x-jet-action-section>
    <x-slot name="title">
        {{ __('Push Notification') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Ping your clients.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600">
            {{ __('Pinging your clients help you to test your devices and clients.') }}
        </div>

        <div class="mt-5">
            <x-jet-button wire:click="pushNotification" wire:loading.attr="disabled">
                {{ __('Push') }}
            </x-jet-button>
        </div>
    </x-slot>
</x-jet-action-section>
