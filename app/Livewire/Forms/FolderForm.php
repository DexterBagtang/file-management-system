<?php

namespace App\Livewire\Forms;

use App\Models\Folder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Mary\Traits\Toast;

class FolderForm extends Form
{


    public function save($currentFolderId)
    {

        Folder::create([
            'name' => $this->name,
            'parent_id' => $currentFolderId ?? null,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name']);

    }


}
