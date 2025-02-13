<?php

use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf as PdfToImage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Livewire\Volt\Component;
use App\Services\OcrService;
use Spatie\PdfToText\Pdf;



new class extends Component {
    use \Livewire\WithFileUploads;

    public $ocrfile;
    public $result;


    public function save()
    {
        $name = $this->ocrfile->getClientOriginalName();
        $url = $this->ocrfile->storeAs($name);

        $filepath = Storage::path($url);
        $pdf = new \Spatie\PdfToText\Pdf('C:\laragon\bin\git\mingw64\bin\pdftotext.exe');

        try {
            $text = $pdf->setPdf($filepath)->text();

            if (empty($text)) {
                dd('empty');
                // Handle the case where no text was extracted by converting PDF to image
            } else {
                // Proceed with the extracted text
                dd($text);
            }
        } catch (\Spatie\PdfToText\Exceptions\CouldNotExtractText $e) {
            // Handle the exception if needed
            dd('Could not extract text: ' . $e->getMessage());
        }


//
//        $pdf = new PdfToImage($filepath);
//
//        $jpg = $pdf->save(storage_path('/converted_images/'.$name));




//
//        $pythonScript = base_path('scripts/easyocr_script.py');
////
//        $process = new Process(['python', $pythonScript, $filepath]);
//
////
//        $process->run();
////
//        if (!$process->isSuccessful()) {
//            throw new ProcessFailedException($process);
//        }
////
//        $text = $process->getOutput();
//
//        dd($text);


//
//
//        $ocrText = \OCR::scan('test.jpg');

//        $imagepath = public_path('test.jpg');
        $tesseract = new TesseractOCR($jpg);

        $text = $tesseract->run();

        dump($text);

//        $this->result = $text;
    }
}; ?>

<div>
    <x-header>
        <x-slot:middle class="!justify-center">
            <x-input icon="o-magnifying-glass" placeholder="Search..." class="!lg:w-full" clearable/>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-funnel"/>
            <x-button icon="o-plus" class="btn-primary"/>
        </x-slot:actions>
    </x-header>

    <x-form wire:submit="save">
        <x-file wire:model="ocrfile" label="Receipt" accept="multipart/form-data"/>
        <x-button label="OCR submit" type="submit"/>

    </x-form>
    <x-card>
        {{$result}}
    </x-card>
</div>
