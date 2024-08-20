<?php

use App\Livewire\FileManager;
use App\Livewire\FolderManager;
use App\Livewire\ShareManager;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Spatie\LivewireFilepond\WithFilePond;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


new class extends Component {
    use WithFilePond;
    use WithFileUploads;
    use Toast, WithPagination;
    use FolderManager, FileManager, ShareManager;

    public $currentFolder;
    public string $search = '';
    public bool $addFolderModal = false;
    public bool $editFolderModal = false;
    public array $selected = [];
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public string $selectedTab = 'all';
    public bool $submitFile = false;
    public string $uploadStatusMessage = '';
    public string $name;
    public bool $isRenaming = false;
    public int $folderId;
    public bool $moveItemModal = false;
    public string $uploadOption;

//    #[Rule('required')]
    public array $file = [];
    public bool $addFile = false;
    public bool $renameFile = false;


    public function mount($id): void
    {
        $this->currentFolder = Folder::where('id', $id)
            ->where(function($query) {
                $query->where('user_id', Auth::id()) // Owned by user
                    ->orWhereHas('shares', function($query) {
                        $query->where('shared_with_id', Auth::id()); // Shared with user
                    })
                ;
            })
            ->first();

        if (!$this->currentFolder) {
            abort(403,"Unauthorized User"); // Throws a 404 error
        }

        $this->getFolders();
        $this->buildBreadcrumbs();
    }

    public function mergedData()
    {
        $folders = [];
        $files = [];

        if ($this->selectedTab == 'all' || $this->selectedTab == 'folders') {
            // Fetch folders
            $folders = Folder::query()
                ->select('folders.*', 'users.name as owner', DB::raw("'folder' as type"))
                ->withCount(['files','children'])
                ->where('parent_id', $this->currentFolder->id ?? null)
                ->join('users', 'folders.user_id', '=', 'users.id')
                ->when($this->search, fn($q) => $q->where('folders.name', 'like', "%$this->search%"))
                ->get()
                ->toArray();
        }

        if ($this->selectedTab == 'all' || $this->selectedTab == 'files') {

            // Perform a Scout search
            $filesQuery = File::search($this->search)
                ->query(function (Builder $query) {
                    $query->select('files.*', 'users.name as owner', DB::raw("'file' as type"))
                        ->join('users', 'files.user_id', '=', 'users.id')
                        ->where('folder_id', $this->currentFolder->id ?? null);
                })->get();
            $files = $filesQuery->toArray();
        }

        // Merge folders and files based on the selected tab
        $merged = array_merge($folders, $files);
//        dd($merged);

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


    public function with()
    {
        return [
            'folderFiles' => $this->mergedData(),
            'headers' => $this->headers(),
            'formattedJson' => $this->formattedArray(),
            'usersList' => User::all()->except(Auth::id()),
        ];
    }

    public function headers()
    {
        return [
            ['key' => 'select', 'label' => '#', 'class' => 'w-1', 'sortable' => false],
//            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'owner', 'label' => 'Owner', 'class' => 'hidden lg:table-cell'],
            ['key' => 'created_at', 'label' => 'Date Created'],
            ['key' => 'type', 'label' => 'Type'],
        ];
    }


    public function delete($id, $type)
    {
        if ($type == 'folder') {
            $file = Folder::findOrFail($id);
        } else {
            $file = File::findOrFail($id);
        }

        $file->delete();

        $this->warning("$type deleted", 'Good bye!', position: 'toast-bottom');

    }

    public function bulkDelete()
    {
        $this->bulkDeleteItems($this->selected, false);
    }


    public function updated($property)
    {
        if (!is_array($property) && $property != "") {
            $this->resetPage();
        }
        if ($property === 'file') {
            $this->submitFile = true;
        }

        if ($property === 'addFolderModal' || $property === 'editFolderModal' || $property === 'renameFile' || $property === 'addFile') {
            if (!$this->$property) {
                $this->resetValidation();
                $this->reset('name');
            }
        }

    }


    public function formattedArray()
    {
        $formattedArray = $this->mergedData()->map(function ($item) {
            return $item['type'] . '-' . $item['id'];
        })->toArray();
        return json_encode($formattedArray);
    }


};
?>

<div>
    <x-header title="" separator progress-indicator>
        {{--        <x-slot:middle>--}}
        {{--            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>--}}
        {{--        </x-slot:middle>--}}
        <x-slot:actions>
            {{--            <x-button label="New" @click="$wire.drawer = true;$wire.name=''" class="btn-primary" icon="s-plus"/>--}}
            {{-- Custom trigger  --}}
            <x-dropdown>
                <x-slot:trigger>
                    <x-button label="New" icon="s-plus" class="btn-primary"/>
                </x-slot:trigger>

                <x-menu-item title="Folder" @click="$wire.addFolderModal = true;$wire.name=''" icon="o-folder-plus"/>
                <x-menu-separator/>
                <x-popover position="left">
                    <x-slot:trigger>
                        <x-menu-item title="Upload File with Filepond"
                                     @click="$wire.addFile = true;$wire.uploadOption='filepond'"
                                     icon="o-document-arrow-up"/>
                    </x-slot:trigger>
                    <x-slot:content class="w-36 text-xs text-wrap rounded-lg">
                        Advance File Uploader provides enhanced
                        features such as drag-and-drop, image previews,
                        and customizable file handling.
                    </x-slot:content>
                </x-popover>

                <x-menu-item title="Standard File Upload"
                             @click="$wire.addFile = true;$wire.uploadOption='standard'"
                             icon="o-document-arrow-up"/>
            </x-dropdown>
        </x-slot:actions>

    </x-header>
    <div class="breadcrumbs text-xs">
        <ul>
            <x-menu-item title="Home" icon="o-home" link="/"/>
            {{--            <x-menu-item :title="$currentFolder->name" icon="o-folder"/>--}}
            @foreach($breadcrumbs as $breadcrumb)
                <x-menu-item :title="$breadcrumb->name" icon="o-folder" link="/folders/{{{$breadcrumb->id}}}/show"/>
            @endforeach
        </ul>
    </div>

    <x-card :title="$currentFolder->name">

        <x-table-tabs :selectedTab="$selectedTab"/>

        <div class="divider"></div>

        <x-selection-actions :formattedJson="$formattedJson"/>

        @if(count($folderFiles) != 0)
            <x-table :headers="$headers" :rows="$folderFiles"
                     class="table-xs" :sort-by="$sortBy">

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
                    <a wire:navigate href="/folders/{{ $folderFile['id'] }}/show">
                        <div>📂 {{ Str::limit($folderFile['name'],60) }}</div>
                        <small class="ms-2">{{($folderFile['files_count'] + $folderFile['children_count'] ) .' items'}}</small>
                    </a>



                @else

                    <a wire:navigate href="/file/{{$folderFile['id']}}/view">
                        📄 {{ Str::limit($folderFile['name'],60) }}
                    </a>
                @endif
                @endscope

                @scope('cell_created_at',$folderFile)
                <div>
                    {{Carbon::parse($folderFile['created_at'])->diffForHumans()}}
                </div>
                @endscope


                @scope('actions', $folderFile)
                <div class="flex">
                    @if($folderFile['type'] == 'folder')
                        <x-button icon="o-pencil"
                                  @click="$wire.editFolderModal = true;
                              $wire.name = '{{$folderFile['name']}}';
                              $wire.isRenaming = true;
                              $wire.folderId={{$folderFile['id']}}" spinner
                                  class="btn-ghost btn-sm text-blue-500"/>
                    @else
                        <x-button icon="o-pencil"
                                  @click="$wire.renameFile = true;
                              $wire.name = '{{pathinfo($folderFile['name'],PATHINFO_FILENAME)}}';
                              $wire.folderId={{$folderFile['id']}}" spinner
                                  class="btn-ghost btn-sm text-blue-500"/>
                    @endif


                    <x-button icon="o-trash" wire:click="delete({{$folderFile['id']}},'{{$folderFile['type']}}')"
                              wire:confirm="Are you sure?"
                              spinner
                              class="btn-ghost btn-sm text-red-500"/>
                </div>
                @endscope

            </x-table>

        @else
            <x-alert title="No items here." description="Click New button to add Folders or Upload Files"
                     class="justify-items-center text-center" icon="o-exclamation-triangle">
            </x-alert>
        @endif

        <x-slot:menu>
            <template x-if="$wire.selected.length > 0">
                <div>
                    <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner
                              label="Delete Selected" icon="o-trash" responsive class="btn-error"/>
                    <x-button @click="$wire.moveItemModal=true;$wire.getFolders()"
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

    {{--ADD Folder MODAL--}}
    <x-modal wire:model="addFolderModal" title="New Folder" box-class="w-80">
        <x-form wire:submit="saveFolder" no-separator>
            <div x-trap.inert="$wire.addFolderModal">
                <x-input wire:model="name" id="folder-name"/>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.set('addFolderModal',false)"/>
                    <x-button type="submit" label="Create" class="btn-primary" spinner="saveFolder"/>
                </x-slot:actions>
            </div>
        </x-form>
    </x-modal>

    {{--EDIT MODAL--}}
    <x-modal wire:model="editFolderModal" title="Rename Folder" box-class="w-80">
        <x-form wire:submit="updateFolder($wire.folderId)" no-separator>
            <div x-trap.inert="$wire.editFolderModal">
                <x-input wire:model="name"/>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.set('editFolderModal',false);$wire.cancelModal()"/>
                    <x-button type="submit" label="Confirm" class="btn-primary" spinner="updateFolder"/>
                </x-slot:actions>
            </div>
        </x-form>
    </x-modal>

    {{--Rename file MODAL--}}
    <x-modal wire:model="renameFile" title="Rename File" box-class="w-80">
        <x-form wire:submit="updateFilename($wire.folderId)" no-separator>
            <div x-trap.inert="$wire.renameFile">
                <x-input wire:model="name"/>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.set('renameFile',false);$wire.cancelModal"/>
                    <x-button type="submit" label="Confirm" class="btn-primary" spinner="save"/>
                </x-slot:actions>
            </div>
        </x-form>
    </x-modal>


    {{--    ADD File MODAL--}}
    <x-modal wire:model="addFile" title="Upload File" box-class="w-4/5 m-auto" persistent>
        <x-form wire:submit="saveFile" no-separator>
            <x-hr/>

            <template x-if="$wire.uploadOption === 'filepond'">
                <div>
                    <x-filepond::upload wire:model="file"
                                        type="file"
                                        allow-reorder
                                        item-insert-interval="0"
                                        multiple
                                        drop-on-page
                                        required
                                        drop-on-element="false"
                    />
                    @error("file.*")
                    <x-alert :title="$message" icon="o-exclamation-triangle" class="text-xs alert-error"/>
                    @enderror
                </div>
            </template>

            <template x-if="$wire.uploadOption === 'standard'">
                <x-file wire:model="file" label="" required multiple/>
            </template>
            <div wire:loading wire:target="file">Uploading...
                <x-loading/>
            </div>

            {{--            <template x-if="$wire.processingFiles">--}}
            {{--                <div class="mt-2">--}}
            {{--                    <div class="flex mb-2 items-center justify-between align-middle">--}}
            {{--                        <div x-html="$wire.uploadStatusMessage"--}}
            {{--                             class="text-xs truncate w-1/2 max-w-64 text-ellipsis overflow-hidden ..."></div>--}}
            {{--                        <div class="text-xs font-semibold mt-1 text-teal-600">--}}
            {{--                            <span x-text="Math.round($wire.progress) + '%'"></span>--}}
            {{--                        </div>--}}
            {{--                    </div>--}}

            {{--                    <x-progress value="{{$progress}}" max="100" class="progress-primary h-0.5"/>--}}
            {{--                </div>--}}
            {{--            </template>--}}
            <div class="flex justify-end">
                <x-button label="Cancel"
                          {{--                              @click="$wire.addFile = false;$wire.cancelModal()"--}}
                          {{--                              @click="$wire.$refresh();$wire.addFile = false;"--}}
                          @click="$wire.addFile = false;window.location.reload()"
                />
                <button class="btn btn-primary">
                    <span wire:loading.remove>Submit</span>
                    <span wire:loading wire:target="saveFile">Submitting</span>
                    <span><x-loading wire:loading class="loading-dots"/></span>
                </button>
            </div>
        </x-form>
    </x-modal>

    {{--Move items MODAL--}}
    <x-modal wire:model="moveItemModal" title="Move Items" box-class="w-4/5">
        <x-toast/>
        <x-hr/>
        <div x-data="{folderName:null }">
            <ul class="menu menu-xs bg-base-200 rounded-lg w-full my-2">
                @foreach ($tree as $folder)
                    <li>
                        @if (isset($folder->children_tree) && count($folder->children_tree) > 0)
                            <details>
                                <summary
                                    @click="$wire.selectedFolder = {{ $folder->id }};
                                    folderName = '{{$folder->name}}';"
                                    :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $folder->id }} }"
                                >
                                    📂 {{ $folder->name }}
                                </summary>
                                <ul>
                                    <x-folder-children :children="$folder->children_tree"/>
                                </ul>
                            </details>
                        @else
                            <a
                                @click="$wire.selectedFolder = {{ $folder->id }};folderName = '{{$folder->name}}';"
                                :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $folder->id }} }"
                            >
                                📂 {{ $folder->name }}
                            </a>
                        @endif
                    </li>
                @endforeach

            </ul>
            <div class="flex justify-between align-middle">
                <div class="">

                </div>

                <div class="">
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

        </div>

    </x-modal>

    {{--Share Folder MODAL--}}
    <x-modal wire:model="shareModal" title="Share Items" box-class="w-full max-h-screen" persistent>
        <x-form wire:submit="shareItems" class="h-96" no-separator id="shareForm">
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



@push('scripts')

@endpush
