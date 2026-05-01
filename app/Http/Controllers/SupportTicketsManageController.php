<?php

namespace App\Http\Controllers;

use App\Events\SupportTicketMessageCreated;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketsManageController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $status = (string) $request->query('status', 'open');

        $query = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->with(['user:id,name,email'])
            ->orderByDesc('updated_at');

        if ($status === 'open') {
            $query->where('status', SupportTicket::STATUS_OPEN);
        } elseif ($status === 'closed') {
            $query->where('status', SupportTicket::STATUS_CLOSED);
        }

        $tickets = $query->paginate(25)->through(function (SupportTicket $t) {
            return [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status,
                'student_name' => $t->user?->name ?? 'â€”',
                'student_email' => $t->user?->email ?? '',
                'updated_at' => $t->updated_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        });

        return Inertia::render('SupportTickets/Index', [
            'pageTitle' => 'Suporte alunos',
            'tickets' => $tickets,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $this->authorizeTicket($ticket);
        $ticket->load(['messages.user', 'closedBy:id,name', 'user:id,name,email']);

        return Inertia::render('SupportTickets/Show', [
            'pageTitle' => 'Ticket #'.$ticket->id,
            'ticket' => $this->serializeTicket($ticket),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);
        if (! $ticket->isOpen()) {
            return back()->with('error', 'Este ticket estÃ¡ encerrado.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'is_staff' => true,
            'body' => $validated['message'],
        ]);
        $ticket->touch();

        SupportTicketMessageCreated::dispatch($ticket, $message);

        return back()->with('success', 'Resposta enviada.');
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);
        if (! $ticket->isOpen()) {
            return back()->with('error', 'Este ticket jÃ¡ estÃ¡ encerrado.');
        }

        $ticket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Ticket encerrado.');
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        if ($ticket->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }

    private function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'student' => [
                'name' => $ticket->user?->name ?? 'â€”',
                'email' => $ticket->user?->email ?? '',
            ],
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'closed_by_name' => $ticket->closedBy?->name,
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_staff' => $m->is_staff,
                'created_at' => $m->created_at?->toIso8601String(),
                'author_name' => $m->user?->name ?? 'â€”',
            ])->values()->all(),
        ];
    }
}


