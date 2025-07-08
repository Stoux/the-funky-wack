<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLivesetRequest;
use App\Http\Requests\UpdateLivesetRequest;
use App\Models\Edition;
use App\Models\Liveset;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LivesetController extends Controller
{
    public function livesets()
    {
        $livesets = Liveset::with(['edition', 'files'])
            ->orderBy('edition_id', 'desc')
            ->orderBy('lineup_order', 'asc')
            ->get();

        return Inertia::render('Admin/Livesets', [
            'livesets' => $livesets,
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
}
