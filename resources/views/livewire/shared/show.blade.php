<?php

use App\Livewire\FolderManager;
use App\Models\Folder;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SharedItem;
use Carbon\Carbon;

new class extends Component {

    use FolderManager;

    public $currentFolder;
    public string $search = '';
    public array $selected = [];
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public string $selectedTab = 'all';


    public function mount($id): void
    {
        // Attempt to find the folder directly shared with the user
        $this->currentFolder = Folder::where('id', $id)
            ->whereHas('shares', function ($query) {
                $query->where('shared_with_id', Auth::id());
            })->first();

        // If the folder is not directly shared, check if any of its parents are shared
        if (!$this->currentFolder && $this->areParentsShared($id)) {
            $this->currentFolder = Folder::find($id);
        }

        if (!$this->currentFolder){
            abort(403,'Unauthorized User');
        }

        $this->buildBreadcrumbs(checkShared: true);
    }



    public function mergedData()
    {
        $folders = [];
        $files = [];

        if ($this->selectedTab == 'all' || $this->selectedTab == 'folders') {
            // Fetch folders
            $folders = Folder::query()
                ->select('folders.*', 'users.name as owner', DB::raw("'folder' as type"))
                ->withCount(['files', 'children'])
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
        return $collection->sortBy([
            [$this->sortBy['column'], $this->sortBy['direction']]
        ]);
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
            ['key' => 'created_at', 'label' => 'Date Created'],
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
    <div class="breadcrumbs text-xs">
        <ul>
            <x-menu-item title="Shared with me" icon="o-home" link="/shared"/>
            {{--            <x-menu-item :title="$currentFolder->name" icon="o-folder"/>--}}
            @foreach($breadcrumbs as $breadcrumb)
                <x-menu-item :title="$breadcrumb->name" icon="o-folder" link="/shared/{{{$breadcrumb->id}}}/show"/>
            @endforeach
        </ul>
    </div>

    <x-card :title="$currentFolder->name">
        <x-table-tabs :selectedTab="$selectedTab" />
        <x-hr />
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
                    <a wire:navigate href="/shared/{{ $folderFile['id'] }}/show">
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


</div>
