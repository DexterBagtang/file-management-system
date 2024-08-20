<?php

namespace App\Livewire;

use App\Models\SharedItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Validate;
use Mary\Traits\Toast;

trait ShareManager
{
    use Toast;

    public bool $shareModal = false;
    public array $shareTo = [];


    public function shareItems($home=false)
    {

//        dd($home);
//        $this->validate();
        // Prepare an array to hold the shared items
        $sharedItems = [];

        // Loop through each selected item
        foreach ($this->selected as $item) {

            if (!$home){
                list($type, $id) = explode('-', $item);
            }else{
                $type = 'folder';
                $id = $item;
            }

            // Loop through each user to share the item with
            foreach ($this->shareTo as $userId) {
                // Check if the item is already shared with this user
                $exists = SharedItem::where('item_type', $type)
                    ->where('item_id', $id)
                    ->where('shared_with_id', $userId)
                    ->exists();

                // Only add to sharedItems array if not already shared
                if (!$exists) {
                    $sharedItems[] = [
                        'item_type' => $type,
                        'item_id' => $id,
                        'owner_id' => Auth::id(),
                        'shared_with_id' => $userId,
                        'permission' => 'read',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Insert all new shared items in one batch operation
        if (!empty($sharedItems)) {
            SharedItem::insert($sharedItems);
        }

        $this->reset(['shareTo', 'selected', 'shareModal']);

        $this->success('Items shared successfully');
    }



}
