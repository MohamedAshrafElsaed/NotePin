<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTextNoteRequest;
use App\Jobs\ProcessRecordingWithAI;
use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use App\Services\EventTracker;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TextNoteController extends Controller
{
    /**
     * Store a text note and auto-start AI processing.
     * Accepts either direct text or a text file (.txt/.md).
     */
    public function store(StoreTextNoteRequest $request): JsonResponse
    {
        Log::info('Text note creation started', [
            'has_text' => filled($request->input('text')),
            'has_file' => $request->hasFile('text_file'),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $text = null;
            $inputMethod = 'paste';

            // Priority: direct text over file
            if (filled($request->input('text'))) {
                $text = $request->input('text');
                $inputMethod = 'paste';
            } elseif ($request->hasFile('text_file')) {
                $file = $request->file('text_file');
                $content = file_get_contents($file->getRealPath());
                $text = $this->normalizeText($content);
                $inputMethod = 'file';

                if (empty($text)) {
                    return response()->json([
                        'error' => 'File is empty',
                        'errors' => ['text_file' => [__('textInput.validation.fileEmpty')]],
                    ], 422);
                }

                // Validate length after reading file
                if (mb_strlen($text) < 10) {
                    return response()->json([
                        'error' => 'File content too short',
                        'errors' => ['text_file' => [__('textInput.validation.textMin')]],
                    ], 422);
                }

                if (mb_strlen($text) > 20000) {
                    return response()->json([
                        'error' => 'File content too long',
                        'errors' => ['text_file' => [__('textInput.validation.textMax')]],
                    ], 422);
                }
            }

            if (empty($text)) {
                return response()->json([
                    'error' => 'No text provided',
                    'errors' => ['text' => [__('textInput.validation.required')]],
                ], 422);
            }

            Log::info('Text note validation passed', [
                'input_method' => $inputMethod,
                'text_length' => mb_strlen($text),
            ]);

            $userId = auth()->id();
            $anonymousId = null;

            if (!$userId) {
                $anonymousId = $request->input('anonymous_id')
                    ?: AnonymousUserResolver::resolve($request);
            }

            // Create with processing status - auto-start AI processing
            $recording = Recording::create([
                'user_id' => $userId,
                'anonymous_id' => $anonymousId,
                'status' => 'processing',
                'transcript' => $text,
                'ai_meta' => [
                    'source' => 'text',
                    'input_method' => $inputMethod,
                ],
            ]);

            Log::info('Text note created', ['id' => $recording->id]);

            EventTracker::track('recording_created', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'input_type' => 'text',
                    'input_method' => $inputMethod,
                    'text_length' => mb_strlen($text),
                    'anonymous' => !$userId,
                ],
            ]);

            // Auto-dispatch AI processing job
            EventTracker::track('ai_processing_started', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'auto_started' => true,
                    'input_type' => 'text',
                    'input_method' => $inputMethod,
                ],
            ]);

            ProcessRecordingWithAI::dispatch($recording->id);

            Log::info('AI processing job dispatched for text note', ['recording_id' => $recording->id]);

            $response = response()->json([
                'id' => $recording->id,
                'status' => $recording->status,
            ]);

            if ($anonymousId) {
                $response->withCookie(AnonymousUserResolver::makeCookie($anonymousId));
            }

            return $response;

        } catch (Exception $e) {
            Log::error('Text note creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to create note',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normalize text content: UTF-8, trim whitespace, normalize line endings.
     */
    private function normalizeText(string $content): string
    {
        // Ensure UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }

        // Normalize line endings to \n
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Trim excessive whitespace
        $content = preg_replace('/[ \t]+$/m', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }
}
