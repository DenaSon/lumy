<?php

namespace App\ContentIntelligence;

use App\Models\Content;
use App\Models\ContentAnnotation;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

final class ContentAnnotationWriter
{
    /**
     * @param  array<string, mixed>  $annotation
     * @param  array<int, int|string>  $topicIds
     * @param  array<int, array<string, mixed>>  $hooks
     */
    public function save(
        Content $content,
        array $annotation,
        array $topicIds,
        ?int $primaryTopicId,
        array $hooks,
    ): void {
        DB::transaction(function () use ($content, $annotation, $topicIds, $primaryTopicId, $hooks) {
            ContentAnnotation::query()->updateOrCreate(
                ['content_id' => $content->id],
                [
                    'primary_pillar_id' => $this->nullableInteger($annotation['primary_pillar_id'] ?? null),
                    'goal' => $this->nullableString($annotation['goal'] ?? null),
                    'cta_type' => $this->nullableString($annotation['cta_type'] ?? null),
                    'target_audience' => $this->nullableString($annotation['target_audience'] ?? null),
                    'production_style' => $this->nullableString($annotation['production_style'] ?? null),
                    'cover_style' => $this->nullableString($annotation['cover_style'] ?? null),
                    'notes' => $this->nullableString($annotation['notes'] ?? null),
                    'annotated_at' => now(),
                ],
            );

            $normalizedTopicIds = Topic::query()
                ->whereIn('id', array_values(array_unique(array_map('intval', $topicIds))))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $primaryTopicId = in_array($primaryTopicId, $normalizedTopicIds, true)
                ? $primaryTopicId
                : null;

            $topicSync = [];

            foreach ($normalizedTopicIds as $topicId) {
                $topicSync[$topicId] = [
                    'is_primary' => $topicId === $primaryTopicId,
                ];
            }

            $content->topics()->sync($topicSync);
            $this->syncHooks($content, $hooks);
        });

        $content->unsetRelation('annotation');
        $content->unsetRelation('topics');
        $content->unsetRelation('hooks');
    }

    /** @param array<int, array<string, mixed>> $hooks */
    public function saveHooks(Content $content, array $hooks): void
    {
        DB::transaction(fn () => $this->syncHooks($content, $hooks));

        $content->unsetRelation('hooks');
    }

    /**
     * @param  array<int, array<string, mixed>>  $hooks
     */
    private function syncHooks(Content $content, array $hooks): void
    {
        $existingHooks = $content->hooks()->get()->keyBy('id');
        $keptIds = [];
        $position = 0;
        $hasPrimary = false;

        foreach ($hooks as $row) {
            $text = trim((string) ($row['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $id = $this->nullableInteger($row['id'] ?? null);
            $hook = $id !== null ? $existingHooks->get($id) : null;
            $hook ??= $content->hooks()->make();

            $requestedPrimary = (bool) ($row['is_primary'] ?? false);
            $isPrimary = $requestedPrimary && ! $hasPrimary;
            $hasPrimary = $hasPrimary || $isPrimary;

            $hook->fill([
                'text' => $text,
                'type' => $this->nullableString($row['type'] ?? null),
                'source' => $this->nullableString($row['source'] ?? null),
                'position' => $position,
                'is_primary' => $isPrimary,
                'notes' => $this->nullableString($row['notes'] ?? null),
            ]);
            $hook->save();

            $keptIds[] = $hook->id;
            $position++;
        }

        $deleteQuery = $content->hooks();

        if ($keptIds !== []) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteQuery->delete();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
