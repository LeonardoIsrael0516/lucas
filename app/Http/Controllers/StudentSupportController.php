<?php

namespace App\Http\Controllers;

use App\Events\SupportTicketMessageCreated;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\MemberAreaResolver;
use App\Support\StudentAreaTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentSupportController extends Controller
{
    public function __construct(
        private MemberAreaResolver $resolver,
    ) {}

    private function tenantIdOrAbort(): int
    {
        $user = auth()->user();
        $tenantId = StudentAreaTenant::idForUser($user);
        if (! $tenantId) {
            abort(404);
        }

        return $tenantId;
    }

    private function supportEnabled(int $tenantId): bool
    {
        return Setting::get('student_support_enabled', '0', $tenantId) === '1';
    }

    private function redirectIfDisabled(int $tenantId): ?RedirectResponse
    {
        if (! $this->supportEnabled($tenantId)) {
            return redirect()->route('member-area.index')
                ->with('error', 'O suporte por tickets nÃ£o estÃ¡ disponÃ­vel no momento.');
        }

        return null;
    }

    private function sharedProps(int $tenantId): array
    {
        $waEnabled = Setting::get('student_support_whatsapp_enabled', '0', $tenantId) === '1';
        $waUrl = trim((string) Setting::get('student_support_whatsapp_url', '', $tenantId));

        return [
            'student_branding' => $this->studentBrandingPayload($tenantId),
            'support_whatsapp' => [
                'enabled' => $waEnabled && $waUrl !== '',
                'url' => $waUrl,
            ],
            'suporte_href' => route('student-support.index'),
            'profile_href' => route('profile.index'),
            'notifications_unread_count' => $this->safeUnreadNotificationsCount(auth()->user()),
            'community_href' => null,
        ];
    }

    private function studentBrandingPayload(int $tenantId): array
    {
        $primary = (string) Setting::get('student_area_primary', '#0ea5e9', $tenantId);
        $logoUrl = null;
        $logoPath = (string) Setting::get('student_area_logo', '', $tenantId);
        if ($logoPath !== '') {
            try {
                $storage = new \App\Services\StorageService($tenantId);
                $logoUrl = $storage->exists($logoPath) ? $storage->url($logoPath) : null;
            } catch (\Throwable) {
                $logoUrl = null;
            }
        }

        return ['primary' => $primary, 'logo_url' => $logoUrl];
    }

    private function safeUnreadNotificationsCount(?\App\Models\User $user): int
    {
        if (! $user || ! \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    private function firstCommunityHref(\App\Models\User $user): ?string
    {
        $ownedProducts = $user->products()
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => $p->type === Product::TYPE_AREA_MEMBROS);

        foreach ($ownedProducts as $product) {
            $config = $product->member_area_config ?? [];
            if (! (bool) ($config['community_enabled'] ?? false)) {
                continue;
            }
            if (! $product->checkout_slug) {
                continue;
            }
            if (! $product->hasMemberAreaAccess($user)) {
                continue;
            }
            $base = rtrim($this->resolver->baseUrlForProduct($product), '/');

            return $base.'/comunidade';
        }

        return null;
    }

    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();
        $tenantId = $this->tenantIdOrAbort();
        if ($r = $this->redirectIfDisabled($tenantId)) {
            return $r;
        }

        $tickets = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status,
                'updated_at' => $t->updated_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        $props = $this->sharedProps($tenantId);
        $props['community_href'] = $this->firstCommunityHref($user);
        $props['tickets'] = $tickets;
        $props['auth_user'] = [
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $this->initials($user->name ?? ''),
        ];

        return Inertia::render('MemberArea/Support', $props);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort();
        if ($r = $this->redirectIfDisabled($tenantId)) {
            return $r;
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $user = auth()->user();

        [$ticket, $message] = DB::transaction(function () use ($validated, $tenantId, $user) {
            $ticket = SupportTicket::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'status' => SupportTicket::STATUS_OPEN,
            ]);

            $message = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'is_staff' => false,
                'body' => $validated['message'],
            ]);

            return [$ticket, $message];
        });

        SupportTicketMessageCreated::dispatch($ticket, $message);

        return redirect()->route('student-support.show', $ticket)->with('success', 'Ticket criado.');
    }

    public function show(SupportTicket $ticket): Response|RedirectResponse
    {
        $user = auth()->user();
        $tenantId = $this->tenantIdOrAbort();
        if ($r = $this->redirectIfDisabled($tenantId)) {
            return $r;
        }

        if ($ticket->tenant_id !== $tenantId || $ticket->user_id !== $user->id) {
            abort(403);
        }

        $ticket->load(['messages.user']);

        $props = $this->sharedProps($tenantId);
        $props['community_href'] = $this->firstCommunityHref($user);
        $props['auth_user'] = [
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $this->initials($user->name ?? ''),
        ];
        $props['ticket'] = $this->serializeTicketForStudent($ticket);

        return Inertia::render('MemberArea/SupportShow', $props);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = auth()->user();
        $tenantId = $this->tenantIdOrAbort();
        if ($r = $this->redirectIfDisabled($tenantId)) {
            return $r;
        }

        if ($ticket->tenant_id !== $tenantId || $ticket->user_id !== $user->id) {
            abort(403);
        }

        if (! $ticket->isOpen()) {
            return back()->with('error', 'Este ticket estÃ¡ encerrado.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_staff' => false,
            'body' => $validated['message'],
        ]);
        $ticket->touch();

        SupportTicketMessageCreated::dispatch($ticket, $message);

        return back()->with('success', 'Mensagem enviada.');
    }

    private function serializeTicketForStudent(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_staff' => $m->is_staff,
                'created_at' => $m->created_at?->toIso8601String(),
                'author_name' => $m->user?->name ?? 'â€”',
            ])->values()->all(),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $s = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $s .= mb_substr($p, 0, 1, 'UTF-8');
        }

        return mb_strtoupper($s ?: '?', 'UTF-8');
    }
}



