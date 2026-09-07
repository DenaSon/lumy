<?php

use App\ContentIntelligence\ContentAnnotationWriter;
use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.panel')] class extends Component
{
    public Content $content;

    public ?int $primaryPillarId = null;

    public string $goal = '';

    public string $ctaType = '';

    public string $targetAudience = '';

    public string $productionStyle = '';

    public string $coverStyle = '';

    public string $notes = '';

    /** @var array<int, int|string> */
    public array $selectedTopicIds = [];

    public ?int $primaryTopicId = null;

    /** @var array<int, array<string, mixed>> */
    public array $hooks = [];

    public ?int $primaryHookIndex = null;

    public string $savedMessage = '';

    public function mount(Content $content): void
    {
        $this->content = $content;
        $this->loadForm();
    }

    public function addHook(): void
    {
        $this->hooks[] = $this->blankHook();
    }

    public function removeHook(int $index): void
    {
        if (! array_key_exists($index, $this->hooks)) {
            return;
        }

        array_splice($this->hooks, $index, 1);

        if ($this->primaryHookIndex === $index) {
            $this->primaryHookIndex = null;
        } elseif ($this->primaryHookIndex !== null && $this->primaryHookIndex > $index) {
            $this->primaryHookIndex--;
        }

        if ($this->hooks === []) {
            $this->hooks[] = $this->blankHook();
        }
    }

    public function setPrimaryHook(int $index): void
    {
        $this->primaryHookIndex = array_key_exists($index, $this->hooks) ? $index : null;
    }

    public function updatedSelectedTopicIds(): void
    {
        $selected = array_map('intval', $this->selectedTopicIds);

        if ($this->primaryTopicId !== null && ! in_array($this->primaryTopicId, $selected, true)) {
            $this->primaryTopicId = null;
        }
    }

    public function save(ContentAnnotationWriter $writer): void
    {
        $this->persist($writer);
        $this->savedMessage = 'Annotation ذخیره شد.';
    }

    public function saveAndNext(ContentAnnotationWriter $writer)
    {
        $this->persist($writer);
        $next = $this->olderContent;

        if ($next === null) {
            $this->savedMessage = 'Annotation ذخیره شد؛ محتوای بعدی وجود ندارد.';

            return null;
        }

        return $this->redirectRoute('content.annotate', ['content' => $next->id], navigate: true);
    }

    #[Computed]
    public function pillars(): Collection
    {
        $selectedTopicIds = array_values(array_unique(array_map('intval', $this->selectedTopicIds)));
        $visiblePillarIds = $selectedTopicIds === []
            ? []
            : Topic::query()->whereIn('id', $selectedTopicIds)->pluck('content_pillar_id')->map(fn ($id) => (int) $id)->all();

        if ($this->primaryPillarId !== null) {
            $visiblePillarIds[] = $this->primaryPillarId;
        }

        $visiblePillarIds = array_values(array_unique($visiblePillarIds));

        return ContentPillar::query()
            ->with(['topics' => function ($query) use ($selectedTopicIds) {
                $query->where(function ($query) use ($selectedTopicIds) {
                    $query->where('is_active', true);

                    if ($selectedTopicIds !== []) {
                        $query->orWhereIn('id', $selectedTopicIds);
                    }
                });
            }])
            ->where(function ($query) use ($visiblePillarIds) {
                $query->where('is_active', true);

                if ($visiblePillarIds !== []) {
                    $query->orWhereIn('id', $visiblePillarIds);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedTopics(): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedTopicIds)));

        return Topic::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function olderContent(): ?Content
    {
        return $this->neighborContent(older: true);
    }

    #[Computed]
    public function newerContent(): ?Content
    {
        return $this->neighborContent(older: false);
    }

    public function formatCount(?int $value): string
    {
        return $value === null ? '—' : number_format($value);
    }

    public function formatRate(?float $value): string
    {
        return $value === null ? '—' : number_format($value * 100, 1).'%';
    }

    public function formatProviderPercent(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value)
            ? '—'
            : number_format((float) $value, 1).'%';
    }

    /** @return array<string, string> */
    public function hookTypes(): array
    {
        return [
            'question' => 'Question',
            'curiosity' => 'Curiosity',
            'problem' => 'Problem',
            'how_to' => 'How-to',
            'warning' => 'Warning',
            'contrarian' => 'Contrarian',
            'list' => 'List',
            'result' => 'Result',
            'news' => 'News',
            'story' => 'Story',
            'statement' => 'Statement',
        ];
    }

    /** @return array<string, string> */
    public function hookSources(): array
    {
        return [
            'cover' => 'Cover',
            'video_overlay' => 'Video overlay',
            'spoken' => 'Spoken',
            'carousel_first_slide' => 'Carousel first slide',
            'caption' => 'Caption',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public function goals(): array
    {
        return [
            'awareness' => 'Awareness',
            'education' => 'Education',
            'community' => 'Community',
            'growth' => 'Growth',
            'conversion' => 'Conversion',
            'experiment' => 'Experiment',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public function ctaTypes(): array
    {
        return [
            'none' => 'None',
            'comment' => 'Comment',
            'save' => 'Save',
            'share' => 'Share',
            'follow' => 'Follow',
            'link' => 'Link',
            'dm' => 'DM',
            'other' => 'Other',
        ];
    }

    private function persist(ContentAnnotationWriter $writer): void
    {
        $hookTypes = array_keys($this->hookTypes());
        $hookSources = array_keys($this->hookSources());
        $goals = array_keys($this->goals());
        $ctaTypes = array_keys($this->ctaTypes());

        $validated = $this->validate([
            'primaryPillarId' => ['nullable', 'integer', 'exists:content_pillars,id'],
            'goal' => ['nullable', 'string', 'max:64', Rule::in($goals)],
            'ctaType' => ['nullable', 'string', 'max:64', Rule::in($ctaTypes)],
            'targetAudience' => ['nullable', 'string', 'max:128'],
            'productionStyle' => ['nullable', 'string', 'max:128'],
            'coverStyle' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'selectedTopicIds' => ['array'],
            'selectedTopicIds.*' => ['integer', 'exists:topics,id'],
            'primaryTopicId' => ['nullable', 'integer', 'exists:topics,id'],
            'primaryHookIndex' => ['nullable', 'integer', 'min:0'],
            'hooks' => ['array', 'max:20'],
            'hooks.*.id' => [
                'nullable',
                'integer',
                Rule::exists('content_hooks', 'id')->where(fn ($query) => $query->where('content_id', $this->content->id)),
            ],
            'hooks.*.text' => ['nullable', 'string', 'max:2000'],
            'hooks.*.type' => ['nullable', 'string', 'max:64', Rule::in($hookTypes)],
            'hooks.*.source' => ['nullable', 'string', 'max:64', Rule::in($hookSources)],
            'hooks.*.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $hookRows = collect($validated['hooks'])
            ->map(function (array $row, int $index) {
                $row['is_primary'] = $this->primaryHookIndex === $index;

                return $row;
            })
            ->all();

        $writer->save(
            $this->content,
            [
                'primary_pillar_id' => $validated['primaryPillarId'],
                'goal' => $validated['goal'],
                'cta_type' => $validated['ctaType'],
                'target_audience' => $validated['targetAudience'],
                'production_style' => $validated['productionStyle'],
                'cover_style' => $validated['coverStyle'],
                'notes' => $validated['notes'],
            ],
            $validated['selectedTopicIds'],
            $validated['primaryTopicId'],
            $hookRows,
        );

        $this->loadForm();
    }

    private function loadForm(): void
    {
        $this->content->load([
            'annotation',
            'topics',
            'hooks',
            'coverMedia',
            'latestMetricSnapshot',
            'socialAccount:id,username',
        ]);

        $annotation = $this->content->annotation;
        $this->primaryPillarId = $annotation?->primary_pillar_id;
        $this->goal = $annotation?->goal ?? '';
        $this->ctaType = $annotation?->cta_type ?? '';
        $this->targetAudience = $annotation?->target_audience ?? '';
        $this->productionStyle = $annotation?->production_style ?? '';
        $this->coverStyle = $annotation?->cover_style ?? '';
        $this->notes = $annotation?->notes ?? '';

        $this->selectedTopicIds = $this->content->topics
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->primaryTopicId = $this->content->topics
            ->first(fn (Topic $topic) => (bool) $topic->pivot->is_primary)
            ?->id;

        $this->hooks = $this->content->hooks
            ->map(fn ($hook) => [
                'id' => $hook->id,
                'text' => $hook->text,
                'type' => $hook->type ?? '',
                'source' => $hook->source ?? '',
                'notes' => $hook->notes ?? '',
            ])
            ->values()
            ->all();

        $this->primaryHookIndex = null;

        foreach ($this->content->hooks->values() as $index => $hook) {
            if ($hook->is_primary) {
                $this->primaryHookIndex = $index;
                break;
            }
        }

        if ($this->hooks === []) {
            $this->hooks[] = $this->blankHook();
        }
    }

    /** @return array<string, mixed> */
    private function blankHook(): array
    {
        return [
            'id' => null,
            'text' => '',
            'type' => '',
            'source' => '',
            'notes' => '',
        ];
    }

    private function neighborContent(bool $older): ?Content
    {
        $query = Content::query()
            ->where('social_account_id', $this->content->social_account_id);

        if ($this->content->published_at === null) {
            return $older
                ? $query->where('id', '<', $this->content->id)->orderByDesc('id')->first()
                : $query->where('id', '>', $this->content->id)->orderBy('id')->first();
        }

        $publishedAt = $this->content->published_at;
        $contentId = $this->content->id;

        if ($older) {
            return $query
                ->where(function ($query) use ($publishedAt, $contentId) {
                    $query
                        ->where('published_at', '<', $publishedAt)
                        ->orWhere(function ($query) use ($publishedAt, $contentId) {
                            $query->where('published_at', $publishedAt)->where('id', '<', $contentId);
                        });
                })
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();
        }

        return $query
            ->where(function ($query) use ($publishedAt, $contentId) {
                $query
                    ->where('published_at', '>', $publishedAt)
                    ->orWhere(function ($query) use ($publishedAt, $contentId) {
                        $query->where('published_at', $publishedAt)->where('id', '>', $contentId);
                    });
            })
            ->orderBy('published_at')
            ->orderBy('id')
            ->first();
    }
};
?>

@php($snapshot = $content->latestMetricSnapshot)
@php($derived = $snapshot?->derivedMetrics())
@php($cover = $content->coverMedia)
@php($thumbnail = $cover?->thumbnail_url ?: $cover?->remote_url)
@php($isVideo = $cover && $cover->remote_url && (str_contains(strtolower((string) $cover->type), 'video') || $content->content_type === 'reel'))

<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a wire:navigate href="{{ route('content.index') }}" class="link link-primary text-sm no-underline">← بازگشت به کاتالوگ</a>
            <p class="mt-3 text-sm font-semibold text-primary">Manual Content Intelligence</p>
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">Annotation محتوا</h1>
            <p class="mt-2 max-w-3xl text-sm text-base-content/60">
                Hook، موضوع، هدف و DNA محتوایی را دستی ثبت کن تا داده‌ی عملکرد به Content Intelligence تبدیل شود.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($this->newerContent)
                <a wire:navigate href="{{ route('content.annotate', $this->newerContent) }}" class="btn btn-ghost btn-sm">
                    <x-icon name="o-arrow-right" class="size-4" />
                    جدیدتر
                </a>
            @endif
            @if($this->olderContent)
                <a wire:navigate href="{{ route('content.annotate', $this->olderContent) }}" class="btn btn-ghost btn-sm">
                    قدیمی‌تر
                    <x-icon name="o-arrow-left" class="size-4" />
                </a>
            @endif
        </div>
    </header>

    @if($savedMessage !== '')
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="size-5" />
            <span>{{ $savedMessage }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error shadow-sm">
            <x-icon name="o-exclamation-triangle" class="size-5" />
            <div>
                <div class="font-bold">ذخیره انجام نشد.</div>
                <div class="text-sm">فیلدهای مشخص‌شده را بررسی کن.</div>
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-12">
        <aside class="space-y-4 xl:col-span-4">
            <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm xl:sticky xl:top-6">
                <div class="aspect-[4/5] bg-base-200">
                    @if($isVideo)
                        <video
                            src="{{ $cover->remote_url }}"
                            @if($cover->thumbnail_url) poster="{{ $cover->thumbnail_url }}" @endif
                            controls
                            preload="metadata"
                            class="size-full object-contain"
                        ></video>
                    @elseif($thumbnail)
                        <img src="{{ $thumbnail }}" alt="" class="size-full object-contain" loading="lazy">
                    @else
                        <div class="flex size-full items-center justify-center">
                            <x-icon name="o-photo" class="size-10 text-base-content/30" />
                        </div>
                    @endif
                </div>

                <div class="space-y-4 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-outline">{{ ucfirst($content->content_type ?: 'unknown') }}</span>
                        <span class="badge {{ $content->analytics_status === 'available' ? 'badge-success' : 'badge-warning' }}">
                            {{ $content->analytics_status }}
                        </span>
                        @if($content->socialAccount?->username)
                            <span class="text-xs text-base-content/50">@{{ $content->socialAccount->username }}</span>
                        @endif
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-base-content/45">Caption</div>
                        <p class="mt-1 whitespace-pre-line text-sm leading-7">
                            {{ $content->caption ?: 'بدون کپشن' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">Views</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatCount($snapshot?->views) }}</div>
                        </div>
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">Reach</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatCount($snapshot?->reach) }}</div>
                        </div>
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">High Intent</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatRate($derived?->highIntentRate) }}</div>
                        </div>
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">Retention</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatRate($derived?->retentionRate) }}</div>
                        </div>
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">Saves</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatCount($snapshot?->saves) }}</div>
                        </div>
                        <div class="rounded-xl bg-base-200 p-3">
                            <div class="text-xs text-base-content/45">Skip Rate</div>
                            <div class="mt-1 font-mono font-bold">{{ $this->formatProviderPercent($snapshot?->skip_rate) }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs text-base-content/50">
                        <span>{{ $content->published_at?->utc()->format('Y-m-d H:i') ?? 'بدون تاریخ' }}</span>
                        <span>•</span>
                        <span>{{ $content->platform_post_id }}</span>
                    </div>

                    @if($content->permalink)
                        <a href="{{ $content->permalink }}" target="_blank" rel="noreferrer" class="btn btn-outline btn-sm w-full">
                            مشاهده در Instagram
                            <x-icon name="o-arrow-top-right-on-square" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </aside>

        <main class="space-y-6 xl:col-span-8">
            <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-black">Content DNA</h2>
                    <p class="mt-1 text-sm text-base-content/55">طبقه‌بندی اصلی و هدف این محتوا.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Primary Pillar</span>
                        <select wire:model="primaryPillarId" class="select select-bordered w-full @error('primaryPillarId') select-error @enderror">
                            <option value="">بدون Pillar</option>
                            @foreach($this->pillars as $pillar)
                                <option value="{{ $pillar->id }}">{{ $pillar->name }}{{ $pillar->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                        @error('primaryPillarId') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Goal</span>
                        <select wire:model="goal" class="select select-bordered w-full @error('goal') select-error @enderror">
                            <option value="">بدون Goal</option>
                            @foreach($this->goals() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('goal') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">CTA Type</span>
                        <select wire:model="ctaType" class="select select-bordered w-full @error('ctaType') select-error @enderror">
                            <option value="">بدون CTA</option>
                            @foreach($this->ctaTypes() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('ctaType') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Target Audience</span>
                        <input wire:model="targetAudience" type="text" maxlength="128" class="input input-bordered w-full @error('targetAudience') input-error @enderror" placeholder="مثلاً Linux beginners">
                        @error('targetAudience') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Production Style</span>
                        <input wire:model="productionStyle" type="text" maxlength="128" class="input input-bordered w-full @error('productionStyle') input-error @enderror" placeholder="مثلاً screen recording">
                        @error('productionStyle') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Cover Style</span>
                        <input wire:model="coverStyle" type="text" maxlength="128" class="input input-bordered w-full @error('coverStyle') input-error @enderror" placeholder="مثلاً terminal screenshot">
                        @error('coverStyle') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="form-control mt-4">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Notes</span>
                    <textarea wire:model="notes" rows="4" class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror" placeholder="نکات تحلیلی یا زمینه‌ای این محتوا"></textarea>
                    @error('notes') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                </label>
            </section>

            <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-black">Topics</h2>
                        <p class="mt-1 text-sm text-base-content/55">یک محتوا می‌تواند چند Topic داشته باشد و یکی از آن‌ها Primary باشد.</p>
                    </div>
                    <span class="badge badge-outline">{{ count($selectedTopicIds) }} انتخاب</span>
                </div>

                @if($this->pillars->isEmpty())
                    <div class="rounded-xl border border-dashed border-base-300 bg-base-200/50 p-5 text-sm text-base-content/55">
                        هنوز Pillar/Topic رسمی ساخته نشده است. سایر Annotationها و Hookها را می‌توانی بدون Taxonomy ذخیره کنی.
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($this->pillars as $pillar)
                            <div class="rounded-xl border border-base-300 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="font-bold">{{ $pillar->name }}</div>
                                    @unless($pillar->is_active)
                                        <span class="badge badge-ghost badge-sm">inactive</span>
                                    @endunless
                                </div>

                                @if($pillar->topics->isEmpty())
                                    <div class="text-xs text-base-content/45">Topic فعالی ندارد.</div>
                                @else
                                    <div class="space-y-2">
                                        @foreach($pillar->topics as $topic)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-base-200">
                                                <input wire:model.live="selectedTopicIds" type="checkbox" value="{{ $topic->id }}" class="checkbox checkbox-primary checkbox-sm">
                                                <span class="flex-1 text-sm">{{ $topic->name }}</span>
                                                @unless($topic->is_active)
                                                    <span class="badge badge-ghost badge-xs">inactive</span>
                                                @endunless
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <label class="form-control mt-4">
                        <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Primary Topic</span>
                        <select wire:model="primaryTopicId" class="select select-bordered w-full @error('primaryTopicId') select-error @enderror" @disabled($this->selectedTopics->isEmpty())>
                            <option value="">بدون Primary Topic</option>
                            @foreach($this->selectedTopics as $topic)
                                <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                            @endforeach
                        </select>
                        @error('primaryTopicId') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                    </label>
                @endif
            </section>

            <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">Hooks</h2>
                        <p class="mt-1 text-sm text-base-content/55">متن Hook را از خود ویدیو، تصویر، اسلاید اول، گفتار یا Caption ثبت کن.</p>
                    </div>
                    <button wire:click="addHook" type="button" class="btn btn-outline btn-sm">
                        <x-icon name="o-plus" class="size-4" />
                        Hook جدید
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach($hooks as $index => $hook)
                        <div wire:key="hook-form-{{ $hook['id'] ?? 'new' }}-{{ $index }}" class="rounded-xl border {{ $primaryHookIndex === $index ? 'border-primary bg-primary/5' : 'border-base-300' }} p-4">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-outline">Hook {{ $index + 1 }}</span>
                                    @if($primaryHookIndex === $index)
                                        <span class="badge badge-primary badge-sm">Primary</span>
                                    @endif
                                </div>

                                <div class="flex gap-1">
                                    <button wire:click="setPrimaryHook({{ $index }})" type="button" class="btn btn-ghost btn-xs">
                                        Primary
                                    </button>
                                    <button wire:click="removeHook({{ $index }})" type="button" class="btn btn-ghost btn-xs text-error">
                                        حذف
                                    </button>
                                </div>
                            </div>

                            <label class="form-control">
                                <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Hook Text</span>
                                <textarea wire:model="hooks.{{ $index }}.text" rows="3" class="textarea textarea-bordered w-full @error('hooks.'.$index.'.text') textarea-error @enderror" placeholder="متن دقیق Hook"></textarea>
                                @error('hooks.'.$index.'.text') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                            </label>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="form-control">
                                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Hook Type</span>
                                    <select wire:model="hooks.{{ $index }}.type" class="select select-bordered w-full @error('hooks.'.$index.'.type') select-error @enderror">
                                        <option value="">بدون Type</option>
                                        @foreach($this->hookTypes() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('hooks.'.$index.'.type') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="form-control">
                                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Source</span>
                                    <select wire:model="hooks.{{ $index }}.source" class="select select-bordered w-full @error('hooks.'.$index.'.source') select-error @enderror">
                                        <option value="">بدون Source</option>
                                        @foreach($this->hookSources() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('hooks.'.$index.'.source') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                                </label>
                            </div>

                            <label class="form-control mt-3">
                                <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Hook Notes</span>
                                <input wire:model="hooks.{{ $index }}.notes" type="text" class="input input-bordered w-full @error('hooks.'.$index.'.notes') input-error @enderror" placeholder="توضیح اختیاری">
                                @error('hooks.'.$index.'.notes') <span class="mt-1 text-xs text-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100/95 p-4 shadow-lg backdrop-blur">
                <div class="text-xs text-base-content/50">
                    ذخیره، `annotated_at` را به زمان فعلی به‌روزرسانی می‌کند.
                </div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="save" wire:loading.attr="disabled" type="button" class="btn btn-outline">
                        ذخیره
                    </button>
                    <button wire:click="saveAndNext" wire:loading.attr="disabled" type="button" class="btn btn-primary">
                        Save & Next
                        <x-icon name="o-arrow-left" class="size-4" />
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>
