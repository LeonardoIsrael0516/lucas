<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductAccessDaysTest extends TestCase
{
    public function test_grant_sets_access_expires_at_from_course_access_days(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $product = $this->createTestProduct([
            'type' => Product::TYPE_AREA_MEMBROS,
            'course_access_days' => 30,
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
        ]);

        app(ProductAccessService::class)->grant($aluno, $product);

        $expires = DB::table('product_user')
            ->where('product_id', $product->id)
            ->where('user_id', $aluno->id)
            ->value('access_expires_at');

        $this->assertNotNull($expires);
        $this->assertTrue($product->hasMemberAreaAccess($aluno));
    }

    public function test_alunos_store_links_existing_student_by_email(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        $owner = User::factory()->create([
            'role' => User::ROLE_INFOPRODUTOR,
            'tenant_id' => 1,
        ]);

        $productA = $this->createTestProduct(['type' => Product::TYPE_AREA_MEMBROS, 'name' => 'Curso A']);
        $productB = $this->createTestProduct(['type' => Product::TYPE_AREA_MEMBROS, 'name' => 'Curso B']);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
            'email' => 'aluno@test.com',
            'password' => Hash::make('secret123'),
        ]);
        $productA->users()->attach($aluno->id);

        $response = $this->actingAs($owner)->postJson('/produtos/alunos', [
            'name' => 'Aluno Atualizado',
            'email' => 'Aluno@Test.com',
            'password_mode' => 'manual',
            'product_ids' => [$productB->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('linked_existing', true);
        $this->assertTrue($productB->users()->where('users.id', $aluno->id)->exists());
        $this->assertSame('Aluno Atualizado', $aluno->fresh()->name);
    }
}
