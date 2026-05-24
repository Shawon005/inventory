<?php

namespace App\Http\Controllers\Distribution;

use App\DistributionHoliday;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');

        $query = DistributionHoliday::where('business_id', $businessId)
            ->orderByDesc('start_date');

        if ($request->filled('year')) {
            $year = (int) $request->year;
            $query->whereYear('start_date', '<=', $year)
                ->whereYear('end_date', '>=', $year);
        }

        $holidays = $query->paginate(25);

        return view('distribution.holidays.index', compact('holidays'));
    }

    public function create()
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        return view('distribution.holidays.create');
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:1000',
        ]);

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);

        DistributionHoliday::create([
            'business_id' => request()->session()->get('user.business_id'),
            'title' => $request->title,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'note' => $request->note,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('distribution.holidays.index')->with('status', [
            'success' => true,
            'msg' => 'Holiday created successfully.',
        ]);
    }

    public function edit($id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $holiday = DistributionHoliday::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);

        return view('distribution.holidays.edit', compact('holiday'));
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:1000',
        ]);

        $holiday = DistributionHoliday::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);

        $holiday->update([
            'title' => $request->title,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'note' => $request->note,
        ]);

        return redirect()->route('distribution.holidays.index')->with('status', [
            'success' => true,
            'msg' => 'Holiday updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $holiday = DistributionHoliday::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);
        $holiday->delete();

        return redirect()->route('distribution.holidays.index')->with('status', [
            'success' => true,
            'msg' => 'Holiday deleted successfully.',
        ]);
    }
}
