<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FeedbackCensor;
use App\Support\FeedbackSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VillageFeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackCensor $censor,
    ) {}

    public function index(Request $request, string $village): JsonResponse
    {
        $villageRecord = $this->village($village);
        abort_unless(FeedbackSettings::enabled((int) $villageRecord->id), 404, 'Fitur Kritik & Saran tidak aktif.');

        $perPage = min(max($request->integer('per_page', 9), 1), 30);
        $entries = DB::table('feedback_entries')
            ->where('village_id', $villageRecord->id)
            ->where('moderation_status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'items' => collect($entries->items())->map(fn (object $entry): array => [
                    'id' => (int) $entry->id,
                    'name' => $entry->name,
                    'rating' => (int) $entry->rating,
                    'message' => $entry->message_censored,
                    'published_at' => $entry->published_at,
                ])->all(),
                'summary' => [
                    'total' => $entries->total(),
                    'average_rating' => round((float) DB::table('feedback_entries')
                        ->where('village_id', $villageRecord->id)
                        ->where('moderation_status', 'published')
                        ->avg('rating'), 1),
                ],
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'total' => $entries->total(),
                    'per_page' => $entries->perPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request, string $village): JsonResponse
    {
        $villageRecord = $this->village($village);
        abort_unless(FeedbackSettings::enabled((int) $villageRecord->id), 404, 'Fitur Kritik & Saran tidak aktif.');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'whatsapp' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-]{8,18}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'website' => ['prohibited'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid.',
            'website.prohibited' => 'Permintaan tidak valid.',
        ]);

        $message = trim(strip_tags($data['message']));
        DB::table('feedback_entries')->insert([
            'village_id' => $villageRecord->id,
            'name' => $this->censor->censor(Str::squish(strip_tags($data['name']))),
            'whatsapp' => preg_replace('/[\s\-]/', '', $data['whatsapp']),
            'email' => Str::lower(trim($data['email'])),
            'rating' => (int) $data['rating'],
            'message_original' => $message,
            'message_censored' => $this->censor->censor($message),
            'moderation_status' => 'pending',
            'submitter_hash' => hash('sha256', implode('|', [
                (string) $request->ip(),
                (string) $request->userAgent(),
                (string) config('app.key'),
            ])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Terima kasih. Kritik & saran Anda menunggu moderasi sebelum ditampilkan.',
        ], 201);
    }

    private function village(string $village): object
    {
        $record = DB::table('villages')
            ->where(fn ($query) => $query
                ->where('id', ctype_digit($village) ? (int) $village : 0)
                ->orWhere('slug', $village))
            ->first(['id', 'slug']);

        abort_unless($record, 404, 'Desa tidak ditemukan.');

        return $record;
    }
}
