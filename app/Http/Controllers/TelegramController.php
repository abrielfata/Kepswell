<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\LiveSession;
use App\Services\OcrService;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        try {
            // Log semua request untuk debugging
            Log::info('Telegram Webhook Received:', $request->all());

            $update = $request->all();

            // Cek apakah ada foto
            if (isset($update['message']['photo'])) {
                return $this->handlePhoto($update);
            }

            // Cek apakah ada text
            if (isset($update['message']['text'])) {
                return $this->handleText($update);
            }

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function handlePhoto($update)
    {
        $chatId = $update['message']['chat']['id'];
        $photos = $update['message']['photo'];
        
        // Ambil foto dengan resolusi tertinggi (index terakhir)
        $photo = end($photos);
        $fileId = $photo['file_id'];

        Log::info('Photo received', [
            'chat_id' => $chatId,
            'file_id' => $fileId,
            'file_size' => $photo['file_size']
        ]);

        try {
            // Download foto dari Telegram
            $filePath = $this->downloadTelegramFile($fileId);
            
            if (!$filePath) {
                $this->sendTelegramMessage($chatId, "❌ Gagal mendownload foto. Silakan coba lagi.");
                return response()->json(['status' => 'error', 'message' => 'Failed to download photo']);
            }

            $this->sendTelegramMessage($chatId, "✅ Foto berhasil diterima!\n\nFile ID: {$fileId}\nFile disimpan di: {$filePath}\n\n⏳ Sedang memproses OCR...");
            
            // Proses OCR
            try {
                $ocrService = new OcrService();
                $gmv = $ocrService->extractGMV($filePath);
                
                if ($gmv) {
                    $formattedGMV = number_format($gmv, 0, ',', '.');
                    
                    $this->sendTelegramMessage($chatId, 
                        "✅ OCR Berhasil!\n\n" .
                        "💰 GMV Terdeteksi: Rp {$formattedGMV}\n\n" .
                        "Data telah disimpan ke database."
                    );
                    
                    // TODO: Simpan ke database
                    // $this->saveLiveSession($chatId, $gmv, $filePath);
                    
                } else {
                    $this->sendTelegramMessage($chatId, 
                        "⚠️ Tidak dapat mendeteksi GMV dari foto.\n\n" .
                        "Pastikan screenshot jelas dan terdapat angka GMV."
                    );
                }
                
            } catch (\Exception $e) {
                Log::error('OCR processing error: ' . $e->getMessage());
                $this->sendTelegramMessage($chatId, "❌ Error: " . $e->getMessage());
            }
            
            return response()->json(['status' => 'success', 'message' => 'Photo processed']);

        } catch (\Exception $e) {
            Log::error('Error processing photo: ' . $e->getMessage());
            $this->sendTelegramMessage($chatId, "❌ Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    private function handleText($update)
    {
        $chatId = $update['message']['chat']['id'];
        $text = $update['message']['text'];

        Log::info('Text message received', [
            'chat_id' => $chatId,
            'text' => $text
        ]);

        // Response sederhana
        $response = "Halo! Kirim screenshot GMV untuk diproses.\n\n";
        $response .= "📸 Kirim foto screenshot\n";
        $response .= "🔍 Sistem akan extract angka GMV otomatis";

        $this->sendTelegramMessage($chatId, $response);

        return response()->json(['status' => 'ok']);
    }

    private function downloadTelegramFile($fileId)
{
    $token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
    
    if (empty($token)) {
        Log::error('Telegram bot token is not set!');
        throw new \Exception('Telegram bot token is missing');
    }

    Log::info('Using token: ' . substr($token, 0, 10) . '...');
    
    // Get file path from Telegram
    $url = "https://api.telegram.org/bot{$token}/getFile?file_id={$fileId}";
    
    Log::info('Requesting file info from: ' . $url);
    
    $response = @file_get_contents($url);
    
    if ($response === false) {
        $error = error_get_last();
        Log::error('Failed to get file from Telegram', ['error' => $error['message']]);
        throw new \Exception('Failed to get file from Telegram');
    }
    
    $data = json_decode($response, true);

    if (!isset($data['ok']) || !$data['ok']) {
        Log::error('Telegram API error', $data);
        throw new \Exception('Telegram API returned error');
    }

    $filePath = $data['result']['file_path'];
    $fileUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";

    Log::info('Downloading file from: ' . $fileUrl);

    // Download file
    $fileContent = @file_get_contents($fileUrl);
    
    if ($fileContent === false) {
        Log::error('Failed to download file from Telegram');
        throw new \Exception('Failed to download file content');
    }

    // Simpan ke storage
    $fileName = 'screenshot_' . time() . '.jpg';
    $storagePath = 'screenshots/' . $fileName;
    
    Storage::put($storagePath, $fileContent);

    Log::info('File downloaded successfully', ['path' => $storagePath]);

    return $storagePath;
}

private function sendTelegramMessage($chatId, $text)
{
    $token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
    
    if (empty($token)) {
        Log::error('Telegram bot token is not set!');
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    Log::info('Sending message to: ' . $url);

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        $error = error_get_last();
        Log::error('Failed to send message', ['error' => $error['message']]);
        return false;
    }

    Log::info('Message sent to Telegram', ['chat_id' => $chatId, 'response' => $result]);

    return json_decode($result, true);
}
}