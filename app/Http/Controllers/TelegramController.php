<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\OcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use TelegramBot\Api\BotApi;

class TelegramController extends Controller
{
    protected $bot;
    protected $ocrService;

    public function __construct(OcrService $ocrService)
    {
        $this->bot = new BotApi(env('TELEGRAM_BOT_TOKEN'));
        $this->ocrService = $ocrService;
    }

    /**
     * Handle Telegram Webhook
     */
    public function handle(Request $request)
    {
        $update = $request->all();

        // Cek apakah ada foto
        if (isset($update['message']['photo'])) {
            $photos = $update['message']['photo'];
            $largestPhoto = end($photos); // Ambil foto dengan resolusi terbesar
            $fileId = $largestPhoto['file_id'];
            $chatId = $update['message']['chat']['id'];

            try {
                // Download foto dari Telegram
                $file = $this->bot->getFile($fileId);
                $filePath = $file->getFilePath();
                $fileUrl = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath;
                
                $imageContent = file_get_contents($fileUrl);
                $fileName = 'screenshots/' . uniqid() . '.jpg';
                Storage::put($fileName, $imageContent);

                // Jalankan OCR
                $localPath = storage_path('app/' . $fileName);
                $gmv = $this->ocrService->extractGmv($localPath);

                // Cari live session yang belum ada laporan (status = scheduled, gmv = null)
                $session = LiveSession::where('status', 'scheduled')
                    ->whereNull('gmv')
                    ->orderBy('scheduled_at', 'desc')
                    ->first();

                if ($session) {
                    $session->update([
                        'gmv' => $gmv,
                        'screenshot_path' => $fileName,
                        'status' => 'completed',
                    ]);

                    $this->bot->sendMessage(
                        $chatId,
                        "✅ Laporan berhasil disimpan!\nGMV: Rp " . number_format($gmv, 0, ',', '.')
                    );
                } else {
                    $this->bot->sendMessage($chatId, "❌ Tidak ada jadwal aktif untuk laporan ini.");
                }

            } catch (\Exception $e) {
                \Log::error('Telegram Webhook Error: ' . $e->getMessage());
                $this->bot->sendMessage($chatId, "❌ Terjadi kesalahan: " . $e->getMessage());
            }
        }

        return response()->json(['ok' => true]);
    }
}