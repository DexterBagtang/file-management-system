<!-- resources/views/components/folder-children-menu.blade.php -->

{{--<ul class="pl-4">--}}
{{--    @foreach ($children as $child)--}}
{{--        <li>--}}
{{--            @if (isset($child->children_tree) && count($child->children_tree) > 0)--}}
{{--                <details >--}}
{{--                    <summary>--}}
{{--                        📂--}}
{{--                        {{ $child->name }}--}}
{{--                    </summary>--}}
{{--                    <ul>--}}
{{--                        @include('components.folder-children-menu', ['children' => $child->children_tree])--}}
{{--                    </ul>--}}
{{--                </details>--}}
{{--            @else--}}
{{--                <a>--}}
{{--                    📂--}}
{{--                    {{ $child->name }}--}}
{{--                </a>--}}
{{--            @endif--}}
{{--        </li>--}}
{{--    @endforeach--}}
{{--</ul>--}}

<!-- resources/views/components/folder-children.original.blade.php -->


