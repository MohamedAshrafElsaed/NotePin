<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\EventTracker;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessRecordingWithAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $recordingId
    ) {
    }

    public function handle(): void
    {
        $recording = Recording::find($this->recordingId);

        if (!$recording) {
            Log::warning('ProcessRecordingWithAI: Recording not found', ['id' => $this->recordingId]);
            return;
        }

        // Idempotency check - skip if already processed or not in processable state
        if ($recording->status === 'ready') {
            Log::info('ProcessRecordingWithAI: Already processed, skipping', ['id' => $this->recordingId]);
            return;
        }

        // Ensure we're in a processable state
        if (!in_array($recording->status, ['processing', 'uploaded', 'failed'])) {
            Log::warning('ProcessRecordingWithAI: Invalid status for processing', [
                'id' => $this->recordingId,
                'status' => $recording->status,
            ]);
            return;
        }

        // Mark as processing if not already
        if ($recording->status !== 'processing') {
            $recording->update(['status' => 'processing']);
        }

        try {
            // Check if we need to transcribe (audio recording) or already have text
            $transcript = $recording->transcript;

            if (!$transcript && $recording->audio_path) {
                // Audio recording - needs transcription
                $transcript = $this->transcribeAudio($recording->audio_path);
                $recording->transcript = $transcript;
                $recording->save();
            }

            if (!$transcript) {
                throw new Exception('No transcript or audio available for processing');
            }

            $structured = $this->generateStructuredOutput($transcript);

            $inputType = $recording->audio_path ? 'audio' : 'text';

            $recording->ai_title = $structured['title'] ?? 'Untitled Recording';
            $recording->ai_summary = $structured['summary'] ?? '';
            $recording->ai_action_items = array_slice($structured['action_items'] ?? [], 0, 10);
            $recording->ai_meta = [
                'input_type' => $inputType,
                'transcription_model' => $inputType === 'audio' ? 'gpt-4o-transcribe' : null,
                'chat_model' => 'gpt-5.2',
                'processed_at' => now()->toISOString(),
            ];
            $recording->status = 'ready';
            $recording->save();

            EventTracker::track('ai_ready', [
                'recording_id' => $recording->id,
                'user_id' => $recording->user_id,
                'metadata' => [
                    'input_type' => $inputType,
                    'action_items_count' => count($recording->ai_action_items ?? []),
                ],
            ]);

            Log::info('ProcessRecordingWithAI: Completed successfully', [
                'id' => $this->recordingId,
                'input_type' => $inputType,
            ]);

        } catch (Exception $e) {
            Log::error('ProcessRecordingWithAI: Failed', [
                'id' => $this->recordingId,
                'error' => $e->getMessage(),
            ]);

            $recording->status = 'failed';
            $recording->ai_meta = [
                'error' => 'Processing failed. Please try again.',
                'error_details' => app()->environment('local') ? $e->getMessage() : null,
                'failed_at' => now()->toISOString(),
            ];
            $recording->save();

            EventTracker::track('ai_failed', [
                'recording_id' => $recording->id,
                'user_id' => $recording->user_id,
            ]);
        }
    }

    private function transcribeAudio(string $audioPath): string
    {
        $filePath = Storage::disk('public')->path($audioPath);

        $response = OpenAI::audio()->transcribe([
            'model' => 'gpt-4o-transcribe',
            'file' => fopen($filePath, 'r'),
        ]);

        return $response->text;
    }

    /**
     * @throws Exception
     */
    private function generateStructuredOutput(string $transcript): array
    {
        $systemPrompt = <<<PROMPT
            You are NotePin, an AI assistant that converts spoken audio transcripts into clean, structured notes.
            Core rules:
            - Preserve meaning. Do not invent facts, numbers, names, or decisions.
            - If something is unclear in the transcript, keep it vague or mark it as "غير واضح" without guessing.
            - Prefer Arabic for output if the transcript is Arabic or mixed Arabic/English used by Egyptian speakers.
            - Normalize Arabic text: fix spelling, punctuation, and readability while keeping Egyptian tone natural and professional.
            - Return ONLY valid JSON. No markdown, no extra keys, no commentary.
        PROMPT;

        $userPrompt = <<<PROMPT
            Input: a raw transcript from a voice note or meeting. The transcript may be:
            - Egyptian Arabic (عامية مصرية)
            - Arabic mixed with English terms (e.g., "deadline", "deploy", "meeting")
            - English
            - Must detect the Language and response based on it
            - Noisy, with fillers and repeated words
            Task:
            Convert the transcript into a clean, structured note with corrected language and clear formatting.
            Language rules:
            - If the transcript is mostly Arabic (including Egyptian Arabic), output Arabic.
            - Keep common English technical terms as-is when they are standard (e.g., API, deploy, production, CRM), but write the rest in Arabic.
            - Use a natural Egyptian business tone: clear, practical, not overly formal.
            Output constraints:
            Return STRICT JSON ONLY with exactly this shape:
            {
              "title": "string",
              "summary": "string",
              "action_items": ["string"]
            }
            Content rules:
            - Title: short and specific (max 8 words).
            - Summary: 4–7 short lines, easy to scan. Fix grammar/spelling/punctuation. Remove filler words. Do NOT add new information.
            - Action items: max 10 items, each starts with a verb, specific and executable.
            - If no action items exist, return an empty array [].
            - If key details are missing (who/when/what), keep the action item generic and append "(غير واضح)".
            - Do NOT include timestamps.
            Transcript:
            {$transcript}
        PROMPT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-5.2',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.3,
        ]);

        $content = $response->choices[0]->message->content ?? '{}';
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            throw new Exception('Invalid JSON response from AI');
        }

        return [
            'title' => is_string($parsed['title'] ?? null) ? $parsed['title'] : 'Untitled',
            'summary' => is_string($parsed['summary'] ?? null) ? $parsed['summary'] : '',
            'action_items' => is_array($parsed['action_items'] ?? null) ? $parsed['action_items'] : [],
        ];
    }
}
