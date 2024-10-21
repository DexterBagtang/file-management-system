<?php

use App\Models\SharedItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use App\Models\File;
use App\Models\Folder;

new class extends Component {

    public string $search = '';
    public array $sortBy = ['column' => 'updated_at', 'direction' => 'desc'];
    public array $selected = [];


    public function sharedItems()
    {
        // Initialize the query
        $query = SharedItem::with(['item', 'owner'])
            ->where('shared_with_id', Auth::id());

        // Apply search filters if $search has content
        if (!empty($this->search)) {
            $query->whereHas('item', function ($query) {
                $query->where('name', 'like', "%$this->search%");
            })
                ->orWhereHas('owner', function ($query) {
                    $query->where('name', 'like', "%$$this->search%");
                });
        }

        // Execute the query
        $sharedItems = $query
//            ->withCount('item')
            ->get();

        // Extract only the items from each shared item
        $items = $sharedItems->map(function ($sharedItem) {
            $item = $sharedItem;

            // Set the owner name and item name
            $item->owner = $sharedItem->owner->name ?? null;
            $item->name = $sharedItem->item->name ?? null;

            // Check if the item type is 'folder'
            if ($item->item_type == 'folder') {
                // Retrieve the folder with counts for files and children
                $folder = $sharedItem->item->loadCount(['files', 'children']);
                // Calculate the total count by summing files_count and children_count
                $item->item_count = $folder->files_count + $folder->children_count;
            } else {
                // If not a folder, set item_count to 0
                $item->item_count = 0;
            }

            return $item;
        });


        // Sort the items based on the specified column and direction
        return $items->sortBy([
            [$this->sortBy['column'], $this->sortBy['direction']]
        ]);

    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'owner', 'label' => 'Owner', 'class' => 'hidden lg:table-cell'],
            ['key' => 'created_at', 'label' => 'Date Shared'],
        ];
    }

    public function with()
    {
        return [
            'sharedItems' => $this->sharedItems(),
            'headers' => $this->headers(),
        ];
    }
};
?>

<div>
{{--    @dump($sharedItems)--}}
    <x-card title="Shared with me" shadow separator progress-indicator>
        <x-table :headers="$headers" :rows="$sharedItems" :sort-by="$sortBy"
                 wire:model="selected"
                 selectable
                 class="table-xs"
        >

            @scope('cell_name', $sharedItem)
            @if($sharedItem['item_type'] === 'folder')
                <a wire:navigate href="/shared/{{ $sharedItem['item_id'] }}/show">
                    <div>📂 <span class="hover:underline">{{ Str::limit($sharedItem['name'],100) }}</span></div>
                    <small class="ms-2">{{($sharedItem['item_count'] ) .' items'}}</small>
                </a>

            @else
                <a wire:navigate href="/file/{{$sharedItem['item_id']}}/view">
                    📄 <span class="hover:underline">{{ Str::limit($sharedItem['name'],100) }}</span>
                </a>
            @endif
            @endscope

        </x-table>

        <x-slot:menu>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>
        </x-slot:menu>
    </x-card>

</div>
