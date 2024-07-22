<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class Spotlight
{
    public function search(Request $request)
    {
        // Example of security concern
        // Guests can not search
        if(! auth()->user()) {
            return [];
        }

        return collect()
            ->merge($this->actions($request->search))
            ->merge($this->users($request->search));
    }

    // Database search
    public function users(string $search = '')
    {
        return User::query()
            ->where('name', 'like', "%$search%")
            ->take(5)
            ->get()
            ->map(function (User $user) {
                return [
                    'avatar' => $user->profile_picture,
                    'name' => $user->name,
                    'description' => $user->email,
                    'link' => "/users/{$user->id}"
                ];
            });
    }

    // Static search, but it could come from a database
    public function actions(string $search = '')
    {
        $icon = Blade::render("<x-icon name='o-bolt' class='w-11 h-11 p-2 bg-yellow-50 rounded-full' />");

        return collect([
            [
                'name' => 'Create user',
                'description' => 'Create a new user',
                'icon' => $icon,
                'link' => '/users/create'
            ],
            [
                // More ...
            ]
        ])->filter(fn(array $item) => str($item['name'] . $item['description'])->contains($search, true));
    }
}
