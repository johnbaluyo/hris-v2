<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="flex items-center gap-3" role="group" aria-label="Color theme">
            <flux:button type="button" data-set-theme="light" aria-pressed="false"><i class="fa-solid fa-sun" aria-hidden="true"></i> Light</flux:button>
            <flux:button type="button" data-set-theme="dark" aria-pressed="false"><i class="fa-solid fa-moon" aria-hidden="true"></i> Dark</flux:button>
            <flux:button type="button" data-set-theme="system" aria-pressed="false"><i class="fa-solid fa-desktop" aria-hidden="true"></i> System</flux:button>
        </div>
    </x-pages::settings.layout>
</section>
