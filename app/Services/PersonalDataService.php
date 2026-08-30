<?php

namespace App\Services;

use App\Models\DataDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PersonalDataService
{
    public function eraseConversations(User $user): void
    {
        $user->readingLogs()->delete();
        $user->readingShares()->delete();
    }

    public function requestErasure(User $user, ?string $note = null): DataDeletionRequest
    {
        if ($user->anonymized_at !== null) {
            throw new RuntimeException('already_anonymized');
        }

        $pending = $user->dataDeletionRequests()
            ->where('status', DataDeletionRequest::STATUS_PENDING)
            ->first();

        if ($pending !== null) {
            throw new RuntimeException('pending_exists');
        }

        return $user->dataDeletionRequests()->create([
            'status' => DataDeletionRequest::STATUS_PENDING,
            'note' => $note,
        ]);
    }

    public function processErasure(DataDeletionRequest $request, User $admin): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException('not_pending');
        }

        $user = $request->user;
        if ($user === null) {
            throw new RuntimeException('missing_user');
        }

        DB::transaction(function () use ($request, $admin, $user) {
            $this->eraseConversations($user);
            $this->anonymizeUser($user);

            $request->update([
                'status' => DataDeletionRequest::STATUS_PROCESSED,
                'processed_at' => now(),
                'processed_by' => $admin->id,
            ]);
        });
    }

    public function rejectErasure(DataDeletionRequest $request, User $admin): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException('not_pending');
        }

        $request->update([
            'status' => DataDeletionRequest::STATUS_REJECTED,
            'processed_at' => now(),
            'processed_by' => $admin->id,
        ]);
    }

    public function anonymizeUser(User $user): void
    {
        if ($user->anonymized_at !== null) {
            return;
        }

        $oldEmail = (string) $user->email;

        $user->forceFill([
            'name' => 'Törölt fiók',
            'email' => 'deleted+'.$user->id.'-'.Str::lower(Str::random(8)).'@anonymized.invalid',
            'password' => Str::password(32),
            'provider' => null,
            'provider_id' => null,
            'remember_token' => null,
            'email_verified_at' => null,
            'template_id' => null,
            'anonymized_at' => now(),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
