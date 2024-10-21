<?php

use App\Livewire\FolderManager;
use App\Livewire\ShareManager;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\User;


// Alias the Rule class


new class extends Component {
    use Toast;
    use WithPagination;
    use FolderManager, ShareManager;


    public string $search = '';
    public bool $addFolderModal = false;
    public bool $editFolderModal = false;
    public int $folderId;
    public array $sortBy = ['column' => 'updated_at', 'direction' => 'desc'];
    public int $filterCount = 0;
    public array $selected = [];

    #[Rule('required')]
    public string $name;


    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
        $this->success('Filters cleared.', position: 'toast-bottom');
    }

    public function delete(Folder $folder): void
    {
        $folder->delete();
        $this->warning("$folder->name deleted", 'Good bye!', position: 'toast-bottom');
    }


    public function headers(): array
    {
        return [
//            ['key' => 'avatar', 'label' => '', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'owner', 'label' => 'Owner', 'class' => 'hidden lg:table-cell'],
            ['key' => 'created_at', 'label' => 'Date Created'],
        ];
    }

    public function folders()
    {
        return Folder::query()
            ->whereNull('parent_id')
            ->where('folders.user_id', Auth::id())
            ->select('folders.*', 'users.name as owner')
            ->withCount(['files', 'children'])
            ->join('users', 'folders.user_id', '=', 'users.id')
            ->when($this->search, fn(Builder $q) => $q->where('folders.name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(100);
//         Perform a Scout search first
//        return Folder::search($this->search)
//            ->query(function (Builder $query) {
//                $query->whereNull('parent_id')
//                    ->where('folders.user_id', Auth::id())
//                    ->select('folders.*', 'users.name as owner')
//                    ->join('users', 'folders.user_id', '=', 'users.id')
//                    ->orderBy(...array_values($this->sortBy));
//            })
//            ->paginate(100);
    }


    public function bulkDelete()
    {
        $this->bulkDeleteItems($this->selected);
    }

    public function with(): array
    {
        return [
            'folders' => $this->folders(),
            'headers' => $this->headers(),
            'usersList' => User::all()->except(Auth::id()),

        ];
    }

    // Reset pagination when any component property changes
    public function updated($property): void
    {
        if (!is_array($property) && $property != "") {
            $this->resetPage();
        }
//        $this->filter_count = $this->calculateFilterCount();
    }


    public bool $moveItemModal = false;

    public function mount()
    {
        $this->getFolders();
    }



}; ?>

<div>
    {{--    @dd($folders)--}}
    <!--HEADER-->
    <x-header title="Home" separator progress-indicator>
        <x-slot:middle>
            {{--            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>--}}
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="New Folder" @click="$wire.addFolderModal = true;$wire.name=''" class="btn-primary"
                      icon="s-plus"/>
        </x-slot:actions>

    </x-header>

    <!-- TABLE  -->
    <x-card title="Folders">
        @if($folders->total() != 0)
            <x-table :headers="$headers" :rows="$folders" :sort-by="$sortBy"
                     class="table-xs"
                     wire:model="selected"
                     with-pagination
                     selectable
                     @row-selection="console.log($event.detail)"
                     link="/folders/{id}/show"
            >

                @scope('cell_name',$folder)
                <div>📂 <span class="hover:underline">{{$folder->name}}</span></div>
                <small class="ms-2">{{($folder->files_count + $folder->children_count ) .' items'}}</small>
                @endscope


                @scope('actions', $folder)
                <div class="flex">
                    <x-button icon="o-pencil"
                              @click="$wire.editFolderModal = true; $wire.name = '{{$folder['name']}}';
                          $wire.folderId={{$folder['id']}}" spinner
                              class="btn-ghost btn-sm text-blue-500"/>

                    <x-button icon="o-trash" wire:click="delete({{ $folder['id'] }})" wire:confirm="Are you sure?"
                              spinner
                              class="btn-ghost btn-sm text-red-500"/>
                </div>
                @endscope

            </x-table>
        @else
            <x-alert title="No files here." description="Click New Folder button add to Folders and start adding Files"
                     class="justify-items-center text-center" icon="o-exclamation-triangle">
            </x-alert>
        @endif
        <x-slot:menu>
            <template x-if="$wire.selected.length > 0">
                <div>
                    <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner
                              label="Delete Selected" icon="o-trash" class="btn-error"/>
                    <x-button @click="$wire.moveItemModal=true;
                    $wire.getFolders()
                    "
                              spinner
                              label="Move" icon="o-arrow-left-start-on-rectangle"
                              responsive class="btn-accent"/>
                    <x-button @click="$wire.shareModal=true;"
                              spinner
                              label="Share" icon="o-share"
                              responsive class="btn-accent"/>
                </div>
            </template>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>
        </x-slot:menu>
    </x-card>

    {{--ADD MODAL--}}
    <x-modal wire:model="addFolderModal" title="New Folder" box-class="w-80">
        <x-form wire:submit="saveFolder" no-separator>
            <div x-trap="$wire.addFolderModal">
                <x-input wire:model="name"/>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.addFolderModal = false"/>
                    <x-button type="submit" label="Create" class="btn-primary" spinner="saveFolder"/>
                </x-slot:actions>
            </div>
        </x-form>
    </x-modal>

    {{--EDIT MODAL--}}
    <x-modal wire:model="editFolderModal" title="Edit Folder" box-class="w-80">
        <x-form wire:submit="updateFolder($wire.folderId)" no-separator>
            <div x-trap="$wire.editFolderModal">
                <x-input wire:model="name"/>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.editFolderModal = false"/>
                    <x-button type="submit" label="Confirm" class="btn-primary" spinner="updateFolder"/>
                </x-slot:actions>

            </div>
        </x-form>
    </x-modal>

    <x-modal wire:model="moveItemModal" title="Move Items" box-class="w-4/5">
        <x-toast/>
        <x-hr/>
        <div x-data="{ folderName:null }">
            <ul class="menu menu-xs bg-base-200 rounded-lg w-full my-2">

                @foreach ($tree as $branch)
                    <li>
                        @if (isset($branch->children_tree) && count($branch->children_tree) > 0)
                            <details>
                                <summary
                                    @click="$wire.selectedFolder = {{ $branch->id }};
                                folderName = '{{$branch->name}}';"
                                    :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $branch->id }} }"
                                >
                                    📂 {{ $branch->name }}
                                </summary>
                                <ul>
                                    <x-folder-children :children="$branch->children_tree"/>
                                </ul>
                            </details>
                        @else
                            <a
                                @click="$wire.selectedFolder = {{ $branch->id }};folderName = '{{$branch->name}}';"
                                :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $branch->id }} }"
                            >
                                📂 {{ $branch->name }}
                            </a>
                        @endif
                    </li>
                @endforeach

            </ul>

            <div class="float-end">
                <x-button label="Cancel" @click="$wire.moveItemModal = false;
                $wire.selectedFolder=0;folderName=null"/>
                <button wire:click="moveItems" class=" btn btn-primary">
                    Move
                    <template x-if="folderName">
                        <span>
                            to <span x-text="folderName"></span>
                        </span>
                    </template>
                    <span wire:loading wire:target="moveItems">
                    <x-loading/>
                    </span>
                </button>
            </div>
        </div>

    </x-modal>

    {{--Share Folder MODAL--}}
    <x-modal wire:model="shareModal" title="Share Items" box-class="w-full max-h-screen" persistent>
        <x-form wire:submit="shareItems(true)" class="h-96" no-separator id="shareForm">
            <div class="flex flex-col h-96 justify-between">
                <div x-trap.inert="$wire.shareModal">
                    <x-choices-offline
                        label="Add user"
                        icon="o-users"
                        wire:model="shareTo"
                        option-label="name"
                        option-sub-label="email"
                        :options="$usersList"
                        hint="Add multiple users"
                        searchable
                        required
                    />
                </div>


                {{--                        <x-slot:actions>--}}
                <div class="text-end">
                    <x-button label="Cancel" @click="$wire.set('shareModal',false)"/>
                    <x-button type="submit" label="Share" class="btn-primary" spinner="saveFolder"/>
                </div>
                {{--                        </x-slot:actions>--}}
            </div>
        </x-form>


    </x-modal>
</div>
