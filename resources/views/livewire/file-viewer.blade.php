<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use App\Models\File;

new class extends Component {
    public $fileToView;
    public $fileType;

    public function mount($id)
    {
        $this->fileToView = File::findOrFail($id);
        $this->fileType = $this->getFileType();
    }

    public function getFileType()
    {
        return pathinfo($this->fileToView->path, PATHINFO_EXTENSION);
    }
}; ?>

{{--<div>--}}

{{--    <x-card :subtitle="$fileToView->name" shadow separator>--}}
{{--        <x-slot:menu>--}}
{{--            <x-button x-data @click="history.back()" label="Back" icon="o-arrow-left" class="btn btn-primary btn-sm" />--}}
{{--        </x-slot:menu>--}}
{{--        <iframe class="w-full h-screen" src="{{ Storage::url($fileToView->path) }}" scrolling="no"></iframe>--}}
{{--        <img src="{{ Storage::url($fileToView->path) }}" alt="{{ $fileToView->name }}" class="w-full h-auto" />--}}

{{--    </x-card>--}}

{{--</div>--}}
<div>
    <x-card :subtitle="$fileToView->name" shadow >
        <x-slot:menu>
            <x-button x-data @click="history.back()" label="Back" icon="o-arrow-left" class="btn btn-primary btn-sm"/>
        </x-slot:menu>

        <div wire:loading>
            <p>Loading...</p>
        </div>

        @if($fileType == 'pdf')
            <iframe class="w-full h-screen" src="{{ Storage::url($fileToView->path)}}#view=FitH"></iframe>
        @elseif($fileType == 'jpg' || $fileType == 'png')
            <img src="{{ Storage::url($fileToView->path) }}" alt="{{ $fileToView->name }}" class="w-full h-auto"/>
        @endif
    </x-card>
</div>

