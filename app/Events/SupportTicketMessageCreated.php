<?php

namespace App\Events;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketMessageCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportTicketMessage $message,
    ) {}
}
