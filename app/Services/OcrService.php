<?php

namespace App\Services;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;

class OcrService
{
    protected $client;

    public function __construct()
    {
        $this->client = new ImageAnnotatorClient([
            'credentials' => storage_path('app/google/service-account.json')
        ]);
    }

    /**
     * Extract GMV dari screenshot menggunakan Google Vision OCR
     */
    public function extractGmv(string $imagePath): ?float
    {
        try {
            $image = file_get_contents($imagePath);
            $imageObject = new Image();
            $imageObject->setContent($image);

            $response = $this->client->textDetection($imageObject);
            $texts = $response->getTextAnnotations();

            if (count($texts) > 0) {
                $fullText = $texts[0]->getDescription();
                
                // Cari pola GMV (misal: "GMV: Rp 1.234.567" atau "Total: Rp1,234,567")
                preg_match('/(?:GMV|Total)[\s:]*Rp[\s]*([\d\.,]+)/i', $fullText, $matches);
                
                if (isset($matches[1])) {
                    // Hapus separator dan convert ke float
                    $gmvString = str_replace(['.', ','], '', $matches[1]);
                    return (float) $gmvString;
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('OCR Error: ' . $e->getMessage());
            return null;
        }
    }

    public function __destruct()
    {
        $this->client->close();
    }
}