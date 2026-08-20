<?php

namespace App\Http\Controllers;

use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeterReadingController extends Controller
{
    public function index()
    {
        $readings = MeterReading::orderBy('reading_date')->get();

        return response()->json($this->withConsumption($readings));
    }

    public function store(Request $request)
    {
        $validated = $this->validateReading($request);

        $reading = MeterReading::create($validated);

        return response()->json($this->attachConsumption($reading), 201);
    }

    public function show(MeterReading $meterReading)
    {
        return response()->json($this->attachConsumption($meterReading));
    }


    public function update(Request $request, MeterReading $meterReading)
    {
        $validated = $this->validateReading($request, $meterReading);

        $meterReading->update($validated);

        return response()->json($this->attachConsumption($meterReading->fresh()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MeterReading $meterReading)
    {
        $meterReading->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate a reading, ensuring the value is not lower than the
     * chronologically previous reading and not higher than the next one.
     */
    private function validateReading(Request $request, ?MeterReading $ignoring = null): array
    {
        $validated = $request->validate([
            'reading_date' => [
                'required',
                'date',
                Rule::unique('meter_readings', 'reading_date')->ignore($ignoring?->id),
            ],
            'reading_value' => ['required', 'numeric', 'min:0'],
        ]);

        $previous = MeterReading::where('reading_date', '<', $validated['reading_date'])
            ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
            ->orderBy('reading_date', 'desc')
            ->first();

        $next = MeterReading::where('reading_date', '>', $validated['reading_date'])
            ->when($ignoring, fn ($query) => $query->where('id', '!=', $ignoring->id))
            ->orderBy('reading_date')
            ->first();

        if ($previous && $validated['reading_value'] < $previous->reading_value) {
            throw ValidationException::withMessages([
                'reading_value' => "Reading must be at least {$previous->reading_value} (the previous reading on {$previous->reading_date->format('Y-m-d')}).",
            ]);
        }

        if ($next && $validated['reading_value'] > $next->reading_value) {
            throw ValidationException::withMessages([
                'reading_value' => "Reading must be at most {$next->reading_value} (the next reading on {$next->reading_date->format('Y-m-d')}).",
            ]);
        }

        return $validated;
    }

    private function attachConsumption(MeterReading $reading): array
    {
        $previous = MeterReading::where('reading_date', '<', $reading->reading_date->format('Y-m-d'))
            ->orderBy('reading_date', 'desc')
            ->first();

        return [
            ...$reading->toArray(),
            'previous_reading_value' => $previous?->reading_value,
            'consumption' => $previous ? round($reading->reading_value - $previous->reading_value, 2) : null,
        ];
    }

    private function withConsumption($readings)
    {
        $previousValue = null;

        return $readings->map(function (MeterReading $reading) use (&$previousValue) {
            $consumption = $previousValue !== null
                ? round($reading->reading_value - $previousValue, 2)
                : null;

            $result = [
                ...$reading->toArray(),
                'previous_reading_value' => $previousValue,
                'consumption' => $consumption,
            ];

            $previousValue = (float) $reading->reading_value;

            return $result;
        })->values();
    }
}
