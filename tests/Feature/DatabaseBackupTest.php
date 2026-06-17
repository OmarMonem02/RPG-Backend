<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\History;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
  use RefreshDatabase;

  private static ?string $sqlitePath = null;

  private User $admin;

  protected function beforeRefreshingDatabase(): void
  {
    if (self::$sqlitePath === null) {
      self::$sqlitePath = tempnam(sys_get_temp_dir(), 'rpg-backup-test-');
    }

    config([
      'database.default' => 'sqlite',
      'database.connections.sqlite.database' => self::$sqlitePath,
    ]);
  }

  protected function setUp(): void
  {
    parent::setUp();

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
  }

  public static function tearDownAfterClass(): void
  {
    if (self::$sqlitePath !== null && is_file(self::$sqlitePath)) {
      @unlink(self::$sqlitePath);
    }

    self::$sqlitePath = null;

    parent::tearDownAfterClass();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
  }

  public function test_admin_can_export_backup_with_manifest(): void
  {
    Brand::create([
      'name' => 'Backup Brand',
      'types' => ['products'],
    ]);

    $response = $this->actingAs($this->admin)->get('/api/backup/export');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/gzip');

    $sql = gzdecode($response->getContent());

    $this->assertNotFalse($sql);
    $this->assertStringContainsString('-- RPG Backup Manifest', $sql);
    $this->assertStringContainsString('-- driver: sqlite', $sql);
    $this->assertStringContainsString('Backup Brand', $sql);
  }

  public function test_staff_cannot_export_backup(): void
  {
    $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

    $this->actingAs($staff)
      ->get('/api/backup/export')
      ->assertForbidden();
  }

  public function test_preview_returns_manifest_and_compatibility(): void
  {
    Brand::create([
      'name' => 'Preview Brand',
      'types' => ['products'],
    ]);

    $export = app(DatabaseBackupService::class)->export();
    $file = UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']);

    $response = $this->actingAs($this->admin)->post('/api/backup/preview', [
      'file' => $file,
    ]);

    $response->assertOk()
      ->assertJsonPath('compatible', true)
      ->assertJsonPath('manifest.driver', 'sqlite')
      ->assertJsonStructure(['tables', 'insert_statements', 'warnings']);
  }

  public function test_merge_restore_inserts_missing_rows_and_skips_duplicates(): void
  {
    Brand::create([
      'name' => 'Merge Brand',
      'types' => ['products'],
    ]);

    $export = app(DatabaseBackupService::class)->export();
    $file = UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']);

    Brand::query()->forceDelete();

    $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => $file,
      'mode' => 'merge',
    ])->assertOk();

    $this->assertDatabaseHas('brands', ['name' => 'Merge Brand']);

    $duplicateImport = $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => $file,
      'mode' => 'merge',
    ]);

    $duplicateImport->assertOk();
    $this->assertSame(1, Brand::query()->where('name', 'Merge Brand')->count());
  }

  public function test_upsert_restore_updates_existing_rows(): void
  {
    $brand = Brand::create([
      'name' => 'Upsert Brand',
      'types' => ['products'],
    ]);

    $export = app(DatabaseBackupService::class)->export();

    $brand->update(['name' => 'Changed Brand']);

    $file = UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']);

    $response = $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => $file,
      'mode' => 'upsert',
    ])->assertOk();

    $this->assertDatabaseHas('brands', ['name' => 'Upsert Brand']);
    $this->assertDatabaseMissing('brands', ['name' => 'Changed Brand']);
  }

  public function test_replace_restore_requires_confirmation(): void
  {
    Brand::create([
      'name' => 'Replace Brand',
      'types' => ['products'],
    ]);

    $export = app(DatabaseBackupService::class)->export();

    Brand::query()->forceDelete();

    $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']),
      'mode' => 'replace',
    ])->assertStatus(422)
      ->assertJsonPath('message', 'Replace mode requires confirmation value REPLACE.');

    $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']),
      'mode' => 'replace',
      'confirmation' => 'REPLACE',
    ])->assertOk();

    $this->assertDatabaseHas('brands', ['name' => 'Replace Brand']);
  }

  public function test_preview_rejects_backup_with_wrong_driver(): void
  {
    $sql = "-- RPG Backup Manifest\n-- version: 1\n-- driver: mysql\n-- tables: brands\n\nINSERT INTO brands (name) VALUES ('X');";
    $file = UploadedFile::fake()->createWithContent('backup.sql', $sql);

    $response = $this->actingAs($this->admin)->post('/api/backup/preview', [
      'file' => $file,
    ]);

    $response->assertOk()
      ->assertJsonPath('compatible', false);

    $this->assertNotEmpty($response->json('warnings'));
  }

  public function test_import_logs_history_entry(): void
  {
    Brand::create([
      'name' => 'History Brand',
      'types' => ['products'],
    ]);

    $export = app(DatabaseBackupService::class)->export();
    $file = UploadedFile::fake()->createWithContent('backup.sql.gz', $export['content']);

    Brand::query()->forceDelete();
    History::query()->delete();

    $this->actingAs($this->admin)->post('/api/backup/import', [
      'file' => $file,
      'mode' => 'merge',
    ])->assertOk();

    $this->assertDatabaseHas('histories', [
      'user_id' => $this->admin->id,
      'model_type' => 'system_backup',
      'action' => 'restore',
    ]);
  }
}
