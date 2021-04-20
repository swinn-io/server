<x-jet-action-section>
    <x-slot name="title">
        {{ __('Delete :name', ['name' => $contact->name]) }}
    </x-slot>

    <x-slot name="description">
        {{ __('Delete from your contacts.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600">
            {{ __('Once your contact is deleted, all of its data will be sent from unknown UUID.') }}
        </div>

        <div class="mt-5">
            <x-jet-danger-button wire:click="confirmContactDeletion" wire:loading.attr="disabled">
                {{ __('Delete Contact') }}
            </x-jet-danger-button>
        </div>

        <!-- Delete Contact Confirmation Modal -->
        <x-jet-dialog-modal wire:model="confirmingContactDeletion">
            <x-slot name="title">
                {{ __('Delete :name', ['name' => $contact->name]) }}
            </x-slot>

            <x-slot name="content">
                {{ __('Are you sure you want to delete your contact? Once your account is deleted, all of its data will not be deleted, instead will be unknown.') }}

                <div class="mt-4" x-data="{}" x-on:confirming-delete-contact.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-jet-input type="password" class="mt-1 block w-3/4" placeholder="{{ __('Password') }}"
                                 x-ref="password"
                                 wire:model.defer="password"
                                 wire:keydown.enter="deleteContact" />

                    <x-jet-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-jet-secondary-button wire:click="$toggle('confirmingContactDeletion')" wire:loading.attr="disabled">
                    {{ __('Nevermind') }}
                </x-jet-secondary-button>

                <x-jet-danger-button class="ml-2" wire:click="deleteContact" wire:loading.attr="disabled">
                    {{ __('Delete Contact') }}
                </x-jet-danger-button>
            </x-slot>
        </x-jet-dialog-modal>
    </x-slot>
</x-jet-action-section>
