<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bank\CreateBankAccountAction;
use App\Actions\Bank\UpdateBankAccountAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Bank\StoreBankAccountRequest;
use App\Http\Requests\Bank\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends ApiController
{
    /**
     * List bank accounts.
     * Users only see active ones. Admins/Owners see all.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = BankAccount::query();

        // If USER role (not Admin/Owner), they can only see active accounts
        if (! $user->can('manage-bank-accounts')) {
            $query->where('is_active', true);
        }

        $accounts = $query->latest()->get();

        return $this->success(BankAccountResource::collection($accounts), 'Daftar rekening bank perusahaan berhasil dimuat.');
    }

    /**
     * Show a specific bank account.
     */
    public function show(Request $request, BankAccount $bankAccount): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-bank-accounts') && ! $bankAccount->is_active) {
            return $this->notFound('Rekening tidak ditemukan atau sedang tidak aktif.');
        }

        return $this->success(new BankAccountResource($bankAccount), 'Detail rekening berhasil dimuat.');
    }

    /**
     * Store new bank account.
     */
    public function store(StoreBankAccountRequest $request, CreateBankAccountAction $action): JsonResponse
    {
        $account = $action->execute($request->validated());

        return $this->created(new BankAccountResource($account), 'Rekening bank perusahaan baru berhasil didaftarkan.');
    }

    /**
     * Update an existing bank account.
     */
    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount, UpdateBankAccountAction $action): JsonResponse
    {
        $updated = $action->execute($bankAccount, $request->validated());

        return $this->success(new BankAccountResource($updated), 'Data rekening bank perusahaan berhasil diperbarui.');
    }
}
