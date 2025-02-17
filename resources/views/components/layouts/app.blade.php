<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"></script>


    {{--    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />--}}
    {{--    <script src="https://cdn.tiny.cloud/1/hig1x5mbm0n2pf4r7aoepels1lrh2o3n7em35rshwsvb7jee/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>--}}

    @filepondScripts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200" x-data>

{{-- The navbar with `sticky` and `full-width` --}}
<x-nav sticky full-width>

    <x-slot:brand>

        {{-- Drawer toggle for "main-drawer" --}}
        <label for="main-drawer" class="lg:hidden mr-3">
            <x-icon name="o-bars-3" class="cursor-pointer"/>
        </label>

        {{-- Brand --}}
        {{--        <x-app-brand class="" label="File Management System" />--}}


        <div class="hidden md:flex items-center space-x-3 text-2xl font-medium
         tracking-tighter bg-clip-text text-transparent bg-gradient-to-r
        from-yellow-400 via-indigo-600 to-blue-600">
            <a href="/" class="flex align-middle space-x-1">
                <x-icon name="s-folder-open" class="w-8 h-8 text-yellow-400"/>
                <span>File Management System</span>
            </a>
        </div>


    </x-slot:brand>


    {{-- Right side actions --}}
    <x-slot:actions>
        <nav class="w-1/2">
            {{--             Notice `@click.stop` --}}
            <x-button label="Search Folders or Files" icon="o-magnifying-glass" class="btn-outline btn-block"
                      @click.stop="$dispatch('mary-search-open')" responsive/>

        </nav>
        <x-button label="Messages" icon="o-envelope" link="###" class="btn-ghost btn-sm" responsive/>
        <x-button label="Notifications" icon="o-bell" link="###" class="btn-ghost btn-sm" responsive/>
        <x-theme-toggle class="btn btn-circle"/>
    </x-slot:actions>

</x-nav>

{{-- MAIN --}}
<x-main full-width>
    {{-- SIDEBAR --}}
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

        {{-- BRAND --}}


        {{-- MENU --}}
        <x-menu activate-by-route>

            {{-- User --}}
            @if($user = auth()->user())
                {{--                    <x-menu-separator />--}}

                <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover
                             class="-mx-2 !-my-2 rounded">
                    <x-slot:actions>
                        <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff"
                                  no-wire-navigate link="/logout"/>
                    </x-slot:actions>
                </x-list-item>

                {{--                    <x-menu-separator />--}}
            @endif

            {{--                <x-menu-item title="Home" icon="o-sparkles" link="/" />--}}
            <x-menu-item title="Home" icon="o-home" link="/"/>
            <x-menu-item title="Shared" icon="o-inbox-stack" link="/shared"/>
            <x-menu-item title="Users" icon="o-users" link="/users"/>
            <x-menu-item title="Trash" icon="o-trash" link="/trash"/>

            <x-menu-sub title="Settings" icon="o-cog-6-tooth">
                <x-menu-item title="Wifi" icon="o-wifi" link="####"/>
                <x-menu-item title="Archives" icon="o-archive-box" link="####"/>
            </x-menu-sub>

        </x-menu>
    </x-slot:sidebar>


    {{-- The `$slot` goes here --}}
    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>

{{--  TOAST area --}}
<x-toast/>

{{-- Spotlight --}}
<x-spotlight
    search-text="Find your files or folders..."
>
    <div
        x-data="{ query: { withFolders: true, withFiles: true } }"
        x-init="$watch('query', value => $dispatch('mary-search', new URLSearchParams(value).toString()))"
        class="flex gap-8 p-3"
    >
        {{--        <x-checkbox type="radio" label="All" x-model="query.withAll" />--}}
        <x-checkbox type="radio" label="Folders" x-model="query.withFolders"/>
        <x-checkbox type="radio" label="Files" x-model="query.withFiles"/>

    </div>

</x-spotlight>


{{-- Theme toggle --}}
<x-theme-toggle class="hidden"/>

@stack('scripts')
</body>
</html>
