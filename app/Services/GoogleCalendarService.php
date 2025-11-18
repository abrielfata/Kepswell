<?php

namespace App\Services;

use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;

class GoogleCalendarService
{
    /**
     * Buat event di Google Calendar dan return Event ID
     */
    public function createEvent(string $title, Carbon $startDateTime, Carbon $endDateTime, array $attendees = []): string
    {
        $event = new Event;

        $event->name = $title;
        $event->startDateTime = $startDateTime;
        $event->endDateTime = $endDateTime;
        
        // Tambahkan attendees (email host)
        if (!empty($attendees)) {
            $event->attendees = collect($attendees)->map(function ($email) {
                return ['email' => $email];
            })->toArray();
        }

        $event->save();

        return $event->googleEvent->id;
    }

    /**
     * Update event di Google Calendar
     */
    public function updateEvent(string $eventId, array $data): bool
    {
        try {
            $event = Event::find($eventId);
            
            if (isset($data['name'])) {
                $event->name = $data['name'];
            }
            
            if (isset($data['startDateTime'])) {
                $event->startDateTime = Carbon::parse($data['startDateTime']);
            }
            
            if (isset($data['endDateTime'])) {
                $event->endDateTime = Carbon::parse($data['endDateTime']);
            }

            $event->save();
            return true;
        } catch (\Exception $e) {
            \Log::error('Calendar Update Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus event dari Google Calendar
     */
    public function deleteEvent(string $eventId): bool
    {
        try {
            $event = Event::find($eventId);
            $event->delete();
            return true;
        } catch (\Exception $e) {
            \Log::error('Calendar Delete Error: ' . $e->getMessage());
            return false;
        }
    }
}