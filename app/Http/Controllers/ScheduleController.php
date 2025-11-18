<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Tampilkan form penjadwalan
     */
    public function index()
    {
        $hosts = User::where('role', 'host')->get();
        $assets = Asset::all();
        $sessions = LiveSession::with(['user', 'asset'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return view('schedule.index', compact('hosts', 'assets', 'sessions'));
    }

    /**
     * Simpan jadwal baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'asset_id' => 'required|exists:assets,id',
            'scheduled_at' => 'required|date|after:now',
            'duration' => 'required|integer|min:30|max:480', // 30 menit - 8 jam
        ]);

        $host = User::findOrFail($request->user_id);
        $asset = Asset::findOrFail($request->asset_id);
        
        $startDateTime = Carbon::parse($request->scheduled_at);
        $endDateTime = $startDateTime->copy()->addMinutes($request->duration);

        // Buat event di Google Calendar
        $eventId = $this->calendarService->createEvent(
            "Live Session: {$asset->name} - {$host->name}",
            $startDateTime,
            $endDateTime,
            [$host->email] // Kirim undangan ke host
        );

        // Simpan ke database
        LiveSession::create([
            'user_id' => $request->user_id,
            'asset_id' => $request->asset_id,
            'scheduled_at' => $startDateTime,
            'google_calendar_event_id' => $eventId,
            'status' => 'scheduled',
        ]);

        return redirect()->route('schedule.index')
            ->with('success', 'Jadwal berhasil dibuat dan dikirim ke Google Calendar!');
    }

    /**
     * Batalkan jadwal
     */
    public function destroy(LiveSession $session)
    {
        if ($session->google_calendar_event_id) {
            $this->calendarService->deleteEvent($session->google_calendar_event_id);
        }

        $session->update(['status' => 'cancelled']);

        return redirect()->route('schedule.index')
            ->with('success', 'Jadwal berhasil dibatalkan.');
    }
}