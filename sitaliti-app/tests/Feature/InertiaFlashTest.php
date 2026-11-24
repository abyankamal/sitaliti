<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaFlashTest extends TestCase
{
    public function test_flash_props_are_null_when_session_has_no_flash_messages(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', null)
            ->where('flash.error', null)
            ->where('flash.warning', null)
            ->where('flash.info', null)
            ->where('flash.status', null)
        );
    }

    public function test_flash_props_include_unique_uuid_and_message_when_present(): void
    {
        $response = $this->withSession([
            'success' => 'Data berhasil disimpan.',
            'error' => 'Terjadi kesalahan.',
            'warning' => 'Peringatan pagu.',
            'info' => 'Export dimulai.',
            'status' => 'Tautan reset terkirim.',
        ])->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('flash.success.message', 'Data berhasil disimpan.')
            ->where('flash.success.id', fn ($id) => is_string($id) && Str::isUuid($id))
            ->where('flash.error.message', 'Terjadi kesalahan.')
            ->where('flash.error.id', fn ($id) => is_string($id) && Str::isUuid($id))
            ->where('flash.warning.message', 'Peringatan pagu.')
            ->where('flash.warning.id', fn ($id) => is_string($id) && Str::isUuid($id))
            ->where('flash.info.message', 'Export dimulai.')
            ->where('flash.info.id', fn ($id) => is_string($id) && Str::isUuid($id))
            ->where('flash.status.message', 'Tautan reset terkirim.')
            ->where('flash.status.id', fn ($id) => is_string($id) && Str::isUuid($id))
        );
    }
}
