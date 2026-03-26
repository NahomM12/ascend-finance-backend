<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Admin: list all appointments.
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['adminUser', 'investorUser'])->orderBy('scheduled_at');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    /**
     * Admin: create an available appointment slot.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:240',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();

        $appointment = Appointment::create([
            'admin_user_id' => $user->id,
            'investor_user_id' => null,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'status' => 'available',
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($appointment, 201);
    }

    /**
     * Admin: update an appointment slot.
     */
    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'sometimes|required|date',
            'duration_minutes' => 'sometimes|required|integer|min:15|max:240',
            'status' => 'sometimes|required|in:available,booked,cancelled,completed',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $appointment->update($validator->validated());

        return response()->json($appointment);
    }

    /**
     * Admin: delete an appointment slot.
     */
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();

        return response()->json(null, 204);
    }

    /**
     * Investor: list available upcoming slots.
     */
    public function available()
    {
        $now = Carbon::now();
        $days = 14;
        $endDate = $now->copy()->addDays($days - 1)->endOfDay();

        $availability = AvailabilitySlot::all();

        $bookings = Appointment::where('status', 'booked')
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $endDate])
            ->get();

        $slots = [];

        for ($date = $now->copy()->startOfDay(); $date->lte($endDate); $date->addDay()) {
            $dayName = strtolower($date->format('l'));

            $dayAvailability = $availability->where('day_of_week', $dayName);

            foreach ($dayAvailability as $window) {
                $startMinutes = $this->timeToMinutes($window->start_time);
                $endMinutes = $this->timeToMinutes($window->end_time);
                $increment = $window->increment_minutes;

                for ($m = $startMinutes; $m + 60 <= $endMinutes; $m += $increment) {
                    $slotStart = $date->copy()->addMinutes($m);
                    if ($slotStart->lessThan($now)) {
                        continue;
                    }
                    $slotEnd = $slotStart->copy()->addHour();

                    $conflict = $bookings->first(function (Appointment $booking) use ($slotStart, $slotEnd) {
                        $bookingStart = Carbon::parse($booking->scheduled_at);
                        $bookingEnd = $bookingStart->copy()->addMinutes($booking->duration_minutes ?? 60);

                        return $slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart);
                    });

                    if (! $conflict) {
                        $slots[] = [
                            'scheduled_at' => $slotStart->toIso8601String(),
                            'end_at' => $slotEnd->toIso8601String(),
                        ];
                    }
                }
            }
        }

        return response()->json($slots);
    }

    /**
     * Investor: book an available slot.
     */
    public function book(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'investors') {
            return response()->json(['message' => 'Only investors can book appointments'], 403);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $scheduledAt = Carbon::parse($validator->validated()['scheduled_at']);
        $endAt = $scheduledAt->copy()->addHour();

        $dayName = strtolower($scheduledAt->format('l'));
        $timeMinutes = $this->timeToMinutes($scheduledAt->format('H:i'));

        $window = AvailabilitySlot::where('day_of_week', $dayName)->get()->first(function (AvailabilitySlot $slot) use ($timeMinutes) {
            $startMinutes = $this->timeToMinutes($slot->start_time);
            $endMinutes = $this->timeToMinutes($slot->end_time);

            if ($timeMinutes < $startMinutes || $timeMinutes + 60 > $endMinutes) {
                return false;
            }

            $diff = $timeMinutes - $startMinutes;

            return $diff % $slot->increment_minutes === 0;
        });

        if (! $window) {
            return response()->json(['message' => 'Selected time is not available'], 422);
        }

        $conflict = Appointment::where('status', 'booked')
            ->where(function ($q) use ($scheduledAt, $endAt) {
                $q->where('scheduled_at', '<', $endAt->toDateTimeString());
                $q->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$scheduledAt->toDateTimeString()]);
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'This time slot has just been booked. Please choose another time.'], 409);
        }

        $appointment = Appointment::create([
            'admin_user_id' => $window->admin_user_id,
            'investor_user_id' => $user->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'booked',
            'title' => null,
            'notes' => null,
        ]);

        return response()->json($appointment);
    }

    /**
     * Investor: list their own bookings.
     */
    public function myAppointments(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $appointments = Appointment::with('adminUser')
            ->where('investor_user_id', $user->id)
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json($appointments);
    }

    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return ((int) $h) * 60 + (int) $m;
    }
}
