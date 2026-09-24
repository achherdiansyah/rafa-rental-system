<?php

namespace Tests\Unit\Support;

use App\Support\FileSecurity;
use Tests\TestCase;

class FileSecurityTest extends TestCase
{
    public function test_validation_rules_returns_expected_structure(): void
    {
        $rules = FileSecurity::validationRules(required: true);

        $this->assertContains('required', $rules);
        $this->assertContains('file', $rules);
        $this->assertContains('max:'.FileSecurity::MAX_FILE_SIZE_KB, $rules);
    }

    public function test_validation_rules_nullable_when_not_required(): void
    {
        $rules = FileSecurity::validationRules(required: false);

        $this->assertContains('nullable', $rules);
        $this->assertNotContains('required', $rules);
    }

    public function test_generate_secure_path_has_expected_format(): void
    {
        $path = FileSecurity::generateSecurePath('payments', 'pdf');

        $this->assertStringStartsWith('payments/', $path);
        $this->assertStringEndsWith('.pdf', $path);

        // Path should have date folder: payments/YYYY/MM/hash.pdf
        $parts = explode('/', $path);
        $this->assertCount(4, $parts);
    }

    public function test_two_secure_paths_are_unique(): void
    {
        $a = FileSecurity::generateSecurePath('identity', 'jpg');
        $b = FileSecurity::generateSecurePath('identity', 'jpg');

        $this->assertNotEquals($a, $b);
    }

    public function test_mime_type_constants_are_set(): void
    {
        $this->assertContains('application/pdf', FileSecurity::ALLOWED_MIME_TYPES);
        $this->assertContains('image/jpeg', FileSecurity::ALLOWED_MIME_TYPES);
    }
}
