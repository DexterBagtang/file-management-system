<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\PdfToText\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

use Smalot\PdfParser\Parser as PdfParser;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;

class ProcessFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $fileId;
    public $filePath;
    public $extension;
    public $fileIndex;
    public $fileCount;

    public function __construct($fileId, $filePath, $extension, $fileIndex, $fileCount)
    {
        $this->fileId = $fileId;
        $this->filePath = $filePath;
        $this->extension = $extension;
        $this->fileIndex = $fileIndex;
        $this->fileCount = $fileCount;
    }

//    public function handle()
//    {
//        $filepath = Storage::disk('public')->path($this->filePath);
//
//        $fileContent = '';
//
//        if ($this->extension === 'pdf') {
//            $pdfToText = new Pdf('C:\laragon\bin\git\mingw64\bin\pdftotext.exe');
//            $text = $pdfToText->setPdf($filepath)->text();
//            $fileContent = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
//
//            if (empty($fileContent)) {
//                $pdf = new \Spatie\PdfToImage\Pdf($filepath);
//                $outputDirectory = dirname($filepath);
//                $baseFileName = pathinfo($this->filePath, PATHINFO_FILENAME);
//
//                $pageCount = $pdf->pageCount();
//                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
//                    $outputPath = $outputDirectory . "/{$baseFileName}_page-{$pageNumber}.jpg";
//                    $pdf->selectPage($pageNumber)->save($outputPath);
//
//                    $tesseract = new TesseractOCR($outputPath);
//                    $fileContent .= $tesseract->run() . "\n";
//
//                    unlink($outputPath);
//                }
//            }
//        } else {
//            try {
//                $tesseract = new TesseractOCR($filepath);
//                $fileContent = $tesseract->run();
//            } catch (\Exception $e) {
//                // Handle OCR failure
//            }
//        }
//
//        // Update the file entry with the processed contents
//        $file = File::find($this->fileId);
//        $file->contents = $fileContent;
//        $file->update();
//    }

    public function handle()
    {
        $filepath = Storage::disk('public')->path($this->filePath);

        $fileContent = '';
        $metadata = [];

        // Extract metadata
        if (strtolower($this->extension) === 'pdf') {
            $metadata = $this->extractPdfMetadata($filepath);
            $fileContent = $this->extractPdfContent($filepath);
        } elseif (in_array($this->extension, ['jpg', 'jpeg', 'png'])) {
            $metadata = $this->extractImageMetadata($filepath);
            $fileContent = $this->extractImageContent($filepath);
        }

        // Update the file entry with the processed contents and metadata
        $file = File::find($this->fileId);
        $file->contents = $fileContent;
        $file->metadata = $metadata;
        $file->update();
    }

    private function extractPdfContent($filepath) : string
    {
        $pdfToText = new Pdf('C:\laragon\bin\git\mingw64\bin\pdftotext.exe');
        $text = $pdfToText->setPdf($filepath)->text();
        $fileContent = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        if (empty($fileContent)) {
            $pdf = new \Spatie\PdfToImage\Pdf($filepath);
            $outputDirectory = dirname($filepath);
            $baseFileName = pathinfo($this->filePath, PATHINFO_FILENAME);

            $pageCount = $pdf->pageCount();

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $outputPath = $outputDirectory . "/{$baseFileName}_page-{$pageNumber}.jpg";
                $pdf->selectPage($pageNumber)->save($outputPath);

                $tesseract = new TesseractOCR($outputPath);
                $fileContent .= $tesseract->run() . "\n";

                unlink($outputPath);
            }
            return $fileContent;
        }

        return htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
    }

    private function extractPdfMetadata($filepath)
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filepath);
        $details = $pdf->getDetails();

        return $details;
    }

    private function extractImageContent($filepath)
    {
        try {
            $tesseract = new TesseractOCR($filepath);
            return $tesseract->run();
        } catch (\Exception $e) {
            // Handle OCR failure
            return '';
        }
    }


    private function extractImageMetadata($filepath)
    {
        // Check if the file is a JPEG
        if (exif_imagetype($filepath) === IMAGETYPE_JPEG) {
            $data = @exif_read_data($filepath);
            if ($data === false) {
                return json_encode(['error' => 'No EXIF data found']);
            }
            $metadata = [
                'FileName' => $data['FileName'] ?? 'Unknown',
                'FileDateTime' => isset($data['FileDateTime']) ? date('Y-m-d H:i:s', $data['FileDateTime']) : 'Unknown',
                'FileSize' => $data['FileSize'] ?? 'Unknown',
                'FileType' => $data['FileType'] ?? 'Unknown',
                'MimeType' => $data['MimeType'] ?? 'Unknown',
                'SectionsFound' => $data['SectionsFound'] ?? 'Unknown',
                'Width' => $data['COMPUTED']['Width'] ?? 'Unknown',
                'Height' => $data['COMPUTED']['Height'] ?? 'Unknown',
                'IsColor' => $data['COMPUTED']['IsColor'] ?? 'Unknown',
                'Software' => $data['Software'] ?? 'Unknown',
                'Exif_IFD_Pointer' => $data['Exif_IFD_Pointer'] ?? 'Unknown',
                'GPS_IFD_Pointer' => $data['GPS_IFD_Pointer'] ?? 'Unknown',
            ];
        } elseif (exif_imagetype($filepath) === IMAGETYPE_PNG) {
            // Extract basic PNG metadata
            $size = getimagesize($filepath);
            $metadata = [
                'FileName' => basename($filepath),
                'Width' => $size[0] ?? 'Unknown',
                'Height' => $size[1] ?? 'Unknown',
                'MimeType' => $size['mime'] ?? 'image/png',
                'FileSize' => filesize($filepath) ?? 'Unknown',
                'ColorType' => $size['channels'] ?? 'Unknown',
            ];
        } else {
            return json_encode(['error' => 'Unsupported image format']);
        }

        return json_encode($metadata);
    }

}
