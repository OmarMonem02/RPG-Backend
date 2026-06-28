<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineDocument;
use Illuminate\Support\Collection;

class MachineDocumentService
{
    public function __construct(private readonly DocumentUploadService $documentUploadService)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public function attachDocuments(Machine $machine, array $documents): void
    {
        foreach ($documents as $document) {
            $mimeType = (string) ($document['mime_type'] ?? '');

            $machine->documents()->create([
                'type' => $document['type'],
                'url' => (string) $document['url'],
                'public_id' => $document['public_id'],
                'filename' => $document['filename'],
                'mime_type' => $mimeType,
                'uploaded_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<int>  $documentIds
     */
    public function removeDocuments(Machine $machine, array $documentIds): void
    {
        if ($documentIds === []) {
            return;
        }

        $documents = $machine->documents()->whereIn('id', $documentIds)->get();
        $this->deleteFromCloudinary($documents);
        $machine->documents()->whereIn('id', $documentIds)->delete();
    }

    public function deleteAllDocuments(Machine $machine): void
    {
        $documents = $machine->documents()->get();
        $this->deleteFromCloudinary($documents);
        $machine->documents()->delete();
    }

    /**
     * @param  Collection<int, MachineDocument>  $documents
     */
    private function deleteFromCloudinary(Collection $documents): void
    {
        foreach ($documents as $document) {
            $this->documentUploadService->delete($document->public_id);
        }
    }
}
