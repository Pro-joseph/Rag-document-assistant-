<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,txt,csv', 'max:10240'],
        ]);

        $path = $request->file('file')->store('documents', 'public');

        if (! Str::startsWith($path, 'documents/')) {
            abort(422, 'Invalid file path.');
        }

        return response()->json([
            'status' => 'success',
            'path' => $path,
        ]);
    }
}
