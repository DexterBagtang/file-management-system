<?php

use Livewire\Volt\Component;
use App\Models\Folder;
use App\Models\File;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;


new class extends Component {

    use Toast;

    public string $search = '';
    public array $selected = [];
    public array $sortBy = ['column' => 'deleted_at', 'direction' => 'desc'];
    public string $selectedTab = 'all';
    public bool $deleteModal = false;


    public function mergedData()
    {
        $folders = [];
        $files = [];

        // Fetch folders if applicable
        if ($this->selectedTab == 'all' || $this->selectedTab == 'folders') {
            $foldersQuery = Folder::onlyTrashed()
                ->select('folders.*', 'users.name as owner', DB::raw("'folder' as type"))
                ->join('users', 'folders.user_id', '=', 'users.id');

            if ($this->search) {
                $foldersQuery->where(function (Builder $q) {
                    $q->where('folders.name', 'like', "%$this->search%");
                });
            }

            $folders = $foldersQuery->get()->toArray();
        }

        // Fetch files if applicable
        if ($this->selectedTab == 'all' || $this->selectedTab == 'files') {
            $filesQuery = File::onlyTrashed()
                ->select('files.*', 'users.name as owner', DB::raw("'file' as type"))
                ->join('users', 'files.user_id', '=', 'users.id');

            if ($this->search) {
                $filesQuery->where(function (Builder $q) {
                    $q->where('files.name', 'like', "%$this->search%")
                        ->orWhere('files.contents', 'like', "%$this->search%");
                });
            }

            $files = $filesQuery->get()->toArray();
        }

        // Merge folders and files based on the selected tab
        $merged = array_merge($folders, $files);

        // Convert to collection for sorting and pagination
        $collection = collect($merged);

        // Sort the collection based on the sortBy property
        $collection = $collection->sortBy([
            [$this->sortBy['column'], $this->sortBy['direction']]
        ]);

        return $collection; // Adjust the pagination as needed
    }

    public function selectTab($tab)
    {
        $this->selectedTab = $tab;
        $this->reset(['selected']);
    }

    public function headers()
    {
        return [
            ['key' => 'select', 'label' => '#', 'class' => 'w-1', 'sortable' => false],
//            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'owner', 'label' => 'Owner', 'class' => 'hidden lg:table-cell'],
            ['key' => 'deleted_at', 'label' => 'Date Deleted'],
            ['key' => 'type', 'label' => 'Type'],
        ];
    }

    public function formattedArray()
    {
        $formattedArray = $this->mergedData()->map(function ($item) {
            return $item['type'] . '-' . $item['id'];
        })->toArray();
        return json_encode($formattedArray);
    }

    public function bulkDelete()
    {
        foreach ($this->selected as $item) {
            list($type, $id) = explode('-', $item);

            if ($type === 'folder') {
                Folder::query()->where('id', $id)->forceDelete();
            } elseif ($type === 'file') {
                File::query()->where('id', $id)->forceDelete();
            }

        }

        $this->reset(['selected','deleteModal']);

        $this->warning("Selected items deleted forever", 'Good bye!', position: 'toast-bottom');
    }

    public function bulkRestore()
    {
        foreach ($this->selected as $item) {
            list($type, $id) = explode('-', $item);

            if ($type === 'folder') {
                Folder::query()->where('id', $id)->restore();
            } elseif ($type === 'file') {
                File::query()->where('id', $id)->restore();
            }

        }

        $this->reset('selected');

        $this->success("Selected items restored",'', position: 'toast-bottom');
    }

    public function with()
    {
        return [
            'folderFiles' => $this->mergedData(),
            'headers' => $this->headers(),
            'formattedJson' => $this->formattedArray(),
        ];
    }
}; ?>

<div>
    <x-header title="" separator progress-indicator>
        <x-slot:middle>
            <x-input placeholder="Search..." wire:model.live.debounce="search"
                     clearable icon="o-magnifying-glass"/>
        </x-slot:middle>
        <x-slot:actions>
        </x-slot:actions>
    </x-header>

    <x-card title="Trash" class="">

        {{--        @if(count($folderFiles) > 0)--}}
        <div class="join">
            <x-button wire:click="selectTab('files')"
                      class="btn btn-outline btn-sm {{$selectedTab == 'files' ? 'btn-active':''}}  join-item rounded-l-full"
                      icon="o-document" spinner responsive>
                Files
            </x-button>
            <x-button wire:click="selectTab('all')"
                      class="btn btn-outline btn-sm {{$selectedTab == 'all' ? 'btn-active':''}} join-item"
                      icon="o-document" spinner responsive>
                All
            </x-button>
            <x-button wire:click="selectTab('folders')"
                      class="btn btn-outline btn-sm {{$selectedTab == 'folders' ? 'btn-active':''}}  join-item rounded-r-full"
                      icon="o-folder" spinner responsive>
                Folders
            </x-button>
        </div>
        {{--        @endif--}}


        <div class="divider"></div>


        <x-selection-actions :formattedJson="$formattedJson" />


    @if(count($folderFiles) != 0)
            <x-table :headers="$headers" :rows="$folderFiles" class="table-sm" :sort-by="$sortBy">

                @scope('header_select', $folderFile)
                @endscope


                @scope('cell_select',$folderFile)
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm checkbox-primary"
                    wire:model="selected"
                    value="{{ $folderFile['type'] }}-{{ $folderFile['id'] }}"/>
                @endscope


                @scope('cell_name', $folderFile)
                @if($folderFile['type'] === 'folder')
                    <a class="truncate" href="/folders/{{ $folderFile['id'] }}/show">
                        📂 {{ Str::limit($folderFile['name'],70) }}
                    </a>
                @else
                                        <a href="/files/{{ $folderFile['id'] }}">
                    <a class="" href="{{Storage::url($folderFile['path'])}}" target="_blank">
                        📄 {{ Str::limit($folderFile['name'],70) }}
                    </a>
                @endif
                @endscope

                @scope('cell_deleted_at',$folderFile)
                <div>
                    {{\Carbon\Carbon::parse($folderFile['deleted_at'])->diffForHumans()}}
                </div>
                @endscope


                @scope('actions', $folderFile)
                <x-dropdown >
                    <x-slot:trigger>
                        <x-button icon="o-ellipsis-vertical" class="btn-circle btn-ghost btn-sm"
                                  @click="$wire.selected = [];$wire.selected.push('{{$folderFile['type'] .'-'. $folderFile['id'] }}')" />
                    </x-slot:trigger>

                    <x-menu-item title="Delete forever" class="overflow-visible"
                                 @click="$wire.deleteModal=true;"
                                 icon="o-trash" />

                    <x-menu-item wire:click="bulkRestore" spinner class="overflow-visible"
                                 title="Restore" icon="o-arrow-path" />
                </x-dropdown>
                @endscope

            </x-table>

        @else
            <x-alert title="No items here." description="Items in the trash will be deleted forever after 30 days"
                     class="justify-items-center text-center" icon="o-exclamation-triangle">
            </x-alert>
        @endif

        <x-slot:menu>
            <template x-if="$wire.selected.length > 0">
                <div>
{{--                    <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner--}}
{{--                              label="Delete Forever" icon="o-trash" responsive class="btn-error"/>--}}

                    <x-button label="Delete forever" icon="o-trash" responsive
                              class="btn-error"
                              @click="$wire.deleteModal = true" />


                    <x-button wire:click="bulkRestore"
                              wire:confirm="Are you sure to restore the selected items?" spinner
                              label="Restore" icon="o-arrow-path" responsive class="btn-info"/>
                </div>
            </template>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>

        </x-slot:menu>

    </x-card>

    {{--Bulk Delete Forever Modal--}}
    <x-modal wire:model="deleteModal" title="Delete forever?">
        <div>Selected items will be deleted forever and you won't be able to restore it.</div>

        <x-slot:actions>
            <x-button label="Cancel" class="btn-outline" @click="$wire.deleteModal = false" />
            <x-button wire:click="bulkDelete" label="Delete forever" icon="o-trash" class="btn-error rounded-2xl" spinner />
        </x-slot:actions>
    </x-modal>
</div>
