<?php

namespace App\Console\Commands;

use App\Models\MemberCommunityPage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\StudentAreaSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateStudentAreaGlobalConfig extends Command
{
    protected $signature = 'student-area:migrate-global-config {--tenant= : ID do tenant (infoprodutor)}';

    protected $description = 'Migra comunidade, certificado e gamificação de produtos para settings globais da área do aluno';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');
        $tenantIds = $tenantFilter !== null && $tenantFilter !== ''
            ? [(int) $tenantFilter]
            : User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_INFOPRODUTOR])
                ->whereNotNull('tenant_id')
                ->pluck('tenant_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

        if ($tenantIds === []) {
            $tenantIds = Product::query()
                ->where('type', Product::TYPE_AREA_MEMBROS)
                ->whereNotNull('tenant_id')
                ->distinct()
                ->pluck('tenant_id')
                ->all();
        }

        foreach ($tenantIds as $tenantId) {
            $this->migrateTenant((int) $tenantId);
        }

        $this->info('Migração concluída.');

        return self::SUCCESS;
    }

    private function migrateTenant(int $tenantId): void
    {
        $this->line("Tenant {$tenantId}...");

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->orderBy('name')
            ->get();

        if (Setting::get('student_community_enabled', null, $tenantId) === null) {
            $enabled = false;
            $canDelete = true;
            foreach ($products as $product) {
                $cfg = $product->member_area_config ?? [];
                if (! empty($cfg['community_enabled'])) {
                    $enabled = true;
                }
                if (array_key_exists('community_users_can_delete_own_posts', $cfg)) {
                    $canDelete = (bool) $cfg['community_users_can_delete_own_posts'];
                }
            }
            StudentAreaSettings::setCommunityEnabled($enabled, $tenantId);
            StudentAreaSettings::setCommunityUsersCanDeleteOwnPosts($canDelete, $tenantId);
        }

        if (Setting::get('student_certificate', null, $tenantId) === null) {
            foreach ($products as $product) {
                $cert = ($product->member_area_config ?? [])['certificate'] ?? [];
                if (is_array($cert) && (! empty($cert['enabled']) || ! empty($cert['title']))) {
                    StudentAreaSettings::setCertificateConfig($cert, $tenantId);
                    break;
                }
            }
        }

        if (Setting::get('student_gamification', null, $tenantId) === null) {
            foreach ($products as $product) {
                $g = ($product->member_area_config ?? [])['gamification'] ?? [];
                if (is_array($g) && (! empty($g['enabled']) || ! empty($g['achievements']))) {
                    StudentAreaSettings::setGamificationConfig($g, $tenantId);
                    break;
                }
            }
        }

        $this->consolidateCommunityPages($tenantId, $products);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function consolidateCommunityPages(int $tenantId, $products): void
    {
        $seenSlugs = [];
        foreach ($products as $product) {
            $pages = MemberCommunityPage::query()
                ->where('product_id', $product->id)
                ->orderBy('position')
                ->get();

            foreach ($pages as $page) {
                $slug = $page->slug ?: \Illuminate\Support\Str::slug($page->title);
                $baseSlug = $slug;
                $n = 1;
                while (isset($seenSlugs[$slug])) {
                    $slug = $baseSlug.'-'.$n;
                    $n++;
                }
                $seenSlugs[$slug] = true;

                DB::table('member_community_pages')
                    ->where('id', $page->id)
                    ->update([
                        'tenant_id' => $tenantId,
                        'slug' => $slug,
                        'product_id' => null,
                    ]);
            }
        }
    }
}
