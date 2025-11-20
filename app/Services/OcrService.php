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
     * Extract GMV dari screenshot TikTok Shop
     */
    public function extractGMV($imagePath)
    {
        try {
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
            
            Log::info('OCR Full Text Result: ' . $fullText);

            // Extract GMV dengan multiple strategies
            $gmv = $this->parseGMVMultiStrategy($fullText);

            if ($gmv) {
                Log::info('GMV successfully extracted: Rp ' . number_format($gmv, 0, ',', '.'));
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
     * Parse GMV dengan Multiple Strategy
     */
    private function parseGMVMultiStrategy($text)
    {
        // STRATEGY 1: Deteksi TikTok Shop Pattern
        // Format: "Pendapatan\n286.9K" atau "Pendapatan 286.9K"
        $gmv = $this->parseTikTokShopFormat($text);
        if ($gmv !== null) {
            Log::info('Strategy 1 (TikTok Shop Format) matched: ' . $gmv);
            return $gmv;
        }

        // STRATEGY 2: Deteksi angka dengan K/M/B suffix
        // Format: "286.9K", "1.2M", "500K"
        $gmv = $this->parseNumberWithSuffix($text);
        if ($gmv !== null) {
            Log::info('Strategy 2 (Number with Suffix) matched: ' . $gmv);
            return $gmv;
        }

        // STRATEGY 3: Deteksi format Rupiah
        // Format: "Rp 286.900", "Rp286900", "286.900"
        $gmv = $this->parseRupiahFormat($text);
        if ($gmv !== null) {
            Log::info('Strategy 3 (Rupiah Format) matched: ' . $gmv);
            return $gmv;
        }

        // STRATEGY 4: Deteksi GMV keyword
        // Format: "GMV: 286900", "Total GMV 286.900"
        $gmv = $this->parseGMVKeyword($text);
        if ($gmv !== null) {
            Log::info('Strategy 4 (GMV Keyword) matched: ' . $gmv);
            return $gmv;
        }

        return null;
    }

    /**
     * STRATEGY 1: TikTok Shop Format
     * Deteksi: "Pendapatan" diikuti angka dengan K/M/B
     */
    private function parseTikTokShopFormat($text)
    {
        // Pattern: Pendapatan + angka dengan optional K/M/B
        $patterns = [
            '/Pendapatan[:\s\n]*([0-9]+\.?[0-9]*)\s*([KMB])/i',
            '/Pendapatan[:\s\n]*([0-9]+[.,]?[0-9]*)\s*([KMB])/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = str_replace(',', '.', $matches[1]);
                $suffix = strtoupper($matches[2]);
                
                return $this->convertSuffixToNumber($number, $suffix);
            }
        }

        return null;
    }

    /**
     * STRATEGY 2: Number with K/M/B Suffix
     * Deteksi: 286.9K, 1.2M, 500K (tanpa keyword)
     */
    private function parseNumberWithSuffix($text)
    {
        // Pattern: Angka diikuti K/M/B
        $pattern = '/\b([0-9]+\.?[0-9]*)\s*([KMB])\b/i';
        
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            return null;
        }

        // Ambil angka terbesar (asumsi itu GMV)
        $maxValue = 0;
        foreach ($matches as $match) {
            $number = str_replace(',', '.', $match[1]);
            $suffix = strtoupper($match[2]);
            $value = $this->convertSuffixToNumber($number, $suffix);
            
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        return $maxValue > 0 ? $maxValue : null;
    }

    /**
     * STRATEGY 3: Rupiah Format
     * Deteksi: Rp 286.900, Rp286900
     */
    private function parseRupiahFormat($text)
    {
        $patterns = [
            '/Rp[\s]*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]+)?)/i',
            '/([0-9]{1,3}(?:[.,][0-9]{3}){2,})/i', // Format: 286.900.000
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = $matches[1];
                // Hapus titik/koma (format Indonesia)
                $number = str_replace(['.', ','], ['', '.'], $number);
                $gmv = floatval($number);
                
                if ($gmv > 0) {
                    return $gmv;
                }
            }
        }

        return null;
    }

    /**
     * STRATEGY 4: GMV Keyword
     * Deteksi: GMV: 286900, Total GMV 286.900
     */
    private function parseGMVKeyword($text)
    {
        $patterns = [
            '/(?:GMV|Total\s*GMV|Gross\s*Merchandise\s*Value)[:\s]*Rp?[\s]*([0-9.,]+)\s*([KMB])?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = str_replace(['.', ','], ['', '.'], $matches[1]);
                $gmv = floatval($number);
                
                // Jika ada suffix K/M/B
                if (isset($matches[2]) && !empty($matches[2])) {
                    $gmv = $this->convertSuffixToNumber($gmv, strtoupper($matches[2]));
                }
                
                if ($gmv > 0) {
                    return $gmv;
                }
            }
        }

        return null;
    }

    /**
     * Convert K/M/B suffix ke angka penuh
     */
    private function convertSuffixToNumber($number, $suffix)
    {
        $value = floatval($number);
        
        switch ($suffix) {
            case 'K':
                return $value * 1000;
            case 'M':
                return $value * 1000000;
            case 'B':
                return $value * 1000000000;
            default:
                return $value;
        }
    }
}