<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_halaman_masuk_berhasil_ditampilkan(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
