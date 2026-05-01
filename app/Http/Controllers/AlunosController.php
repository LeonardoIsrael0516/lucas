<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Events\StudentAccessDeliveryReady;
use App\Services\AccessEmailService;
use App\Services\MemberProgressService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AlunosController extends Controller
{
    private const FILTER_OPTIONS = ['todos', 'novos_30'];

    private function tenantProductIds(?int $tenantId): array
    {
        if (auth()->user()?->isTeam()) {
            return app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
        }

        return Product::forTenant($tenantId)->pluck('id')->toArray();
    }

    private function baseAlunosQuery(?int $tenantId)
    {
        return User::where('role', User::ROLE_ALUNO)
            ->whereHas('products', fn ($q) => $q->forTenant($tenantId));
    }

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $filter = $request->query('filter', 'todos');
        if (! in_array($filter, self::FILTER_OPTIONS, true)) {
            $filter = 'todos';
        }

        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : '';
        $search = $search !== '' ? $search : null;

        $productIdsFilter = $request->query('product_ids');
        $productIdsFilter = is_array($productIdsFilter)
            ? $productIdsFilter
            : (is_string($productIdsFilter) ? array_filter(explode(',', $productIdsFilter)) : []);

        $tenantProductIds = $this->tenantProductIds($tenantId);
        $baseAlunosQuery = $this->baseAlunosQuery($tenantId);

        if ($filter === 'novos_30') {
            $baseAlunosQuery->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('product_user')
                    ->join('products', 'products.id', '=', 'product_user.product_id')
                    ->whereColumn('product_user.user_id', 'users.id')
                    ->where('product_user.created_at', '>=', now()->subDays(30));
                if ($tenantId === null) {
                    $q->whereNull('products.tenant_id');
                } else {
                    $q->where('products.tenant_id', $tenantId);
                }
            });
        }

        if (! empty($productIdsFilter)) {
            $validProductIds = array_intersect($productIdsFilter, $tenantProductIds);
            if (! empty($validProductIds)) {
                $baseAlunosQuery->whereHas('products', fn ($q) => $q->whereIn('products.id', $validProductIds));
            }
        }

        if ($search !== null) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $baseAlunosQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like);
            });
        }

        $alunos = (clone $baseAlunosQuery)
            ->with(['products' => fn ($q) => $q->forTenant($tenantId)->select('products.id', 'products.name')])
            ->withCount(['products as products_count' => function ($q) use ($tenantId) {
                if ($tenantId === null) {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', $tenantId);
                }
            }])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'products_count' => $u->products_count,
                'products' => $u->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            ]);

        $produtos = Product::forTenant($tenantId)->withCount('users')->orderBy('name')->get();

        $totalAlunos = User::where('role', User::ROLE_ALUNO)
            ->whereHas('products', fn ($q) => $q->forTenant($tenantId))
            ->count();

        $totalInscricoes = empty($tenantProductIds)
            ? 0
            : DB::table('product_user')->whereIn('product_id', $tenantProductIds)->count();

        $produtosAtivos = Product::forTenant($tenantId)->whereHas('users')->count();

        $alunosNovos30dias = User::where('role', User::ROLE_ALUNO)
            ->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('product_user')
                    ->join('products', 'products.id', '=', 'product_user.product_id')
                    ->whereColumn('product_user.user_id', 'users.id')
                    ->where('product_user.created_at', '>=', now()->subDays(30));
                if ($tenantId === null) {
                    $q->whereNull('products.tenant_id');
                } else {
                    $q->where('products.tenant_id', $tenantId);
                }
            })
            ->count();

        $stats = [
            'total_alunos' => $totalAlunos,
            'total_inscricoes' => $totalInscricoes,
            'produtos_ativos' => $produtosAtivos,
            'alunos_novos_30dias' => $alunosNovos30dias,
        ];

        return Inertia::render('Alunos/Index', [
            'alunos' => $alunos,
            'produtos' => $produtos,
            'stats' => $stats,
            'filter' => $filter,
            'product_ids_filter' => $productIdsFilter,
            'q' => $search,
        ]);
    }

    public function show(User $aluno, Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if (! $aluno->products()->forTenant($tenantId)->exists()) {
            abort(404);
        }
        $aluno->load(['products' => fn ($q) => $q->forTenant($tenantId)->select('products.id', 'products.name')]);

        $allProducts = Product::forTenant($tenantId)->orderBy('name')->get(['id', 'name', 'type', 'checkout_slug']);
        $accessSet = $aluno->products->pluck('id')->flip()->all();

        $progressService = app(MemberProgressService::class);
        $productsPayload = $allProducts->map(function (Product $p) use ($aluno, $accessSet, $progressService) {
            $hasAccess = isset($accessSet[$p->id]);
            $progress = null;
            if ($hasAccess && $p->type === Product::TYPE_AREA_MEMBROS) {
                try {
                    $progress = $progressService->completionPercent($p, $aluno);
                } catch (\Throwable) {
                    $progress = null;
                }
            }
            return [
                'id' => $p->id,
                'name' => $p->name,
                'has_access' => $hasAccess,
                'progress_percent' => $progress,
            ];
        })->values()->all();

        $lastAccessAt = null;
        try {
            $lastAccessAt = DB::table('member_activity_logs')
                ->where('user_id', $aluno->id)
                ->max('created_at');
        } catch (\Throwable) {
            $lastAccessAt = null;
        }

        $magicLinks = [];
        foreach ($allProducts as $p) {
            if ($p->type !== Product::TYPE_AREA_MEMBROS || ! $p->checkout_slug) {
                continue;
            }
            // Build signed link for member-area magic access (path mode). Host mode will still accept /access on host,
            // but for simplicity we return the path-based URL which works in most installs.
            $magicLinks[$p->id] = URL::signedRoute('member-area.magic-access', [
                'slug' => $p->checkout_slug,
                'u' => $aluno->id,
            ]);
        }

        return response()->json([
            'id' => $aluno->id,
            'name' => $aluno->name,
            'email' => $aluno->email,
            'phone' => $aluno->phone,
            'created_at' => $aluno->created_at?->toIso8601String(),
            'last_access_at' => $lastAccessAt ? (string) $lastAccessAt : null,
            'products' => $productsPayload,
            'magic_links' => $magicLinks,
        ]);
    }

    public function store(Request $request, AccessEmailService $accessEmailService): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password_mode' => ['nullable', 'string', 'in:auto,manual'],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
            'send_access_email' => ['nullable', 'boolean'],
            'send_access_whatsapp' => ['nullable', 'boolean'],
        ]);
        $productIds = $validated['product_ids'] ?? [];
        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = array_values(array_intersect($productIds, $tenantProductIds));
        $sendAccessEmail = (bool) ($validated['send_access_email'] ?? true);
        $sendAccessWhatsapp = (bool) ($validated['send_access_whatsapp'] ?? false);

        $passwordMode = (string) ($validated['password_mode'] ?? 'manual');
        if ($passwordMode === 'auto') {
            $plainPassword = Str::random(12);
        } else {
            $plainPassword = (string) ($validated['password'] ?? '');
            if (strlen($plainPassword) < 6) {
                return response()->json(['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'], 422);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'role' => User::ROLE_ALUNO,
            'tenant_id' => $tenantId,
        ]);
        if (! empty($validated['phone'])) {
            $user->phone = $validated['phone'];
            $user->save();
        }

        foreach ($productIds as $pid) {
            $user->products()->syncWithoutDetaching([$pid]);
        }

        $emailsSent = 0;
        if ($sendAccessEmail && ! empty($productIds)) {
            $products = Product::whereIn('id', $productIds)->get();
            foreach ($products as $product) {
                if ($accessEmailService->sendForUserProduct($user, $product)) {
                    $emailsSent++;
                }
            }
        }

        if ($sendAccessWhatsapp) {
            // AutoZap integration will be handled by manual access event in a later step.
            // For now we only persist the phone and return OK.
        }

        $message = 'Aluno cadastrado com sucesso.';
        if ($sendAccessEmail && $emailsSent > 0) {
            $message .= " E-mail de acesso enviado para {$emailsSent} produto(s).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'aluno' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'products_count' => count($productIds)],
        ]);
    }

    public function update(Request $request, User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if (! $aluno->products()->forTenant($tenantId)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $aluno->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $aluno->name = $validated['name'];
        $aluno->email = $validated['email'];
        if (array_key_exists('phone', $validated)) {
            $aluno->phone = $validated['phone'];
        }
        if (! empty($validated['password'])) {
            $aluno->password = Hash::make($validated['password']);
        }
        $aluno->save();

        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = $validated['product_ids'] ?? [];
        $productIds = array_values(array_intersect($productIds, $tenantProductIds));
        $currentIds = $aluno->products()->forTenant($tenantId)->pluck('products.id')->toArray();
        $aluno->products()->detach($currentIds);
        $aluno->products()->attach($productIds);

        return response()->json([
            'success' => true,
            'message' => 'Aluno atualizado com sucesso.',
            'aluno' => [
                'id' => $aluno->id,
                'name' => $aluno->name,
                'email' => $aluno->email,
                'products_count' => count($productIds),
                'products' => Product::forTenant($tenantId)->whereIn('id', $productIds)->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ],
        ]);
    }

    public function sendAccess(Request $request, User $aluno, AccessEmailService $accessEmailService): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if (! $aluno->products()->forTenant($tenantId)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'string', 'exists:products,id'],
            'channel' => ['required', 'string', 'in:email,whatsapp'],
        ]);

        $product = Product::forTenant($tenantId)->where('id', $validated['product_id'])->firstOrFail();

        if ($validated['channel'] === 'email') {
            $ok = $accessEmailService->sendForUserProduct($aluno, $product);
            return response()->json([
                'success' => (bool) $ok,
                'message' => $ok ? 'Acesso enviado por e-mail.' : 'Não foi possível enviar o e-mail de acesso.',
            ], $ok ? 200 : 422);
        }

        // WhatsApp: trigger AutoZap flows via manual event
        $link = null;
        if ($product->type === Product::TYPE_AREA_MEMBROS && $product->checkout_slug) {
            $link = URL::signedRoute('member-area.magic-access', [
                'slug' => $product->checkout_slug,
                'u' => $aluno->id,
            ]);
        }

        event(new StudentAccessDeliveryReady(
            user: $aluno,
            product: $product,
            access: [
                'type' => 'magic_link',
                'link' => $link,
            ],
        ));

        return response()->json([
            'success' => true,
            'message' => 'Acesso enviado por WhatsApp (AutoZap).',
        ]);
    }

    public function destroy(User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if (! $aluno->products()->forTenant($tenantId)->exists()) {
            abort(404);
        }
        $aluno->products()->detach();
        $aluno->delete();
        return response()->json(['success' => true, 'message' => 'Aluno excluído com sucesso.']);
    }

    public function downloadImportExample(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'alunos_exemplo_' . date('Y-m-d') . '.csv';
        $content = "nome;email;senha\nJoão Silva;joao@exemplo.com;senha123\nMaria Santos;maria@exemplo.com;\nPedro Oliveira;pedro@exemplo.com;minhasenha456";

        return response()->streamDownload(function () use ($content) {
            echo "\xEF\xBB\xBF";
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, AccessEmailService $accessEmailService): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:4096'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
            'send_access_email' => ['nullable', 'boolean'],
            'send_access_whatsapp' => ['nullable', 'boolean'],
        ]);

        $productIds = $request->input('product_ids', []);
        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = array_values(array_intersect((array) $productIds, $tenantProductIds));
        if (empty($productIds)) {
            return response()->json(['success' => false, 'message' => 'Selecione ao menos um produto para dar acesso.'], 422);
        }
        $sendAccessEmail = (bool) ($request->input('send_access_email', true));
        $sendAccessWhatsapp = (bool) ($request->input('send_access_whatsapp', false));

        $file = $request->file('file');
        $rows = $this->readImportRows($file->getRealPath(), (string) $file->getClientOriginalExtension());
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Nenhuma linha válida no arquivo.'], 422);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim($h)), $rows[0]);
        $nameCol = $this->findColumn($header, ['nome', 'name', 'nome_completo']);
        $emailCol = $this->findColumn($header, ['email', 'e-mail', 'mail']);
        $passCol = $this->findColumn($header, ['senha', 'password', 'senha_acesso']);

        $hasHeader = $emailCol !== null || $nameCol !== null || $passCol !== null;
        if ($emailCol === null) {
            if (count($rows[0] ?? []) >= 2 && filter_var(trim($rows[0][1] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $emailCol = 1;
                $nameCol = 0;
                $hasHeader = false;
            } else {
                return response()->json(['success' => false, 'message' => 'Coluna "email" ou "e-mail" não encontrada. Use cabeçalho: nome;email;senha'], 422);
            }
        }

        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
        if (empty($dataRows)) {
            return response()->json(['success' => false, 'message' => 'Nenhum dado para importar.'], 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($dataRows as $idx => $row) {
            $email = isset($emailCol) && isset($row[$emailCol]) ? $row[$emailCol] : ($row[1] ?? $row[0] ?? '');
            $email = trim($email);
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Linha " . ($idx + 2) . ": e-mail inválido ou vazio.";
                $skipped++;
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $errors[] = "Linha " . ($idx + 2) . ": e-mail {$email} já cadastrado.";
                $skipped++;
                continue;
            }

            $name = isset($nameCol) && isset($row[$nameCol]) ? $row[$nameCol] : explode('@', $email)[0];
            $name = trim($name) ?: 'Aluno';
            $password = (isset($passCol) && isset($row[$passCol]) && strlen(trim($row[$passCol] ?? '')) >= 6)
                ? trim($row[$passCol])
                : Str::random(12);

            try {
                $user = User::create([
                    'name' => mb_substr($name, 0, 255),
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_ALUNO,
                    'tenant_id' => $tenantId,
                ]);

                foreach ($productIds as $pid) {
                    $user->products()->syncWithoutDetaching([$pid]);
                }

                if ($sendAccessEmail && ! empty($productIds)) {
                    $products = Product::whereIn('id', $productIds)->get();
                    foreach ($products as $product) {
                        if ($accessEmailService->sendForUserProduct($user, $product)) {
                            $emailsSent++;
                        }
                    }
                }
                if ($sendAccessWhatsapp) {
                    // AutoZap integration is event-driven; handled via manual access event in UI flow.
                    $whatsappsQueued++;
                }
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Linha " . ($idx + 2) . ": " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "{$created} aluno(s) importado(s) com sucesso.";
        if ($skipped > 0) {
            $message .= " {$skipped} linha(s) ignorada(s).";
        }
        if ($sendAccessEmail && $emailsSent > 0) {
            $message .= " E-mail de acesso enviado.";
        }
        if ($sendAccessWhatsapp && $whatsappsQueued > 0) {
            $message .= " WhatsApp marcado para envio.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    private function readImportRows(string $path, string $extension): array
    {
        $ext = mb_strtolower(trim($extension));
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw = $sheet->toArray(null, false, false, false);
            $rows = [];
            foreach ($raw as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $cols = array_map(fn ($v) => is_string($v) ? trim($v) : (is_null($v) ? '' : trim((string) $v)), $r);
                if (! empty(array_filter($cols, fn ($v) => $v !== ''))) {
                    $rows[] = $cols;
                }
            }
            return $rows;
        }

        $content = file_get_contents($path);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $content));
        if (empty($lines)) {
            return [];
        }
        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $this->detectDelimiter($line));
            $cols = array_map('trim', $cols);
            if (! empty(array_filter($cols))) {
                $rows[] = $cols;
            }
        }
        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        return str_contains($line, ';') ? ';' : ',';
    }

    private function findColumn(array $header, array $names): ?int
    {
        foreach ($names as $n) {
            $i = array_search($n, $header, true);
            if ($i !== false) {
                return (int) $i;
            }
        }
        return null;
    }

    public function removeProduct(User $aluno, Product $produto): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }
        $aluno->products()->detach($produto->id);
        $remaining = $aluno->products()->where(fn ($q) => $q->forTenant($tenantId))->count();
        return response()->json([
            'success' => true,
            'message' => 'Acesso ao produto removido.',
            'products_count' => $remaining,
        ]);
    }
}
