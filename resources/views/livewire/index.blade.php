<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <x-header>
        <x-slot:middle class="!justify-center">
            <x-input icon="o-magnifying-glass" placeholder="Search..." class="!lg:w-full" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-funnel" />
            <x-button icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>
</div>
