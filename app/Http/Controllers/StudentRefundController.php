<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\RefundRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentRefundController extends Controller
{
    public function __construct(
        private RefundRequestService $refundRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $product = Product::query()->findOrFail((string) $validated['product_id']);

        if (! $product->hasMemberAreaAccess($user)) {
            abort(403, 'Você não tem acesso a este curso.');
        }

        try {
            $this->refundRequestService->create($user, $product, trim($validated['reason']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Solicitação de reembolso enviada. Aguarde a análise.');
    }
}
