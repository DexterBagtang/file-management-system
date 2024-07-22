<?php

use App\Livewire\Forms\FileForm;
use App\Livewire\Forms\FolderForm;
use App\Models\File;
use App\Models\Folder;
use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Spatie\LivewireFilepond\WithFilePond;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;



new class extends Component {
    use Toast, WithPagination, WithFilePond, WithFileUploads;

    public FolderForm $folderForm;
    public FileForm $fileForm;
    public Folder $currentFolder;
    public string $search = '';
    public bool $addModal = false;
    public array $selected = [];
    public array $sortBy = ['column' => 'id', 'direction' => 'desc'];
    public array $breadcrumbs = [];
    public string $selectedTab = 'all';
    public bool $submitFile = false;


    public string $name;

    #[Validate(['file.*' => 'max:1024'])]
    public array $file = [];

    public bool $addFile = false;


    public function mount($id): void
    {
        $this->currentFolder = Folder::findOrFail($id);
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
                ->where('parent_id', $this->currentFolder->id ?? null)
                ->join('users', 'folders.user_id', '=', 'users.id')
                ->when($this->search, fn($q) => $q->where('folders.name', 'like', "%$this->search%"))
                ->get()
                ->toArray();
        }

        if ($this->selectedTab == 'all' || $this->selectedTab == 'files') {
            // Fetch files
            $files = File::query()
                ->select('files.*', 'users.name as owner', DB::raw("'file' as type"))
                ->where('folder_id', $this->currentFolder->id ?? null)
                ->join('users', 'files.user_id', '=', 'users.id')
                ->when($this->search, fn($q) => $q->where('files.name', 'like', "%$this->search%"))
                ->get()
                ->toArray();
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


    public function with()
    {
        return [
            'folderFiles' => $this->mergedData(),
            'headers' => $this->headers(),
            'formattedJson' => $this->formattedArray(),
        ];
    }


    public function headers()
    {
        return [
            ['key' => 'select', 'label' => '#', 'class' => 'w-1'],
//            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'owner', 'label' => 'Owner', 'class' => 'hidden lg:table-cell'],
            ['key' => 'created_at', 'label' => 'Date Created'],
            ['key' => 'type', 'label' => 'Type'],
        ];
    }


    public function save()
    {
        $this->validate([
            'name' => ['required',
                Rule::unique('folders')->where(function ($query) {
                    return $query->where('parent_id', $this->currentFolder->id ?? null);
                })
            ]
        ], [
            'name.unique' => 'A folder with this name already exists in this location. Please choose a different name.',
        ]);
        Folder::create([
            'name' => $this->name,
            'parent_id' => $this->currentFolder->id ?? null,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name']);

        $this->addModal = false;

        $this->success('Folder created successfully.');
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
        foreach ($this->selected as $item) {
            list($type, $id) = explode('-', $item);

            if ($type === 'folder') {
                Folder::destroy($id);
            } elseif ($type === 'file') {
                File::destroy($id);
            }

        }

        $this->reset('selected');

        $this->warning("Selected items deleted", 'Good bye!', position: 'toast-bottom');
    }

    public function buildBreadcrumbs()
    {
        $this->breadcrumbs = [];
        $folder = Folder::find($this->currentFolder->id ?? null);

        while ($folder) {
            array_unshift($this->breadcrumbs, $folder);
            $folder = $folder->parent;
        }
    }


//    public function updating($property, $value)
//    {
//        if ($property === 'file') {
//            // Reset the submitFile flag when files are updated (upload starts)
//            $this->submitFile = false;
//        }
//    }


    public function updated($property)
    {
        if (!is_array($property) && $property != "") {
            $this->resetPage();
        }
        if ($property === 'file') {
                $this->submitFile = true;
        }
    }

    public function formattedArray()
    {
        $formattedArray = $this->mergedData()->map(function ($item) {
            return $item['type'] . '-' . $item['id'];
        })->toArray();
        return json_encode($formattedArray);
    }

    public function selectAll()
    {
        $this->reset(['search', 'selected']);
//        $all = $this->mergedData()->map(function ($item) {
//            return ['id' => $item['id'], 'type' => $item['type']];
//        })->toArray();
        foreach ($this->mergedData() as $fileData) {
            $format = $fileData['type'] . '-' . $fileData['id'];
            $this->selected[] = $format;
        }

//        $this->selected = $all;
//        dd($this->selected);
        $this->success('All folders selected');
    }

    public function unselectAll()
    {
        $this->reset('selected');
        $this->selected = [];
        $this->success('All folders unselected.');
    }

    public function saveFile()
    {
        $this->validate();

        // Retrieve existing file names for the current folder
        $existingFileNames = File::where('folder_id', $this->currentFolder->id)
            ->pluck('name')
            ->toArray();

        // Process each file
        if ($this->file) {
            foreach ($this->file as $item) {
                $originalName = $item->getClientOriginalName();
                $name = pathinfo($originalName, PATHINFO_FILENAME);
                $extension = $item->getClientOriginalExtension();
                $newName = $originalName;

                // Check if the file name exists and rename if necessary
                $counter = 1;
                while (in_array($newName, $existingFileNames)) {
                    $newName = $name . " ($counter)." . $extension;
                    $counter++;
                }

                $url = $item->storeAs($this->currentFolder->id, $newName);
                File::create([
                    'name' => $newName,
                    'user_id' => Auth::id(),
                    'folder_id' => $this->currentFolder->id,
                    'path' => "/storage/$url",
                ]);
            }
        }

        // Reset file inputs if needed
        $this->reset(['file', 'addFile']);

        // Redirect and success message
        $this->redirect(url()->previous(), true);
        $this->success('Files added successfully', 'yeah');

    }

    public function cancelModal()
    {
        $this->reset('file');
    }




};
?>

<div>
    <x-header title="" separator progress-indicator>
        <x-slot:middle>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>
        </x-slot:middle>
        <x-slot:actions>
            {{--            <x-button label="New" @click="$wire.drawer = true;$wire.name=''" class="btn-primary" icon="s-plus"/>--}}
            {{-- Custom trigger  --}}
            <x-dropdown>
                <x-slot:trigger>
                    <x-button label="New" icon="s-plus" class="btn-primary"/>
                </x-slot:trigger>

                <x-menu-item title="Folder" @click="$wire.addModal = true;$wire.name=''" icon="o-folder-plus"/>
                <x-menu-separator/>
                <x-menu-item title="Upload File" @click="$wire.addFile = true;$wire.name=''"
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


        <div class="divider"></div>


        <div x-data="{
                selected: @entangle('selected'),
                isSelected:false,
                addAllItems() {
                    let items = JSON.parse(@js($formattedJson));
                    if (Array.isArray(items)) {
                        items.forEach(item => this.selected.push(item));
                    } else {
                        console.error('Parsed items is not an array:', items);
                        // Convert object to array
                        items = Object.values(items);
                        items.forEach(item => this.selected.push(item));
                    }
                    this.isSelected = true;
                },
                unselectAll() {
                    this.selected = [];
                    this.isSelected = false;
                }
            }">
            <template x-if="$wire.selected.length > 0">
                <div class="flex justify-between">
                    <x-button class="btn-xs" spinner>
                        <span x-text="$wire.selected.length"></span>
                        items selected
                    </x-button>

                    <div class="flex justify-around">
                        <template x-if="!isSelected">
                            <x-button
                                @click="addAllItems"

                                {{--                        wire:click="selectAll" --}}
                                spinner
                                class="btn-primary btn-outline btn-xs">
                                Select All
                            </x-button>
                        </template>

                        <div class="divider m-0 divider-horizontal"></div>
                        <x-button
                            @click="unselectAll"
                            {{--                        wire:click="unselectAll" --}}
                            spinner
                            class="btn-error btn-outline btn-xs">
                            Unselect All
                        </x-button>
                    </div>

                </div>

            </template>
        </div>


        @if(count($folderFiles) != 0)
            <x-table :headers="$headers" :rows="$folderFiles" class="table-xs" :sort-by="$sortBy">

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
                        📂 {{ $folderFile['name'] }}
                    </a>
                @else
                    <a href="/files/{{ $folderFile['id'] }}">
                        📄 {{ $folderFile['name'] }}
                    </a>
                @endif
                @endscope


                @scope('actions', $folderFile)
                <div class="flex">
                    <x-button icon="o-pencil"
                              @click="$wire.editModal = true; $wire.name = '{{$folderFile['name']}}';
                              $wire.folderId={{$folderFile['id']}}" spinner
                              class="btn-ghost btn-sm text-blue-500"/>

                    {{--                    <x-button icon="o-trash" wire:click="delete({{$folderFile['id']}},{{$folderFile['type']}})" wire:confirm="Are you sure?"--}}
                    {{--                              spinner--}}
                    {{--                              class="btn-ghost btn-sm text-red-500"/>--}}

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
                <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner
                          label="Delete Selected" icon="o-trash" responsive class="btn-error"/>
            </template>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>

        </x-slot:menu>

    </x-card>

    {{--ADD MODAL--}}
    <x-modal wire:model="addModal" title="New Folder" box-class="w-80">
        <x-form wire:submit="save" no-separator>
            <x-input wire:model="name" id="folder-name"/>
            <x-slot:actions>
                <x-button type="submit" label="Confirm" class="btn-primary" spinner="save"/>
                <x-button label="Cancel" @click="$wire.addModal = false"/>
            </x-slot:actions>
        </x-form>
    </x-modal>


{{--    ADD File MODAL--}}
    <x-modal wire:model="addFile" title="Upload File" box-class="w-4/5 m-auto">
        <x-form wire:submit="saveFile" no-separator>
            <x-hr />
            <x-filepond::upload wire:model="file"
                                type="file"
                                allow-reorder
                                item-insert-interval="0"
                                multiple
                                drop-on-page
                                required
                                drop-on-element="false"
            />

            @error("file.*")<x-alert :title="$message" icon="o-exclamation-triangle"/>@enderror

            <div class="flex justify-end">
                <x-button label="Cancel" @click="$wire.addFile = false;$wire.cancelModal()"/>
                <button class="btn btn-primary">
                    <span wire:loading.remove>Upload</span>
                    <span wire:loading>Uploading</span>
                    <span><x-loading wire:loading class="loading-dots" /></span>
                </button>
            </div>
{{--            <x-slot:actions>--}}
{{--                <x-button label="Cancel" @click="$wire.addFile = false;$wire.cancelModal()"/>--}}
{{--                <x-button wire:loading.remove type="submit" label="Upload" class="btn-primary" spinner="file" />--}}
{{--            </x-slot:actions>--}}
        </x-form>
    </x-modal>



</div>



@push('scripts')

{{--    <script>--}}
{{--        document.addEventListener('livewire:init',function (){--}}
{{--            Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {--}}
{{--                // Runs immediately before a commit's payload is sent to the server...--}}
{{--                Livewire.dispatch('uploading')--}}
{{--                /*dispatch livewire event*/--}}

{{--                respond(() => {--}}
{{--                    // Runs after a response is received but before it's processed...--}}
{{--                })--}}

{{--                succeed(({ snapshot, effect }) => {--}}
{{--                    // Runs after a successful response is received and processed--}}
{{--                    // with a new snapshot and list of effects...--}}
{{--                })--}}

{{--                fail(() => {--}}
{{--                    // Runs if some part of the request failed...--}}
{{--                })--}}
{{--            })--}}
{{--        })--}}
{{--    </script>--}}
@endpush
