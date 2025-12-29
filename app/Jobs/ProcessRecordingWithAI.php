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

        // Idempotency check - skip if already processed
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
            $transcript = $recording->transcript;
            $inputType = 'text';

            if (!$transcript && $recording->audio_path) {
                $inputType = 'audio';
                $transcript = $this->transcribeAudio($recording->audio_path);
                $recording->transcript = $transcript;
                $recording->save();
            }

            if (!$transcript) {
                throw new Exception('No transcript or audio available for processing');
            }

            $structured = $this->generateDecisionFocusedOutput($transcript, $inputType);

            // Validate and map to existing columns
            $this->validateStructuredOutput($structured);

            // Map action_items to simple strings for display, store full objects in meta
            $actionItemsDisplay = array_slice(
                array_map(fn($item) => $item['task'], $structured['action_items']),
                0,
                8
            );

            $recording->ai_title = $structured['title'];
            $recording->ai_summary = $structured['summary'];
            $recording->ai_action_items = $actionItemsDisplay;
            $recording->ai_meta = [
                'language' => $structured['meta']['language'],
                'source' => $structured['meta']['source'],
                'decision_context' => $structured['meta']['decision_context'],
                'action_items_full' => $structured['action_items'],
                'transcription_model' => $inputType === 'audio' ? 'gpt-4o-transcribe' : null,
                'chat_model' => 'gpt-4o',
                'processed_at' => now()->toISOString(),
            ];
            $recording->status = 'ready';
            $recording->save();

            EventTracker::track('ai_ready', [
                'recording_id' => $recording->id,
                'user_id' => $recording->user_id,
                'metadata' => [
                    'input_type' => $inputType,
                    'language' => $structured['meta']['language'],
                    'action_items_count' => count($structured['action_items']),
                ],
            ]);

            Log::info('ProcessRecordingWithAI: Completed successfully', [
                'id' => $this->recordingId,
                'input_type' => $inputType,
                'language' => $structured['meta']['language'],
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
    private function generateDecisionFocusedOutput(string $transcript, string $inputType): array
    {
        $systemPrompt = <<<'PROMPT'
You are NotePin, an AI that extracts WORK DECISIONS and ACTION ITEMS from voice notes and text.

CRITICAL RULES:
1. Focus on DECISIONS made, not general notes or journaling.
2. Extract concrete, actionable tasks with owners/dates when mentioned.
3. DO NOT invent information - if unclear, use null and confidence="low".
4. Arabic-first: if input is Arabic/Egyptian dialect, output in Arabic (keep technical terms in English).
5. Summary must be MAX 4 lines, focused on key decisions.
6. Return ONLY valid JSON - no markdown, no extra text.

OUTPUT SCHEMA (STRICT):
{
  "title": "string (max 8 words, decision-focused)",
  "summary": "string (max 4 lines, key decisions only)",
  "action_items": [
    {
      "task": "string (starts with verb, specific action)",
      "due_date": "YYYY-MM-DD or null",
      "owner": "string or null",
      "project": "string or null",
      "confidence": "low|medium|high"
    }
  ],
  "meta": {
    "language": "ar|en",
    "source": "audio|text",
    "decision_context": "string or null (what triggered these decisions)"
  }
}

CONFIDENCE RULES:
- high: task, owner, and date are all clearly stated
- medium: task is clear, but owner or date is inferred
- low: task is vague or details are missing

ARABIC OUTPUT RULES:
- Use Egyptian business Arabic (natural, not formal MSA)
- Keep English tech terms: API, deploy, sprint, deadline, CRM, etc.
- Fix spelling/grammar but preserve meaning
PROMPT;

        $userPrompt = <<<PROMPT
Extract decisions and action items from this transcript.
Source type: {$inputType}

TRANSCRIPT:
{$transcript}

Return STRICT JSON only. No markdown code blocks.
PROMPT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';
        $content = trim($content);

        // Clean markdown if present (fallback)
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON from AI: ' . json_last_error_msg());
        }

        if (!is_array($parsed)) {
            throw new Exception('AI response is not a valid object');
        }

        return $parsed;
    }

    /**
     * @throws Exception
     */
    private function validateStructuredOutput(array $data): void
    {
        // Validate required top-level fields
        if (!isset($data['title']) || !is_string($data['title'])) {
            throw new Exception('Missing or invalid title field');
        }

        if (!isset($data['summary']) || !is_string($data['summary'])) {
            throw new Exception('Missing or invalid summary field');
        }

        if (!isset($data['action_items']) || !is_array($data['action_items'])) {
            throw new Exception('Missing or invalid action_items field');
        }

        if (!isset($data['meta']) || !is_array($data['meta'])) {
            throw new Exception('Missing or invalid meta field');
        }

        // Validate meta fields
        $meta = $data['meta'];
        if (!isset($meta['language']) || !in_array($meta['language'], ['ar', 'en'])) {
            throw new Exception('Invalid meta.language field');
        }

        if (!isset($meta['source']) || !in_array($meta['source'], ['audio', 'text'])) {
            throw new Exception('Invalid meta.source field');
        }

        // Validate action items structure
        foreach ($data['action_items'] as $index => $item) {
            if (!is_array($item)) {
                throw new Exception("Action item {$index} is not an object");
            }

            if (!isset($item['task']) || !is_string($item['task'])) {
                throw new Exception("Action item {$index} missing task field");
            }

            if (!isset($item['confidence']) || !in_array($item['confidence'], ['low', 'medium', 'high'])) {
                throw new Exception("Action item {$index} has invalid confidence field");
            }

            // Validate date format if present
            if (isset($item['due_date']) && $item['due_date'] !== null) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['due_date'])) {
                    throw new Exception("Action item {$index} has invalid due_date format");
                }
            }
        }

        // Validate summary length (max 4 lines ~= 400 chars)
        if (strlen($data['summary']) > 1000) {
            Log::warning('ProcessRecordingWithAI: Summary exceeds recommended length', [
                'length' => strlen($data['summary']),
            ]);
        }
    }
}
