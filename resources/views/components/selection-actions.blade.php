<div>
    <div x-data="{
                selected: @entangle('selected'),
                isSelected:false,
                addAllItems() {
                    this.selected = [];
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
                <div class="join">
                    <x-button @click="$wire.selected = []" class="btn-xs join-item" icon="c-x-mark" />
                    <x-button class="btn-xs join-item" spinner>
                        <span x-text="$wire.selected.length"></span>
                        items selected
                    </x-button>
                </div>


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

</div>
