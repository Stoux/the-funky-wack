<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditionRequest;
use App\Http\Requests\UpdateEditionRequest;
use App\Models\Edition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EditionController extends Controller
{
    public function editions()
    {
        $editions = Edition::orderBy('date', 'desc')->get();

        return Inertia::render('Admin/Editions', [
            'editions' => $editions,
        ]);
    }

    public function newEdition()
    {
        return Inertia::render('Admin/Edition', [
            'edition' => null,
        ]);
    }

    public function storeEdition(StoreEditionRequest $request)
    {
        $validated = $request->validated();

        $edition = Edition::create($validated);

        return redirect()->route('admin.editions.view', $edition)->with('success', 'Edition created successfully.');
    }

    public function viewEdition(Edition $edition)
    {
        return Inertia::render('Admin/Edition', [
            'edition' => $edition,
        ]);
    }

    public function updateEdition(UpdateEditionRequest $request, Edition $edition)
    {
        $validated = $request->validated();

        $edition->update($validated);

        return redirect()->route('admin.editions.view', $edition)->with('success', 'Edition updated successfully.');
    }

    public function deleteEdition(Edition $edition)
    {
        $edition->delete();

        return redirect()->route('admin.editions')->with('success', 'Edition deleted successfully.');
    }

    public function viewPoster(Edition $edition)
    {
        return Inertia::render('Admin/EditionPoster', [
            'edition' => $edition,
        ]);
    }

    public function updatePoster(Edition $edition, Request $request)
    {
        $request->validate([
            'poster' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        if (! $request->file('poster')->isValid()) {
            return redirect()->back()->with('error', 'Failed to upload poster.');
        }

        $disk = Storage::disk('public');
        $postersDir = 'posters';

        // Delete existing poster by using stored srcset paths rather than scanning
        // 1) delete all files listed in poster_srcset (webp variants)
        if (is_array($edition->poster_srcset)) {
            foreach ($edition->poster_srcset as $item) {
                if (is_array($item) && isset($item['path'])) {
                    $disk->delete($item['path']);
                } elseif (is_string($item)) {
                    // if stored as simple string for some reason
                    $disk->delete($item);
                }
            }
        }
        // 2) delete the original poster file
        if ($edition->poster_path) {
            $disk->delete($edition->poster_path);
        }
        // 3) reset srcset in DB before proceeding
        $edition->poster_srcset = [];
        $edition->save();

        if (! $disk->exists($postersDir)) {
            $disk->makeDirectory($postersDir);
        }

        $extension = $request->file('poster')->extension();
        $filename = $edition->id.'.'.$extension;

        $path = $request->file('poster')->storeAs($postersDir, $filename, 'public');

        $edition->update([
            'poster_path' => $path,
            'poster_srcset' => [],
        ]);

        // Dispatch resize/conversion job for the new poster
        \App\Jobs\ResizePostersJob::dispatch($edition);

        return redirect()->route('admin.editions.view', $edition)->with('success', 'Poster updated successfully.');
    }
}
