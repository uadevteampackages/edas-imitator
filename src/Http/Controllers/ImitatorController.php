<?php

namespace Uadevteampackages\Imitator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Uadevteampackages\Imitator\Graph\GraphClient;
use Uadevteampackages\Imitator\Imitator;

class ImitatorController extends Controller
{
    public function __construct(
        private Imitator $imitator,
        private GraphClient $graph,
    ) {}

    public function index(): View
    {
        return view('imitator::index', [
            'status' => $this->imitator->status(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $searchTerm = (string) $request->query('q', '');

        if (trim($searchTerm) === '') {
            return response()->json(['value' => []]);
        }

        $results = $this->graph->searchUsers($searchTerm);

        if ($results === null) {
            return response()->json(['value' => [], 'error' => 'Unable to search Entra users.'], 502);
        }

        return response()->json($results);
    }

    public function store(Request $request): RedirectResponse
    {
        $original = $this->imitator->active()
            ? $this->imitator->originalUser()
            : Auth::user();

        if (! $original) {
            abort(403);
        }

        $validated = $request->validate([
            'user.id' => ['required', 'string'],
            'user.name' => ['required', 'string'],
            'user.userPrincipalName' => ['required', 'string'],
            'user.email' => ['nullable', 'string'],
            'user.job_title' => ['nullable', 'string'],
        ]);

        if ($this->imitator->targetsSameUser($original, $validated['user'])) {
            throw ValidationException::withMessages([
                'user.id' => 'You cannot imitate yourself.',
            ]);
        }

        $target = $this->imitator->upsertUserFromEntra($validated['user']);

        $this->imitator->start($original, $target);

        Log::info('Imitator: started', [
            'original_user_id' => $original->getAuthIdentifier(),
            'target_user_id' => $target->getAuthIdentifier(),
            'started_at' => now()->toISOString(),
        ]);

        return redirect('/')->with('success', 'Now imitating '.$target->name.'.');
    }

    public function destroy(): RedirectResponse
    {
        if (! $this->imitator->active()) {
            abort(403);
        }

        $originalUser = $this->imitator->originalUser();
        $targetUser = $this->imitator->targetUser();

        $this->imitator->stop();

        Log::info('Imitator: stopped', [
            'original_user_id' => $originalUser?->getAuthIdentifier(),
            'target_user_id' => $targetUser?->getAuthIdentifier(),
            'stopped_at' => now()->toISOString(),
        ]);

        return redirect('/imitator')->with('success', 'Imitation stopped.');
    }
}
