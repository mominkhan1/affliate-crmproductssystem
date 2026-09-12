<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * List all teams, across every customer.
     */
    public function index(): View
    {
        $teams = Team::with('user:id,name')->withCount('orders')->latest()->paginate(15);

        return view('admin.teams.index', compact('teams'));
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        return view('admin.teams.create', [
            'team' => new Team(['is_active' => true]),
            'customers' => $this->customers(),
        ]);
    }

    /**
     * Store a new team.
     */
    public function store(TeamRequest $request): RedirectResponse
    {
        Team::create($request->validated());

        return redirect()
            ->route('admin.teams.index')
            ->with('status', 'Team created.');
    }

    /**
     * Show the edit form.
     */
    public function edit(Team $team): View
    {
        return view('admin.teams.edit', [
            'team' => $team,
            'customers' => $this->customers(),
        ]);
    }

    /**
     * Update a team.
     */
    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()
            ->route('admin.teams.index')
            ->with('status', 'Team updated.');
    }

    /**
     * Delete a team that has no orders against it.
     */
    public function destroy(Team $team): RedirectResponse
    {
        if ($team->orders()->exists()) {
            return back()->with('error', 'This team has orders and cannot be deleted. Switch it to inactive instead.');
        }

        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('status', 'Team deleted.');
    }

    /**
     * The accounts a team can belong to.
     */
    private function customers(): Collection
    {
        return User::where('role', 'user')->orderBy('name')->get(['id', 'name', 'email']);
    }
}
