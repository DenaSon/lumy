<?php

use App\ContentIntelligence\ContentAnnotationWriter;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.panel')] class extends Component
{
    #[Url(as: 'content', except: null)]
    public ?int $contentId = null;

    /** @var array<int, array<string, mixed>> */
    public array $hooks = [];

    public ?int $primaryHookIndex = null;

    public string $bulkHooks = '';

    public string $savedMessage = '';

    public bool $hasUnsavedChanges = false;

    /** @var array<int, int> */
    public array $skippedContentIds = [];

    public function mount(): void
    {
        if ($this->contentId === null) {
            $this->contentId = $this->nextMissingContent()?->id;
        }

        $this->loadHooks();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['contentId', 'savedMessage', 'hasUnsavedChanges', 'skippedContentIds'], true)) {
            $this->hasUnsavedChanges = true;
            $this->savedMessage = '';
        }
    }

    #[Computed]
    public function account(): ?SocialAccount
    {
        $configuredAccountId = trim((string) config('zernio.account_id'));

        if ($configuredAccountId !== '') {
            $configured = SocialAccount::query()
                ->where('provider', 'zernio')
                ->where('provider_account_id', $configuredAccountId)
                ->first();

            if ($configured !== null) {
                return $configured;
            }
        }

        return SocialAccount::query()
            ->where('platform', 'instagram')
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function content(): ?Content
    {
        if ($this->contentId === null || $this->account === null) {
            return null;
        }

        return Content::query()
            ->where('social_account_id', $this->account->id)
            ->with(['coverMedia', 'socialAccount:id,username'])
            ->find($this->contentId);
    }

    #[Computed]
    public function queueSummary(): array
    {
        if ($this->account === null) {
            return ['total' => 0, 'completed' => 0, 'remaining' => 0, 'rate' => null];
        }

        $query = Content::query()->where('social_account_id', $this->account->id);
        $total = (clone $query)->count();
        $completed = (clone $query)
            ->whereHas('hooks', fn ($query) => $query->where('is_primary', true))
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'remaining' => max(0, $total - $completed),
            'rate' => $total === 0 ? null : $completed / $total,
        ];
    }

    public function addHook(): void
    {
        if (count($this->hooks) >= 20) {
            return;
        }

        $this->hooks[] = $this->blankHook();
        $this->markUnsaved();
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
            $this->primaryHookIndex = 0;
        }

        $this->markUnsaved();
    }

    public function setPrimaryHook(int $index): void
    {
        if (array_key_exists($index, $this->hooks)) {
            $this->primaryHookIndex = $index;
            $this->markUnsaved();
        }
    }

    public function setHookType(int $index, string $type): void
    {
        if (array_key_exists($index, $this->hooks) && array_key_exists($type, $this->hookTypes())) {
            $this->hooks[$index]['type'] = $type;
            $this->markUnsaved();
        }
    }

    public function setHookSource(int $index, string $source): void
    {
        if (array_key_exists($index, $this->hooks) && array_key_exists($source, $this->hookSources())) {
            $this->hooks[$index]['source'] = $source;
            $this->markUnsaved();
        }
    }

    public function importBulkHooks(): void
    {
        $lines = preg_split('/\R+/u', trim($this->bulkHooks)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));

        if ($lines === []) {
            return;
        }

        if (count($this->hooks) === 1 && trim((string) ($this->hooks[0]['text'] ?? '')) === '') {
            $this->hooks = [];
            $this->primaryHookIndex = null;
        }

        foreach ($lines as $line) {
            if (count($this->hooks) >= 20) {
                break;
            }

            $this->hooks[] = [
                ...$this->blankHook(),
                'text' => $line,
            ];
        }

        if ($this->primaryHookIndex === null && $this->hooks !== []) {
            $this->primaryHookIndex = 0;
        }

        $this->bulkHooks = '';
        $this->markUnsaved();
    }

    public function save(ContentAnnotationWriter $writer): void
    {
        if ($this->content === null) {
            return;
        }

        $this->persist($writer);
        $this->savedMessage = 'Hookها ذخیره شدند.';
    }

    public function saveAndNext(ContentAnnotationWriter $writer): void
    {
        if ($this->content === null) {
            return;
        }

        $savedContentId = $this->content->id;
        $this->persist($writer);
        $this->skippedContentIds = array_values(array_filter(
            $this->skippedContentIds,
            fn (int $id) => $id !== $savedContentId,
        ));

        $next = $this->nextMissingContent();

        if ($next === null) {
            $this->savedMessage = 'Hook ذخیره شد؛ محتوای بدون Primary Hook دیگری باقی نمانده است.';

            return;
        }

        $this->selectContent($next->id);
        $this->savedMessage = 'ذخیره شد؛ محتوای بعدی آماده است.';
    }

    public function skip(): void
    {
        if ($this->content === null) {
            return;
        }

        $this->skippedContentIds[] = $this->content->id;
        $this->skippedContentIds = array_values(array_unique(array_map('intval', $this->skippedContentIds)));
        $next = $this->nextMissingContent();

        if ($next === null) {
            $this->savedMessage = 'در این نوبت محتوای دیگری برای بررسی باقی نمانده است.';

            return;
        }

        $this->selectContent($next->id);
        $this->savedMessage = '';
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

    private function persist(ContentAnnotationWriter $writer): void
    {
        $content = $this->content;

        if ($content === null) {
            return;
        }

        $validated = $this->validate([
            'primaryHookIndex' => ['nullable', 'integer', 'min:0'],
            'hooks' => ['array', 'max:20'],
            'hooks.*.id' => [
                'nullable',
                'integer',
                Rule::exists('content_hooks', 'id')->where(fn ($query) => $query->where('content_id', $content->id)),
            ],
            'hooks.*.text' => ['nullable', 'string', 'max:2000'],
            'hooks.*.type' => ['nullable', 'string', 'max:64', Rule::in(array_keys($this->hookTypes()))],
            'hooks.*.source' => ['nullable', 'string', 'max:64', Rule::in(array_keys($this->hookSources()))],
            'hooks.*.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $firstNonBlank = collect($validated['hooks'])
            ->search(fn (array $row) => trim((string) ($row['text'] ?? '')) !== '');

        if ($firstNonBlank === false) {
            $this->addError('hooks', 'حداقل یک Hook وارد کن.');

            throw \Illuminate\Validation\ValidationException::withMessages([
                'hooks' => 'حداقل یک Hook وارد کن.',
            ]);
        }

        $primaryIndex = $this->primaryHookIndex;

        if ($primaryIndex === null || trim((string) ($validated['hooks'][$primaryIndex]['text'] ?? '')) === '') {
            $primaryIndex = (int) $firstNonBlank;
            $this->primaryHookIndex = $primaryIndex;
        }

        $hookRows = collect($validated['hooks'])
            ->map(function (array $row, int $index) use ($primaryIndex) {
                $row['is_primary'] = $primaryIndex === $index;

                return $row;
            })
            ->all();

        $writer->saveHooks($content, $hookRows);
        unset($this->content, $this->queueSummary);
        $this->loadHooks();
        $this->hasUnsavedChanges = false;
    }

    private function selectContent(int $contentId): void
    {
        $this->contentId = $contentId;
        $this->savedMessage = '';
        $this->hasUnsavedChanges = false;
        unset($this->content, $this->queueSummary);
        $this->loadHooks();
    }

    private function loadHooks(): void
    {
        $content = $this->content;

        if ($content === null) {
            $this->hooks = [];
            $this->primaryHookIndex = null;
            $this->hasUnsavedChanges = false;

            return;
        }

        $existing = $content->hooks()->get();
        $this->hooks = $existing
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

        foreach ($existing->values() as $index => $hook) {
            if ($hook->is_primary) {
                $this->primaryHookIndex = $index;
                break;
            }
        }

        if ($this->hooks === []) {
            $this->hooks[] = $this->blankHook();
            $this->primaryHookIndex = 0;
        } elseif ($this->primaryHookIndex === null) {
            $this->primaryHookIndex = 0;
        }

        $this->hasUnsavedChanges = false;
        $this->resetValidation();
    }

    private function nextMissingContent(): ?Content
    {
        if ($this->account === null) {
            return null;
        }

        return Content::query()
            ->where('social_account_id', $this->account->id)
            ->whereDoesntHave('hooks', fn ($query) => $query->where('is_primary', true))
            ->when($this->skippedContentIds !== [], fn ($query) => $query->whereNotIn('id', $this->skippedContentIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
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

    private function markUnsaved(): void
    {
        $this->hasUnsavedChanges = true;
        $this->savedMessage = '';
    }
};
?>

@php($content = $this->content)
@php($summary = $this->queueSummary)
@php($cover = $content?->coverMedia)
@php($thumbnail = $cover?->thumbnail_url ?: $cover?->remote_url)
@php($isVideo = $cover && $cover->remote_url && (str_contains(strtolower((string) $cover->type), 'video') || $content?->content_type === 'reel'))

<div
    class="space-y-6"
    x-data
    x-on:keydown.window.ctrl.enter.prevent="$wire.saveAndNext()"
    x-on:keydown.window.meta.enter.prevent="$wire.saveAndNext()"
>
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-primary">Quick Hook Workflow</p>
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">ثبت سریع Hook</h1>
            <p class="mt-2 max-w-3xl text-sm text-base-content/60">
                فقط Hook را ثبت کن و مستقیم برو سراغ محتوای بعدی که Primary Hook ندارد.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('content.index') }}" class="btn btn-ghost btn-sm">کاتالوگ محتوا</a>
            @if($content)
                <a wire:navigate href="{{ route('content.annotate', $content) }}" class="btn btn-outline btn-sm">Full Annotation</a>
            @endif
        </div>
    </header>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm font-black">Hook Coverage</div>
                <div class="mt-1 text-xs text-base-content/50">
                    {{ number_format($summary['completed']) }} از {{ number_format($summary['total']) }} محتوا Primary Hook دارند · {{ number_format($summary['remaining']) }} باقی‌مانده
                </div>
            </div>
            <div class="flex items-center gap-3">
                <progress class="progress progress-primary w-40" value="{{ $summary['completed'] }}" max="{{ max(1, $summary['total']) }}"></progress>
                <span class="font-mono text-sm font-bold">{{ $summary['rate'] === null ? '—' : number_format($summary['rate'] * 100, 0).'%' }}</span>
            </div>
        </div>
    </section>

    @if($savedMessage !== '')
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="size-5" />
            <span>{{ $savedMessage }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error shadow-sm">
            <x-icon name="o-exclamation-triangle" class="size-5" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if($this->account === null)
        <x-panel.empty-state
            title="اکانت Instagram پیدا نشد"
            description="بعد از sync شدن Social Account، صف ثبت Hook فعال می‌شود."
        />
    @elseif($content === null)
        <div class="rounded-2xl border border-success/30 bg-success/10 p-10 text-center shadow-sm">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-success/15">
                <x-icon name="o-check-circle" class="size-7 text-success" />
            </div>
            <h2 class="mt-4 text-lg font-black">صف Hook کامل است</h2>
            <p class="mt-2 text-sm text-base-content/60">برای همه‌ی محتواهای این اکانت Primary Hook ثبت شده است.</p>
        </div>
    @else
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
                            <span class="text-xs text-base-content/50">{{ $content->published_at?->utc()->format('Y-m-d') ?? 'بدون تاریخ' }}</span>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-base-content/45">Caption</div>
                            <p class="mt-1 max-h-52 overflow-y-auto whitespace-pre-line text-sm leading-7">
                                {{ $content->caption ?: 'بدون کپشن' }}
                            </p>
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

            <main class="space-y-4 xl:col-span-8">
                <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                    <details>
                        <summary class="cursor-pointer list-none">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-black">Paste چند Hook</div>
                                    <div class="mt-1 text-xs text-base-content/50">هر Hook را در یک خط Paste کن؛ Lumy آن‌ها را به کارت‌های جدا تبدیل می‌کند.</div>
                                </div>
                                <span class="badge badge-outline">Bulk</span>
                            </div>
                        </summary>
                        <div class="mt-4">
                            <textarea wire:model="bulkHooks" rows="4" class="textarea textarea-bordered w-full" placeholder="Hook اول&#10;Hook دوم&#10;Hook سوم"></textarea>
                            <div class="mt-2 flex justify-end">
                                <button wire:click="importBulkHooks" type="button" class="btn btn-outline btn-sm">تبدیل به Hook</button>
                            </div>
                        </div>
                    </details>
                </section>

                <section class="space-y-3">
                    @foreach($hooks as $index => $hook)
                        <article
                            wire:key="quick-hook-{{ $hook['id'] ?? 'new' }}-{{ $index }}"
                            class="rounded-2xl border bg-base-100 p-5 shadow-sm {{ $primaryHookIndex === $index ? 'border-primary ring-1 ring-primary/20' : 'border-base-300' }}"
                        >
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-outline">Hook {{ $index + 1 }}</span>
                                    <button wire:click="setPrimaryHook({{ $index }})" type="button" class="btn btn-xs {{ $primaryHookIndex === $index ? 'btn-primary' : 'btn-ghost' }}">
                                        <x-icon name="{{ $primaryHookIndex === $index ? 's-star' : 'o-star' }}" class="size-4" />
                                        Primary
                                    </button>
                                </div>
                                <button wire:click="removeHook({{ $index }})" type="button" class="btn btn-ghost btn-xs text-error">حذف</button>
                            </div>

                            <textarea
                                wire:model="hooks.{{ $index }}.text"
                                rows="3"
                                autofocus
                                class="textarea textarea-bordered w-full text-base leading-7 @error('hooks.'.$index.'.text') textarea-error @enderror"
                                placeholder="متن دقیق Hook را وارد کن..."
                            ></textarea>

                            <div class="mt-4">
                                <div class="mb-2 text-xs font-semibold text-base-content/50">Hook Type</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->hookTypes() as $value => $label)
                                        <button
                                            wire:click="setHookType({{ $index }}, '{{ $value }}')"
                                            type="button"
                                            class="btn btn-xs {{ ($hook['type'] ?? '') === $value ? 'btn-primary' : 'btn-outline' }}"
                                        >{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="mb-2 text-xs font-semibold text-base-content/50">Source</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->hookSources() as $value => $label)
                                        <button
                                            wire:click="setHookSource({{ $index }}, '{{ $value }}')"
                                            type="button"
                                            class="btn btn-xs {{ ($hook['source'] ?? '') === $value ? 'btn-secondary' : 'btn-outline' }}"
                                        >{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <details class="mt-4 rounded-xl bg-base-200/60 p-3">
                                <summary class="cursor-pointer text-xs font-semibold text-base-content/55">Advanced / Notes</summary>
                                <input wire:model="hooks.{{ $index }}.notes" type="text" class="input input-bordered mt-3 w-full" placeholder="توضیح اختیاری">
                            </details>
                        </article>
                    @endforeach
                </section>

                <button wire:click="addHook" type="button" class="btn btn-outline w-full border-dashed" @disabled(count($hooks) >= 20)>
                    <x-icon name="o-plus" class="size-4" />
                    Add another hook
                </button>

                <div
                    class="sticky bottom-3 z-10 rounded-2xl border border-base-300 bg-base-100/95 p-4 shadow-xl backdrop-blur"
                    wire:dirty.class="border-warning/60"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1 text-xs">
                            <div wire:show="$dirty || $wire.hasUnsavedChanges" class="flex items-center gap-2 font-semibold text-warning">
                                <x-icon name="o-exclamation-circle" class="size-4" />
                                تغییرات Hook ذخیره‌نشده است.
                            </div>
                            <div wire:show="!$dirty && !$wire.hasUnsavedChanges" class="text-base-content/45">
                                Hook فعلی ذخیره شده است.
                            </div>
                            <div class="text-base-content/50">
                                <span class="font-semibold">⌘/Ctrl + Enter</span> = ذخیره و رفتن به محتوای بعدی
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button wire:click="skip" wire:loading.attr="disabled" type="button" class="btn btn-ghost">رد کردن</button>
                            <button wire:click="save" wire:loading.attr="disabled" wire:dirty.class="ring-2 ring-warning/30" type="button" class="btn btn-outline">ذخیره</button>
                            <button wire:click="saveAndNext" wire:loading.attr="disabled" wire:dirty.class="ring-2 ring-warning/30" type="button" class="btn btn-primary">
                                Save & Next Missing
                                <x-icon name="o-arrow-left" class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    @endif
</div>
