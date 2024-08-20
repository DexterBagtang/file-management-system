<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use App\Models\Country;
use App\Models\Language;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required')]
    public string $name = '';

    #[Rule('required|email')]
    public string $email = '';

    // Optional
    #[Rule('sometimes')]
    public ?int $country_id = null;

    #[Rule('nullable|image|max:2048')]
    public $photo;

    // Selected languages
    #[Rule('required')]
    public array $my_languages = [];

    #[Rule('sometimes')]
    public ?string $bio = null;

    public function with(): array
    {
        return [
            'countries' => Country::all(),
            'languages' => Language::all(), // Available Languages

        ];
    }

    public function mount(): void
    {
        //
    }

    public function save(): void
    {
        // Validate
        $data = $this->validate();

        // Create user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'country_id' => $data['country_id'],
            'bio' => $data['bio'],
        ]);

        // Sync languages
        $user->languages()->sync($this->my_languages);

        // Upload file and save the avatar `url` on User model
        if ($this->photo) {
            $url = $this->photo->store('users', 'public');
            $user->update(['avatar' => "/storage/$url"]);
        }

        // Toast and redirect
        $this->success('User created successfully.', redirectTo: '/users');
    }

}; ?>
<div>
    <x-header title="Create New User" separator/>
    <div>
        <x-form wire:submit="save">
            {{-- Basic section --}}
            <div class="lg:grid grid-cols-5">
                <div class="col-span-2">
                    <x-header title="Basic" subtitle="Basic info from user" size="text-2xl"/>
                </div>
                <div class="col-span-3 grid gap-3">
                    <x-file label="Avatar" wire:model="photo" accept="image/png, image/jpeg">
                        <img src="/empty-user.jpg" class="h-40 rounded-lg"/>
                    </x-file>
                    <x-input label="Name" wire:model="name"/>
                    <x-input label="Email" wire:model="email"/>
                    <x-select label="Country" wire:model="country_id" :options="$countries" placeholder="---"/>
                </div>
            </div>

            {{-- Details section --}}
            <hr class="my-5"/>

            <div class="lg:grid grid-cols-5">
                <div class="col-span-2">
                    <x-header title="Details" subtitle="More about the user" size="text-2xl"/>
                </div>
                <div class="col-span-3 grid gap-3">
                    <x-choices-offline label="My languages" wire:model="my_languages" :options="$languages" searchable/>
{{--                    <x-editor wire:model="bio" label="Bio" hint="The great biography"/>--}}
                    <x-textarea wire:model="bio" label="Bio" hint="The great biography"/>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancel" link="/users"/>
                <x-button label="Save" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary"/>
            </x-slot:actions>

        </x-form>
    </div>
    <div>
        {{-- Get a nice picture from `StorySet` web site --}}
    </div>
</div>

