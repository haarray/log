<?php

namespace App\Http\Controllers;

use App\Http\Services\MLSuggestionService;
use App\Models\Suggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuggestionController extends Controller
{
    public function __construct(
        private readonly MLSuggestionService $mlSuggestionService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $suggestions = Suggestion::query()
            ->where('user_id', $user->id)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('suggestions.index', [
            'suggestions' => $suggestions,
        ]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->mlSuggestionService->generateForUser($user);

        return back()->with('success', 'Suggestions refreshed from current finance data.');
    }

    public function markRead(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $suggestion->user_id === (int) $user->id, 403);

        $suggestion->update(['is_read' => true]);

        return back()->with('success', 'Suggestion marked as read.');
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $user = $request->user();

        Suggestion::query()
            ->where('user_id', $user->id)
            ->where('is_read', true)
            ->delete();

        return back()->with('success', 'Read suggestions cleared.');
    }
}
