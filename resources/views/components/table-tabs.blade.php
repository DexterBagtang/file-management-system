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
