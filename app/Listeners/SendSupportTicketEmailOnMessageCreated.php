<?php

namespace App\Listeners;

use App\Events\SupportTicketMessageCreated;
use App\Models\User;
use App\Notifications\SupportTicketMessageNotification;
use Illuminate\Support\Facades\Notification;

class SendSupportTicketEmailOnMessageCreated
{
    public function handle(SupportTicketMessageCreated $event): void
    {
        $ticket = $event->ticket;
        $message = $event->message;

        // Staff reply -> send to student
        if ($message->is_staff) {
            $studentEmail = (string) ($ticket->user?->email ?? '');
            if ($studentEmail !== '') {
                $url = url('/suporte/'.$ticket->id);
                Notification::route('mail', $studentEmail)
                    ->notify(new SupportTicketMessageNotification($ticket, $message, 'student', $url));
            }
            return;
        }

        // Student message -> send to infoprodutor/admin of the tenant
        $emails = User::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->whereIn('role', [User::ROLE_INFOPRODUTOR, User::ROLE_ADMIN])
            ->pluck('email')
            ->filter(fn ($e) => is_string($e) && trim($e) !== '')
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $url = url('/suporte-alunos/'.$ticket->id);
        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new SupportTicketMessageNotification($ticket, $message, 'staff', $url));
        }
    }
}
