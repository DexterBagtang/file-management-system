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


new class extends Component {
    use Toast, WithPagination, WithFilePond;

    public FolderForm $folderForm;
    public FileForm $fileForm;

    public Folder $currentFolder;

    public string $search = '';
    public bool $addModal = false;
    public array $selected = [];
    public array $sortBy = ['column' => 'id', 'direction' => 'desc'];
    public array $breadcrumbs = [];

    public string $name;

    #[Validate(['file.*' => 'file|max:1024'])]
    public array $file = [];

    public bool $addFile = false;


    public function mount($id): void
    {
        $this->currentFolder = Folder::findOrFail($id);
        $this->buildBreadcrumbs();
    }


    public function mergedData()
    {
        // Fetch folders
        $folders = Folder::query()
            ->select('folders.*', 'users.name as owner', DB::raw("'folder' as type"))
            ->where('parent_id', $this->currentFolder->id ?? null)
            ->join('users', 'folders.user_id', '=', 'users.id')
            ->when($this->search, fn($q) => $q->where('folders.name', 'like', "%$this->search%"))
//            ->orderBy(...array_values($this->sortBy))
            ->get()
            ->toArray();

        // Fetch files
        $files = File::query()
            ->select('files.*', 'users.name as owner', DB::raw("'file' as type"))
            ->where('folder_id', $this->currentFolder->id ?? null)
            ->join('users', 'files.user_id', '=', 'users.id')
            ->when($this->search, fn($q) => $q->where('files.name', 'like', "%$this->search%"))
//            ->orderBy(...array_values($this->sortBy))
            ->get()
            ->toArray();

        // Merge folders and files
        $merged = array_merge($folders, $files);

        // Convert to collection for sorting and pagination
        $collection = collect($merged);

        // Sort the collection based on the sortBy property
        $collection = $collection->sortBy([
            [$this->sortBy['column'], $this->sortBy['direction']]
        ]);

        return $collection; // Adjust the pagination as needed
    }


    public function with()
    {
        return [
            'folderFiles' => $this->mergedData(),
            'headers' => $this->headers(),
        ];
    }


    public function headers()
    {
        return [
            ['key' => 'select','label' => '#','class'=>'w-1'],
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

    public function delete($id,$type)
    {
        if ($type == 'folder'){
            $file = Folder::findOrFail($id);
        }else{
            $file = File::findOrFail($id);
        }

        $file->delete();

        $this->warning("$type deleted", 'Good bye!', position: 'toast-bottom');

    }

    public function bulkDelete()
    {
//        dd($this->selected);
        foreach ($this->selected as $item) {
            $file= json_decode($item);
            $files[] = $file;
        }
        dd($files);

        $folders = Folder::query()->whereIn('id', $this->selected)->get();
        $folders->each->delete();

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

    public function updated($property): void
    {
        if (!is_array($property) && $property != "") {
            $this->resetPage();
        }
    }

//    public function selectAll()
//    {
//        $this->reset(['search', 'selected']);
////        $all = $this->mergedData()->pluck('id','type')->toArray();
//        $all = $this->mergedData()->map(function ($item) {
//            return json_encode(['id' => $item['id'], 'type' => $item['type']]);
//        })->toArray();
////        $folders = $this->folders()->pluck('id')->toArray();
//        $this->selected = $all;
//        $this->success('All folders selected');
//    }
    public function selectAll()
    {
        $this->reset(['search', 'selected']);
        $all = $this->mergedData()->map(function ($item) {
            return ['id' => $item['id'], 'type' => $item['type']];
        })->toArray();

        $this->selected = $all;
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
        if ($this->file) {
            foreach ($this->file as $item) {
                $itemName = $item->getClientOriginalName();
                $url = $item->storeAs($this->currentFolder->id, $itemName);
                File::create([
                    'name' => $itemName,
                    'user_id' => Auth::id(),
                    'folder_id' => $this->currentFolder->id,
                    'path' => "/storage/$url",
                ]);
            }
        }
    }

    public function cancelModal()
    {
        $this->reset('file');
    }

};
?>

<div>
    @dump(count($selected))
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
        <div x-text="$wire.selected.length"></div>
        <template x-if="$wire.selected.length > 0">
            <div class="flex justify-between">
                <x-button class="btn-xs" spinner>
                    <span x-text="$wire.selected.length"></span>
                    items selected
                </x-button>

                <div class="flex justify-around">
                    <x-button wire:click="selectAll" spinner
                              class="btn-primary btn-outline btn-xs">
                        Select All
                    </x-button>
                    <div class="divider m-0 divider-horizontal"></div>
                    <x-button wire:click="unselectAll" spinner
                              class="btn-error btn-outline btn-xs">
                        Unselect All
                    </x-button>
                </div>

            </div>

        </template>
        @if(count($folderFiles) != 0)
            <x-table :headers="$headers" :rows="$folderFiles" class="table-xs"
                     :sort-by="$sortBy"
                     wire:model="selected"
{{--                     selectable--}}
                     {{--                 with-pagination--}}

{{--                     @row-selection="console.log($event.detail.row.id,$event.detail.row.type)"--}}
{{--                     @row-all="console.log({{json_encode($folderFiles)}})"--}}
{{--                     @row-all="$wire.selectAll"--}}
{{--                     @row-selection="console.log($event.detail)"--}}
{{--                     selectable-key="['id','type']"--}}
                {{--                 link="/folders/{id}/show"--}}
            >

                @scope('cell_select',$folderFile)

                <input
                    type="checkbox"
                    class="checkbox checkbox-sm checkbox-primary"
                    wire:model="selected"
                    @click="$wire.selected = '{{json_encode($folderFile)}}'"
{{--                    value='{{ json_encode(['id' => $folderFile['id'], 'type' => $folderFile['type']]) }}'--}}
                />
                @endscope

                @scope('cell_name', $folderFile)
                @if($folderFile['type'] === 'folder')
                    <a href="/folders/{{ $folderFile['id'] }}/show">
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

                    <x-button icon="o-trash" wire:click="delete({{$folderFile['id']}},'{{$folderFile['type']}}')" wire:confirm="Are you sure?"
                              spinner
                              class="btn-ghost btn-sm text-red-500"/>
                </div>
                @endscope

            </x-table>

        @else
            <x-alert title="No files here." description="Click New button to add Folders or Upload Files"
                     class="justify-items-center text-center" icon="o-exclamation-triangle">
            </x-alert>
        @endif

        <x-slot:menu>
            <template x-if="$wire.selected.length > 0">
                <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner
                          label="Delete Selected" icon="o-trash" responsive class="btn-error"/>
            </template>
            {{--            <x-icon name="o-heart" class="cursor-pointer"/>--}}
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>

        </x-slot:menu>

    </x-card>

    {{--ADD MODAL--}}
    <x-modal wire:model="addModal" title="New Folder" box-class="w-80">
        <x-form wire:submit="save" no-separator>
            <x-input wire:model="name" id="folder-name" />
            <x-slot:actions>
                <x-button type="submit" label="Confirm" class="btn-primary" spinner="save"/>
                <x-button label="Cancel" @click="$wire.addModal = false"/>
            </x-slot:actions>
        </x-form>
    </x-modal>


    {{--ADD File MODAL--}}
    <x-modal wire:model="addFile" title="Upload File" box-class="w-2/3">
        <x-form wire:submit="saveFile" no-separator>

            <x-filepond::upload wire:model="file"
                                allow-reorder
                                item-insert-interval="0"
                                multiple/>

            @error('file.*') <span class="error">{{ $message }}</span> @enderror

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.addFile = false;$wire.cancelModal()"/>
                <x-button type="submit" label="Confirm" class="btn-primary" spinner="saveFile"/>
            </x-slot:actions>
        </x-form>
    </x-modal>

</div>

@push('scripts')

@endpush
