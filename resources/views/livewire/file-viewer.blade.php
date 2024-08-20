<?php

use App\Livewire\FolderManager;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use App\Models\File;
use Illuminate\Support\Facades\Auth;

new class extends Component {

    use FolderManager;

    public $fileToView;
    public $fileType;

    public function mount($id)
    {
        $userId = Auth::id();

        // Attempt to find the file based on ownership or sharing
        $file = File::where('id', $id)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereHas('shares', fn($q) => $q->where('shared_with_id', $userId));
            })
            ->first();

        // If file is found, assign it to fileToView
        if ($file) {
            $this->fileToView = $file;
        } else {
            // Retrieve the file to check its folder_id if needed
            $file = File::find($id);

            // Check if the file exists and if its parent folder is shared
            if ($file && $this->areParentsShared($file->folder_id)) {
                $this->fileToView = $file;
            }
        }

        // Abort with an unauthorized error if the file is still not found
        if (!$this->fileToView) {
            abort(403, 'Unauthorized User');
        }

        // Set the file type
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
    <x-card :subtitle="$fileToView->name" shadow>
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

