<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    protected array $days = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = AvailabilitySlot::where('admin_user_id', $user->id)->orderBy('day_of_week')->orderBy('start_time');

        if ($request->filled('day_of_week')) {
            $day = strtolower($request->get('day_of_week'));
            if (! in_array($day, $this->days, true)) {
                return response()->json(['message' => 'Invalid day_of_week'], 422);
            }
            $query->where('day_of_week', $day);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'increment_minutes' => 'required|integer|in:15,30,60',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $day = strtolower($data['day_of_week']);

        if (! in_array($day, $this->days, true)) {
            return response()->json(['message' => 'Invalid day_of_week'], 422);
        }

        $startMinutes = $this->timeToMinutes($data['start_time']);
        $endMinutes = $this->timeToMinutes($data['end_time']);

        $overlaps = AvailabilitySlot::where('admin_user_id', $user->id)
            ->where('day_of_week', $day)
            ->get()
            ->filter(function (AvailabilitySlot $slot) use ($startMinutes, $endMinutes) {
                $slotStart = $this->timeToMinutes($slot->start_time);
                $slotEnd = $this->timeToMinutes($slot->end_time);

                return $startMinutes < $slotEnd && $endMinutes > $slotStart;
            });

        if ($overlaps->isNotEmpty()) {
            return response()->json([
                'message' => 'This time range overlaps with an existing availability for this day.',
            ], 422);
        }

        $slot = AvailabilitySlot::create([
            'admin_user_id' => $user->id,
            'day_of_week' => $day,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'increment_minutes' => $data['increment_minutes'],
        ]);

        return response()->json($slot, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $slot = AvailabilitySlot::where('admin_user_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'day_of_week' => 'sometimes|required|string',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'increment_minutes' => 'sometimes|required|integer|in:15,30,60',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $day = array_key_exists('day_of_week', $data) ? strtolower($data['day_of_week']) : $slot->day_of_week;

        if (! in_array($day, $this->days, true)) {
            return response()->json(['message' => 'Invalid day_of_week'], 422);
        }

        $start = $data['start_time'] ?? $slot->start_time;
        $end = $data['end_time'] ?? $slot->end_time;

        if ($this->timeToMinutes($end) <= $this->timeToMinutes($start)) {
            return response()->json(['message' => 'end_time must be after start_time'], 422);
        }

        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);

        $overlaps = AvailabilitySlot::where('admin_user_id', $user->id)
            ->where('day_of_week', $day)
            ->where('id', '!=', $slot->id)
            ->get()
            ->filter(function (AvailabilitySlot $s) use ($startMinutes, $endMinutes) {
                $slotStart = $this->timeToMinutes($s->start_time);
                $slotEnd = $this->timeToMinutes($s->end_time);

                return $startMinutes < $slotEnd && $endMinutes > $slotStart;
            });

        if ($overlaps->isNotEmpty()) {
            return response()->json([
                'message' => 'This time range overlaps with an existing availability for this day.',
            ], 422);
        }

        $slot->update([
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'increment_minutes' => $data['increment_minutes'] ?? $slot->increment_minutes,
        ]);

        return response()->json($slot);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $slot = AvailabilitySlot::where('admin_user_id', $user->id)->findOrFail($id);
        $slot->delete();

        return response()->json(null, 204);
    }

    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return ((int) $h) * 60 + (int) $m;
    }

    protected function minutesToTime(int $minutes): string
    {
        $h = floor($minutes / 60) % 24;
        $m = $minutes % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}

