<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditionRequest;
use App\Http\Requests\UpdateEditionRequest;
use App\Models\Edition;
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

        return redirect()->route('admin.editions.view', $edition)
            ->with('success', 'Edition created successfully.');
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

        return redirect()->route('admin.editions.view', $edition)
            ->with('success', 'Edition updated successfully.');
    }

    public function deleteEdition(Edition $edition)
    {
        $edition->delete();

        return redirect()->route('admin.editions')
            ->with('success', 'Edition deleted successfully.');
    }
}
