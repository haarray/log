<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $totalBalance = (float) $accounts->where('is_active', true)->sum('balance');

        return view('accounts.index', [
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
            'balance' => ['required', 'numeric', 'between:-999999999999,999999999999'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Account::query()->create([
            'user_id' => $user->id,
            'name' => trim((string) $validated['name']),
            'institution' => trim((string) ($validated['institution'] ?? '')) ?: null,
            'type' => strtolower(trim((string) $validated['type'])),
            'currency' => strtoupper(trim((string) ($validated['currency'] ?? 'NPR'))) ?: 'NPR',
            'balance' => (float) $validated['balance'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $account->user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
            'balance' => ['required', 'numeric', 'between:-999999999999,999999999999'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account->update([
            'name' => trim((string) $validated['name']),
            'institution' => trim((string) ($validated['institution'] ?? '')) ?: null,
            'type' => strtolower(trim((string) $validated['type'])),
            'currency' => strtoupper(trim((string) ($validated['currency'] ?? 'NPR'))) ?: 'NPR',
            'balance' => (float) $validated['balance'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Account updated.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $account->user_id === (int) $user->id, 403);

        $account->delete();

        return back()->with('success', 'Account deleted.');
    }
}
