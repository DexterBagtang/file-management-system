<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OcrService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.ocr.space/parse/image';

    public function __construct()
    {
//        $this->apiKey = env('OCR_SPACE_API_KEY'); // Add your API key to .env
        $this->apiKey = 'K87257480388957';
    }

    public function recognizeText($imagePath)
    {
        $response = Http::attach(
            'file', file_get_contents($imagePath), 'image.jpg'
        )->post($this->apiUrl, [
            'apikey' => $this->apiKey,
            'language' => 'eng', // Specify language if needed
        ]);

        return $response->json();
    }
}
