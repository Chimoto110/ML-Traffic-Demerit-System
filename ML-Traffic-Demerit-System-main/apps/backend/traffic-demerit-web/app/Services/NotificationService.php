<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyUser(
        ?int $recipientUserId,
        string $title,
        string $message,
        array $context = [],
        ?string $relatedType = null,
        ?int $relatedId = null
    ): ?SystemNotification {
        if (! $recipientUserId) {
            return null;
        }

        $user = User::find($recipientUserId);

        $notification = SystemNotification::create([
            'recipient_user_id' => $recipientUserId,
            'channel' => 'in_app',
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'sent_at' => now(),
        ]);

        if ($user?->email) {
            $this->sendEmail($user, $title, $message, $context, $relatedType, $relatedId);
        }

        return $notification;
    }

    public function notifyRole(
        string $role,
        string $title,
        string $message,
        array $context = [],
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        SystemNotification::create([
            'recipient_role' => $role,
            'channel' => 'in_app',
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'sent_at' => now(),
        ]);

        User::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get()
            ->each(function (User $user) use ($title, $message, $context, $relatedType, $relatedId) {
                $this->sendEmail($user, $title, $message, $context, $relatedType, $relatedId);
            });
    }

    protected function sendEmail(
        User $user,
        string $title,
        string $message,
        array $context = [],
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        try {
            $details = collect($context)
                ->map(fn ($value, $key) => ucfirst((string) $key) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value)))
                ->implode("\n");

            $body = trim($message . "\n\n" . ($details !== '' ? "Details:\n{$details}" : ''));

            Mail::raw($body, function ($mail) use ($user, $title) {
                $mail->to($user->email)
                    ->subject('NTSA Traffic Demerit Update: ' . $title);
            });

            SystemNotification::create([
                'recipient_user_id' => $user->id,
                'channel' => 'email',
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'status' => 'read',
                'sent_at' => now(),
                'read_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notification email send failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
                'title' => $title,
            ]);

            SystemNotification::create([
                'recipient_user_id' => $user->id,
                'channel' => 'email',
                'title' => $title,
                'message' => $message,
                'context' => array_merge($context, ['email_error' => $e->getMessage()]),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'status' => 'unread',
                'sent_at' => now(),
            ]);
        }
    }
}
