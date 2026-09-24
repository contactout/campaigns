<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;

trait ResolvesCurrentTeam
{
    /**
     * The team resolved for the current request.
     */
    protected ?Team $resolvedCurrentTeam = null;

    /**
     * Resolve the current team from the route, falling back to the user's current team.
     *
     * The `{current_team}` route parameter is a slug string unless a route model
     * binding has already been applied.
     */
    protected function resolveCurrentTeam(Request $request): Team
    {
        if ($this->resolvedCurrentTeam instanceof Team) {
            return $this->resolvedCurrentTeam;
        }

        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::query()->where('slug', $team)->first();
        }

        if (! $team instanceof Team) {
            $team = $request->user()?->currentTeam;
        }

        if (! $team instanceof Team) {
            abort(404);
        }

        return $this->resolvedCurrentTeam = $team;
    }
}
