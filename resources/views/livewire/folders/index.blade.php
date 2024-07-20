<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule as ValidationRule;

// Alias the Rule class


new class extends Component {
    use \Mary\Traits\Toast;
    use \Livewire\WithPagination;

    public string $search = '';
    public bool $drawer = false;
    public bool $editModal = false;
    public int $folderId;
    public array $sortBy = ['column' => 'id', 'direction' => 'desc'];
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

    public function save()
    {
        $this->validate([
            'name' => [
                'required',
                ValidationRule::unique('folders')->where(function ($query) {
                    return $query->where('parent_id', null);
                })]
        ],
            [
                'name.unique' => 'A folder with this name already exists in this location. Please choose a different name.',
            ]);

        Folder::create([
            'name' => $this->name,
            'user_id' => auth()->user()->id,
        ]);

        $this->reset();

        $this->drawer = false;

        $this->success('Folder created successfully.');

    }

    public function update(Folder $folder)
    {
        $data = $this->validate();

        $folder->update($data);

        $this->reset();

        $this->editModal = false;

        $this->success('Folder updated successfully.');

    }


    public function headers(): array
    {
        return [
//            ['key' => 'avatar', 'label' => '', 'class' => 'w-1'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
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
            ->join('users', 'folders.user_id', '=', 'users.id')
            ->when($this->search, fn(Builder $q) => $q->where('folders.name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);
    }

    public function bulkDelete(){
        $folders = Folder::query()->whereIn('id',$this->selected)->get();
        $folders->each->delete();

        $this->reset('selected');

        $this->warning("Selected items deleted", 'Good bye!', position: 'toast-bottom');
    }

    public function with(): array
    {
        return [
            'folders' => $this->folders(),
            'headers' => $this->headers(),
        ];
    }
    // Reset pagination when any component property changes
    public function updated($property): void
    {
        if (! is_array($property) && $property != "") {
            $this->resetPage();
        }
//        $this->filter_count = $this->calculateFilterCount();
    }

}; ?>

<div>
    <!--HEADER-->
    <x-header title="Home" separator progress-indicator>
        <x-slot:middle>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>
        </x-slot:middle>
        <x-slot:actions>
                <x-button label="New Folder" @click="$wire.drawer = true;$wire.name=''" class="btn-primary" icon="s-plus"/>
{{--            --}}{{-- Custom trigger  --}}
{{--            <x-dropdown>--}}
{{--                <x-slot:trigger>--}}
{{--                    <x-button label="New" icon="s-plus" class="btn-primary"/>--}}
{{--                </x-slot:trigger>--}}

{{--                <x-menu-item title="Folder" @click="$wire.drawer = true;$wire.name=''" icon="o-folder-plus"/>--}}
{{--                <x-menu-separator/>--}}
{{--                <x-menu-item title="File" icon="o-document-arrow-up"/>--}}
{{--            </x-dropdown>--}}
        </x-slot:actions>

    </x-header>

    <!-- TABLE  -->
    <x-card title="Folders">
        @if($folders->total() != 0)
            <x-table :headers="$headers" :rows="$folders" :sort-by="$sortBy"
                     wire:model="selected"
                     selectable
                     with-pagination
                     @row-selection="console.log($event.detail)"
                     link="/folders/{id}/show"
            >

                @scope('cell_name',$folder)
                {{'📂'.$folder->name}}
                @endscope


                @scope('actions', $folder)
                <div class="flex">
                    <x-button icon="o-pencil"
                              @click="$wire.editModal = true; $wire.name = '{{$folder['name']}}';
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
                <x-button wire:click="bulkDelete" wire:confirm="Are you sure to deleted the selected items?" spinner
                          label="Delete Selected" icon="o-trash" class="btn-error"/>
            </template>
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"/>
        </x-slot:menu>
    </x-card>

    {{--ADD MODAL--}}
    <x-modal wire:model="drawer" title="New Folder" box-class="w-80">
        <x-form wire:submit="save" no-separator>
            <x-input wire:model="name"/>

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.drawer = false"/>
                <x-button type="submit" label="Confirm" class="btn-primary" spinner="save"/>
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{--EDIT MODAL--}}
    <x-modal wire:model="editModal" title="Edit Folder" box-class="w-80">
        <x-form wire:submit="update($wire.folderId)" no-separator>
            <x-input wire:model="name"/>

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.editModal = false"/>
                <x-button type="submit" label="Confirm" class="btn-primary" spinner="save"/>
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
