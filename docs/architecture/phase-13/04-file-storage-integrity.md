# 04 — File Storage & Data Integrity (Phase 13D)

Audit penyimpanan berkas, privilege download, integritas referensial, dan
strategi backup/restore.

## Audit

| Area | Temuan / Status |
|---|---|
| Payment proof | private `local` disk; `url:null`; download via `GET /payments/{id}/proof` (policy view) |
| Refund proof | private disk; **+ endpoint baru** `GET /refunds/{refund}/proof` (policy view, `nosniff`) |
| Attachment/media equipment | public `storage`; nama acak `FileSecurity::generateSecurePath` (random 20-byte hex) |
| MIME/extension/size | seluruh upload via `FileSecurity` (pdf/png/jpeg/webp, max 5 MB) + `throttle:file-upload` |
| Random filename | dijamin acak + tidak ada path traversal (path dari `generateSecurePath`) |
| Orphan files | **tidak dihapus otomatis**; command `storage:audit-orphans` melaporkan: attachment tanpa file & file privat (>1 hari) tanpa baris attachment |
| Financial hard delete | `invoices` SoftDeletes; `forceDelete` diblokir FK RESTRICT payments/refunds; tanpa endpoint delete finansial |
| FK / unique / status consistency | FK RESTRICT utk finansial; unique `(rental_detail_id, report_date)` timesheet; state machine guards |
| Backup/restore | `db:backup` (mysqldump → private disk) + dokumentasi restore di bawah |

## Integration Download (baru)

```
GET /api/v1/refunds/{refund}/proof   (owner/admin, policy view; private stream)
```

## Test — `FileStorageIntegrityTest` (5 kasus, 24 assertions)

- Payment proof: private upload (`url:null`), owner & admin download OK, stranger 403.
- Refund proof: private + authorized download; invalid/php file saat complete ditolak.
- `storage:audit-orphans`: melaporkan MISSING + ORPHAN tanpa menghapus.
- Unique timesheet didukung DB (duplikat → QueryException).
- Financial history: soft delete menyimpan; force delete diblokir FK RESTRICT.

## Backup / Restore (ops)

- **DB**: `php artisan db:backup` (mysqldump, no-tablespaces) → `storage/app/backups/rafa-{db}-{ts}.sql`.
  Restore: `mysql -h <HOST> -P <PORT> -u <USER> -p <DB> < backup.sql`
- **Berkas privat**: `storage/app/` (payments/signatures/refunds/backups) → salin via rsync ke lokasi terenkripsi.
- **Berkas publik**: `storage/app/public/` → deploy `php artisan storage:link` setelah restore.
- **Sinkronisasi**: restore DB + restore storage dalam window yang sama agar FK attachment konsisten.
- **Ritme**: harian `db:backup` + snapshot storage; verifikasi restore berkala.

## Files

- `app/Console/Commands/{DatabaseBackupCommand,AuditOrphanFilesCommand}.php`
- `app/Http/Controllers/Api/V1/RefundController.php` (+proof)
- `routes/api.php` (`GET /refunds/{refund}/proof`)
- `tests/Feature/Storage/FileStorageIntegrityTest.php`