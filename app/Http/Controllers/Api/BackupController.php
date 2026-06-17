<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BackupController extends Controller
{
  public function __construct(
    private readonly DatabaseBackupService $backupService,
  ) {}

  public function export(Request $request): Response|JsonResponse
  {
    $this->ensureAdmin($request);
    set_time_limit(0);

    try {
      $export = $this->backupService->export();
    } catch (\Throwable $exception) {
      return response()->json([
        'message' => $exception->getMessage(),
      ], 422);
    }

    return response($export['content'], Response::HTTP_OK, [
      'Content-Type' => 'application/gzip',
      'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
      'Cache-Control' => 'no-store',
    ]);
  }

  public function preview(Request $request): JsonResponse
  {
    $this->ensureAdmin($request);
    set_time_limit(0);

    $maxKb = (int) config('backup.max_upload_mb', 512) * 1024;

    $request->validate([
      'file' => ['required', 'file', 'max:' . $maxKb, function (string $attribute, mixed $value, \Closure $fail): void {
        $name = strtolower($value->getClientOriginalName());

        if (! str_ends_with($name, '.sql') && ! str_ends_with($name, '.sql.gz') && ! str_ends_with($name, '.gz')) {
          $fail('The backup file must be a .sql or .sql.gz file.');
        }
      }],
    ]);

    try {
      $preview = $this->backupService->preview($request->file('file'));
    } catch (\Throwable $exception) {
      return response()->json([
        'message' => $exception->getMessage(),
      ], 422);
    }

    return response()->json($preview);
  }

  public function import(Request $request): JsonResponse
  {
    $this->ensureAdmin($request);
    set_time_limit(0);

    $maxKb = (int) config('backup.max_upload_mb', 512) * 1024;

    $request->validate([
      'file' => ['required', 'file', 'max:' . $maxKb, function (string $attribute, mixed $value, \Closure $fail): void {
        $name = strtolower($value->getClientOriginalName());

        if (! str_ends_with($name, '.sql') && ! str_ends_with($name, '.sql.gz') && ! str_ends_with($name, '.gz')) {
          $fail('The backup file must be a .sql or .sql.gz file.');
        }
      }],
      'mode' => 'required|in:merge,upsert,replace',
      'confirmation' => 'nullable|string|max:32',
    ]);

    try {
      $result = $this->backupService->import(
        $request->file('file'),
        (string) $request->input('mode'),
        $request->user(),
        $request->input('confirmation'),
      );
    } catch (\Throwable $exception) {
      return response()->json([
        'message' => $exception->getMessage(),
      ], 422);
    }

    return response()->json($result);
  }

  private function ensureAdmin(Request $request): void
  {
    if ($request->user()?->role !== User::ROLE_ADMIN) {
      abort(403, 'You are not authorized to access this resource.');
    }
  }
}
