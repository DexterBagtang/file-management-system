<div>
        @foreach ($children as $child)
            <li>
                @if (isset($child->children_tree) && count($child->children_tree) > 0)
                    <details >
                        <summary
                            @click="$wire.selectedFolder = {{ $child->id }};folderName = '{{$child->name}}';"
                            :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $child->id }} }"
                        >
                            📂
                            {{ $child->name }}
                        </summary>
                        <ul>
                            <x-folder-children :children="$child->children_tree" />
                        </ul>
                    </details>
                @else
                    <a
                        @click="$wire.selectedFolder = {{ $child->id }};folderName = '{{$child->name}}';"
                        :class="{ 'bg-blue-500 text-white': $wire.selectedFolder === {{ $child->id }} }"
                    >
                        📂 {{ $child->name }}
                    </a>
                @endif

            </li>
        @endforeach
</div>
