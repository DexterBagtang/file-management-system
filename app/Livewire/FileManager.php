<?php

namespace App\Livewire;

use App\Jobs\ProcessFile;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;
use Spatie\LivewireFilepond\WithFilePond;
trait FileManager
{
//    use WithFilePond,WithFileUploads;
    public $fileIndex = 0;
    public $fileCount = 0;
    public $progress = 0;
    public $processingFiles = false;



    public function saveFile()
    {
        $rules = [];
        $messages = [];

        foreach ($this->file as $index => $file) {
            $filename = $file->getClientOriginalName();
            $rules["file.$index"] = 'mimes:pdf,jpeg,jpg,png,bmp,gif,svg,webp|max:10240';
            $messages["file.$index.mimes"] = "The file '$filename' must be a file of type: pdf, jpeg, jpg, png, bmp, gif, svg, webp.";
            $messages["file.$index.max"] = "The file '$filename' may not be greater than 10MB.";
        }

        $this->validate($rules, $messages);
//        $this->processingFiles = true;
        $this->fileIndex = 0;
        $this->fileCount = count($this->file);

        $this->file = array_values($this->file);

        $this->processNextFile();
    }

    #[On('processNextFile')]
    public function processNextFile()
    {
        if ($this->fileIndex >= $this->fileCount) {
            $this->reset(['file', 'addFile']);
            $this->redirect(url()->previous(), true);
            $this->success('Files added successfully', 'yeah');
            return;
        }

        $item = $this->file[$this->fileIndex];
        $originalName = $item->getClientOriginalName();
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $item->getClientOriginalExtension();
        $newName = $originalName;

        $this->uploadStatusMessage = "Processing File:<br> $originalName";

        // Check if file name exists and rename if necessary
        $existingFileNames = File::where('folder_id', $this->currentFolder->id)
            ->pluck('name')
            ->toArray();

        $counter = 1;
        while (in_array($newName, $existingFileNames)) {
            $newName = $name . " ($counter)." . strtolower($extension);
            $counter++;
        }

        // Store the file in the public disk
        $url = $item->storeAs($this->currentFolder->id, $newName, 'public');

        $file = File::create([
            'name' => $newName,
            'user_id' => Auth::id(),
            'folder_id' => $this->currentFolder->id,
            'contents' => '',
            'path' => $url,
        ]);

        ProcessFile::dispatch($file->id, $url, $extension, $this->fileIndex, $this->fileCount);

        $this->progress = (($this->fileIndex + 1) / $this->fileCount) * 100;

        $this->fileIndex++;
        $this->processNextFile();
//        $this->dispatch('processNextFile')->self();
    }

    public function updateFilename(File $file)
    {
        $data = $this->validate([
            'name' => 'required'
        ]);

        $currentPath = $file->path;
        $currentExtension = pathinfo($currentPath, PATHINFO_EXTENSION);
        $baseName = $data['name'];
        $newName = $baseName . '.' . $currentExtension;
        $newPath = dirname($currentPath) . '/' . $newName;

        // If the new name is the same as the current name, do nothing
        if ($newName === $file->name) {
            $this->reset(['name', 'renameFile']);
            $this->success('File name is unchanged.');
            return;
        }

        $existingFileNames = File::where('folder_id', $this->currentFolder->id)
            ->pluck('name')
            ->toArray();

        $counter = 1;
        while (in_array($newName, $existingFileNames)) {
            $newName = $baseName . " ($counter)." . $currentExtension;
            $newPath = dirname($currentPath) . '/' . $newName;
            $counter++;
        }

        if (Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->move($currentPath, $newPath);

            $file->update([
                'name' => $newName,
                'path' => $newPath
            ]);

            $this->reset(['name', 'renameFile']);
            $this->success('File name and path updated successfully.');
        } else {
            $this->addError('name', 'The current file does not exist.');
        }
    }
}
