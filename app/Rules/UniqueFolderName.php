<?php

namespace App\Rules;

use App\Models\Folder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueFolderName implements ValidationRule
{
    protected $parentId;

    public function __construct($parentId)
    {
        $this->parentId = $parentId;
    }

    public function passes($attribute, $value)
    {
        return !Folder::where('name', $value)->where('parent_id', $this->parentId)->exists();
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //
    }
}
