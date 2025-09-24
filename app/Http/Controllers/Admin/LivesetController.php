<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidTimetableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLivesetRequest;
use App\Http\Requests\UpdateLivesetRequest;
use App\Models\Edition;
use App\Models\Liveset;
use App\Models\LivesetTrack;
use App\Services\TimetablerService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LivesetController extends Controller
{
    public function livesets(TimetablerService $timetablerService)
    {
        $invalidTimetables = collect([]);
        $livesets = Liveset::with(['edition', 'files'])
            ->orderBy('edition_id', 'desc')
            ->orderBy('lineup_order', 'asc')
            ->get()
            ->map(function (Liveset $liveset) use ($timetablerService, $invalidTimetables) {
                $timeslot = null;
                if ($liveset->edition->timetabler_mode) {
                    try {
                        // Fetch the timetable
                        $timetable = $timetablerService->getTimetable($liveset->edition);

                        // Output the expected timeslot for this liveset
                        $timeslot = $timetable->get($liveset->id)->timeslot();
                    } catch (InvalidTimetableException $e) {
                        $invalidTimetables[$liveset->edition_id] = sprintf(
                            'Unable to generate timetable for TFW #%s: %s',
                            $liveset->edition->number,
                            $e->getMessage()
                        );
                        $timeslot = false;
                    }
                }

                return [
                    ...$liveset->toArray(),
                    'timeslot' => $timeslot,
                ];
            });





        return Inertia::render('Admin/Livesets', [
            'livesets' => $livesets,
            'invalidTimetables' => $invalidTimetables->values(),
        ]);
    }

    public function newLiveset()
    {
        $editions = Edition::orderBy('date', 'desc')->get();

        return Inertia::render('Admin/Liveset', [
            'liveset' => null,
            'editions' => $editions,
        ]);
    }

    public function storeLiveset(StoreLivesetRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Create the liveset
            $liveset = Liveset::create($validated);

            $this->possiblyReorderLivesetsInTimetable($liveset);

            // Add tracks
            if (!empty($validated['tracks_text'])) {
                $tracks = $request->validateAndParseTracks($validated['tracks_text']);

                foreach ($tracks as $track) {
                    $liveset->tracks()->create([
                        'title' => $track['title'],
                        'timestamp' => $track['timestamp'],
                        'order' => $track['order'],
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.livesets.view', $liveset)
                ->with('success', 'Liveset created successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e; // Re-throw ValidationException to be handled by Laravel
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['tracks_text' => $e->getMessage()]);
        }
    }

    public function viewLiveset(Liveset $liveset)
    {
        $editions = Edition::orderBy('date', 'desc')->get();

        // Load the liveset with its tracks
        $liveset->load('tracks');

        return Inertia::render('Admin/Liveset', [
            'liveset' => $liveset,
            'editions' => $editions,
            'fileCount' => $liveset->files->count(),
        ]);
    }

    public function updateLiveset(UpdateLivesetRequest $request, Liveset $liveset)
    {
        $validated = $request->validated();

        try {
            // Start a transaction
            DB::beginTransaction();

            // Update the liveset
            $liveset->update($validated);

            $this->possiblyReorderLivesetsInTimetable($liveset);

            // Just swap all tracks :)
            $liveset->tracks()->delete();
            if (!empty($validated['tracks_text'])) {
                $tracks = $request->validateAndParseTracks($validated['tracks_text']);

                foreach ($tracks as $track) {
                    $liveset->tracks()->create([
                        'title' => $track['title'],
                        'timestamp' => $track['timestamp'],
                        'order' => $track['order'],
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.livesets.view', $liveset)
                ->with('success', 'Liveset updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e; // Re-throw ValidationException to be handled by Laravel
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['tracks_text' => $e->getMessage()]);
        }
    }

    public function deleteLiveset(Liveset $liveset)
    {
        $liveset->delete();

        return redirect()->route('admin.livesets')
            ->with('success', 'Liveset deleted successfully.');
    }

    protected function possiblyReorderLivesetsInTimetable(Liveset $liveset): void
    {
        // Bail if not in timetabler mode or if the liveset doesn't have an order
        if (!$liveset->edition->timetabler_mode) {
            return;
        }

        // Determine previous order (from DB fresh copy) and the desired new order
        $desired = $liveset->lineup_order;
        if ($desired === null) {
            // Don't reorder when it doens't have an order.
            return;
        }

        // Ensure we only affect livesets within the same edition
        $editionId = $liveset->edition_id;

        // Build compacted list excluding current liveset
        $siblings = Liveset::where('edition_id', $editionId)
            ->where('id', '!=', $liveset->id)
            ->whereNotNull('lineup_order')
            ->orderBy('lineup_order')
            ->get();

        $expected = 1;
        foreach ($siblings as $sibling) {
            // Reserve the desired slot for the current liveset if specified
            if ($expected === $desired) {
                $expected++;
            }
            if ($sibling->lineup_order !== $expected) {
                $sibling->lineup_order = $expected;
                $sibling->save();
            }
            $expected++;
        }

        // If desired is null or larger than the next expected slot, append at the end
        if ($desired >= $expected) {
            $desired = $expected; // place at end
        }

        // Finally set this liveset to its target order and save
        if ($liveset->lineup_order !== $desired) {
            $liveset->lineup_order = $desired;
            $liveset->save();
        }
    }
}
