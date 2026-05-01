<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketMessageNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentSupportTicketsTest extends TestCase
{
    public function test_aluno_creates_ticket_when_support_enabled(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $tenantId = 1;
        Setting::set('student_support_enabled', '1', $tenantId);

        $producer = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => $tenantId,
            'email' => 'producer@test.com',
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
            'email' => 'aluno@test.com',
        ]);

        Notification::fake();

        $this->actingAs($aluno)
            ->post('/suporte', [
                'subject' => 'Preciso de ajuda',
                'message' => 'Não consigo acessar a aula.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->where('user_id', $aluno->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame('Preciso de ajuda', $ticket->subject);
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertSame(1, $ticket->messages()->count());

        Notification::assertSentOnDemand(SupportTicketMessageNotification::class, function ($notification, $channels, $notifiable) use ($producer) {
            return in_array('mail', $channels, true)
                && method_exists($notifiable, 'routeNotificationFor')
                && $notifiable->routeNotificationFor('mail') === $producer->email;
        });

        $other = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
        ]);

        $this->actingAs($other)
            ->get('/suporte/'.$ticket->id)
            ->assertForbidden();
    }

    public function test_aluno_cannot_reply_when_ticket_closed(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $tenantId = 1;
        Setting::set('student_support_enabled', '1', $tenantId);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
        ]);

        $staff = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => $tenantId,
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'user_id' => $aluno->id,
            'subject' => 'Teste',
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $staff->id,
        ]);

        $this->actingAs($aluno)
            ->post('/suporte/'.$ticket->id.'/reply', [
                'message' => 'Nova mensagem',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_team_without_suporte_permission_cannot_open_panel(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $role = \App\Models\TeamRole::create([
            'tenant_id' => 1,
            'name' => 'Sem suporte',
            'permissions' => [
                'dashboard.view' => true,
                'suporte.view' => false,
                'vendas.view' => false,
                'produtos.view' => false,
                'relatorios.view' => false,
                'integracoes.view' => false,
                'email_marketing.view' => false,
                'api_pagamentos.view' => false,
                'configuracoes.view' => false,
                'equipe.manage' => false,
            ],
        ]);

        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'tenant_id' => 1,
            'team_role_id' => $role->id,
        ]);

        $this->actingAs($team)->get('/suporte-alunos')->assertForbidden();
    }

    public function test_staff_reply_sends_email_to_student(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $tenantId = 1;
        Setting::set('student_support_enabled', '1', $tenantId);

        $producer = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => $tenantId,
            'email' => 'producer@test.com',
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
            'email' => 'aluno@test.com',
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'user_id' => $aluno->id,
            'subject' => 'Assunto',
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        Notification::fake();

        $this->actingAs($producer)
            ->post('/suporte-alunos/'.$ticket->id.'/reply', ['message' => 'Olá, como posso ajudar?'])
            ->assertRedirect();

        Notification::assertSentOnDemand(SupportTicketMessageNotification::class, function ($notification, $channels, $notifiable) use ($aluno) {
            return in_array('mail', $channels, true)
                && method_exists($notifiable, 'routeNotificationFor')
                && $notifiable->routeNotificationFor('mail') === $aluno->email;
        });
    }
}
