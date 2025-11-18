<?php

namespace App\Services;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    protected $client;

    public function __construct()
    {
        try {
            // Path ke service account JSON
            $keyFilePath = storage_path('app/google/service-account.json');
            
            if (!file_exists($keyFilePath)) {
                Log::error('Google service account file not found at: ' . $keyFilePath);
                throw new \Exception('Service account file not found');
            }

            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keyFilePath);
            
            $this->client = new ImageAnnotatorClient();
            
        } catch (\Exception $e) {
            Log::error('Error initializing OCR Service: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract GMV dari screenshot
     */
    public function extractGMV($imagePath)
    {
        try {
            // Baca file dari storage
            $imageContent = Storage::get($imagePath);
            
            if (!$imageContent) {
                throw new \Exception('Failed to read image file');
            }

            Log::info('Processing OCR for image: ' . $imagePath);

            // Panggil Google Vision API
            $response = $this->client->textDetection($imageContent);
            $texts = $response->getTextAnnotations();

            if (!$texts || count($texts) === 0) {
                Log::warning('No text detected in image');
                return null;
            }

            // Text pertama adalah full text
            $fullText = $texts[0]->getDescription();
            
            Log::info('OCR Result: ' . $fullText);

            // Extract angka GMV
            $gmv = $this->parseGMV($fullText);

            if ($gmv) {
                Log::info('GMV extracted: ' . $gmv);
            } else {
                Log::warning('GMV not found in OCR result');
            }

            return $gmv;

        } catch (\Exception $e) {
            Log::error('OCR Error: ' . $e->getMessage());
            return null;
        } finally {
            if ($this->client) {
                $this->client->close();
            }
        }
    }

    /**
     * Parse GMV dari text OCR
     */
    private function parseGMV($text)
    {
        // Pattern untuk mendeteksi GMV
        // Format: GMV, Rp, angka dengan titik/koma
        $patterns = [
            '/GMV[:\s]*Rp[\s]*([0-9.,]+)/i',
            '/Rp[\s]*([0-9.,]+)[kKmMbB]?/i',
            '/([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)[kKmMbB]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Ambil angka saja
                $number = $matches[1];
                
                // Hapus titik/koma (format Indonesia)
                $number = str_replace(['.', ','], ['', '.'], $number);
                
                // Convert ke float
                $gmv = floatval($number);
                
                if ($gmv > 0) {
                    return $gmv;
                }
            }
        }

        return null;
    }
}