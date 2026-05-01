<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Support\WhiteLabelEmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public SupportTicketMessage $message,
        public string $audience,
        public string $actionUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$appName, $logoUrl] = WhiteLabelEmailBranding::resolve((int) $this->ticket->tenant_id);

        $isNewTicket = ! $this->message->is_staff && $this->ticket->messages()->count() === 1;

        $subject = $isNewTicket
            ? 'Novo ticket: '.$this->ticket->subject
            : 'Nova mensagem no ticket: '.$this->ticket->subject;

        $author = $this->message->user?->name ?? '—';

        $mm = (new MailMessage)
            ->from((string) config('mail.from.address'), $appName)
            ->markdown('notifications::email', ['logoUrl' => $logoUrl, 'appName' => $appName])
            ->subject($subject)
            ->greeting('Olá!')
            ->line($this->audience === 'staff'
                ? 'Você recebeu uma nova mensagem de suporte de um aluno.'
                : 'Você recebeu uma nova resposta do suporte.')
            ->line('Assunto: '.$this->ticket->subject)
            ->line('De: '.$author)
            ->line('Mensagem:')
            ->line($this->message->body)
            ->action('Ver ticket', $this->actionUrl);

        return $mm;
    }
}
