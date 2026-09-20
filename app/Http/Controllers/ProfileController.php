<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'theme' => ['sometimes', Rule::in(['system', 'light', 'dark'])],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'current_password' => [$request->filled('password') || ($request->has('email') && $request->input('email') !== $user->email) ? 'required' : 'nullable', 'current_password'],
        ]);
        unset($data['current_password']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            AuditEvent::create(['user_id' => $user->id, 'action' => 'profile_updated', 'changes' => ['fields' => array_keys($data)]]);
        });
        if (isset($data['password'])) {
            $request->session()->regenerate();
        }

        return response()->json($user)->header('X-CSRF-Token', csrf_token());
    }
}
