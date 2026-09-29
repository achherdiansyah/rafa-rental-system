<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\CmsSetting;
use App\Models\User;
use App\Support\FileSecurity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Landing-page CMS.
 * - Public: read-only published settings (drives the public landing page).
 * - Admin: create/update/disable settings + upload/replace/delete media.
 * Owner & USER may not mutate CMS.
 */
class CmsController extends ApiController
{
    /**
     * Public view — returns all active settings for the landing page.
     * Media keys are resolved to absolute public URLs.
     */
    public function publicShow(): JsonResponse
    {
        $settings = CmsSetting::where('is_active', true)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (CmsSetting $s) => [$s->key => $s->publicValue()]);

        return $this->success($settings, 'Konten landing page berhasil dimuat.');
    }

    /**
     * Admin list (including disabled).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Hanya Admin yang dapat mengelola konten landing page.');
        }

        $list = CmsSetting::with('attachments')->orderBy('key')->get();

        return $this->success($list->map(fn (CmsSetting $s) => [
            'key' => $s->key,
            'value' => $s->value,
            'url' => $s->publicValue(),
            'is_media' => $s->isMedia(),
            'is_active' => $s->is_active,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ]), 'Konfigurasi CMS berhasil dimuat.');
    }

    /**
     * Admin upsert a setting value (plain text).
     */
    public function update(Request $request, string $key): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Hanya Admin yang dapat mengelola konten landing page.');
        }

        $data = $request->validate([
            'value' => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $setting = CmsSetting::updateOrCreate(
            ['key' => $key],
            array_merge($data, ['updated_by' => $user->id])
        );

        return $this->success($setting->fresh()->only(['key', 'value', 'is_active']), 'Konten landing page berhasil disimpan.');
    }

    /**
     * Admin upload / replace media for a media slot (logo, favicon, hero).
     */
    public function uploadMedia(Request $request, string $key): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Hanya Admin yang dapat mengelola media landing page.');
        }

        if (! in_array($key, CmsSetting::MEDIA_KEYS, true)) {
            return $this->error('Kunci media tidak dikenal.', [], 422, 'VALIDATION_FAILED');
        }

        $request->validate(['media' => FileSecurity::validationRules(true)]);

        return DB::transaction(function () use ($request, $user, $key) {
            $setting = CmsSetting::firstOrCreate(['key' => $key], ['updated_by' => $user->id]);

            $file = $request->file('media');
            $extension = strtolower($file->getClientOriginalExtension());
            $path = FileSecurity::generateSecurePath('cms', $extension);

            $file->storeAs('', $path, 'public');
            $setting->value = $path;
            $setting->updated_by = $user->id;
            $setting->save();

            $setting->attachments()->create([
                'document_type' => 'CMS_MEDIA',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            return $this->success([
                'key' => $key,
                'url' => $setting->fresh()->publicValue(),
            ], 'Media landing page berhasil diunggah.');
        });
    }

    /**
     * Admin delete/disable a setting (soft disable).
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Hanya Admin yang dapat mengelola konten landing page.');
        }

        /** @var CmsSetting|null $setting */
        $setting = CmsSetting::where('key', $key)->first();
        if (! $setting) {
            return $this->notFound('Kunci konten tidak ditemukan.');
        }

        $setting->update(['is_active' => false, 'updated_by' => $user->id]);

        return $this->success(null, 'Konten landing page dinonaktifkan.');
    }
}
