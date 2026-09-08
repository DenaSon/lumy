<?php

use App\ContentIntelligence\AnnotationCoverage;
use App\Models\ContentPillar;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.panel')] class extends Component
{
    public ?int $editingPillarId = null;
    public string $pillarName = '';
    public string $pillarSlug = '';
    public string $pillarDescription = '';
    public int $pillarSortOrder = 0;
    public bool $pillarIsActive = true;

    public ?int $editingTopicId = null;
    public ?int $topicPillarId = null;
    public string $topicName = '';
    public string $topicSlug = '';
    public string $topicDescription = '';
    public int $topicSortOrder = 0;
    public bool $topicIsActive = true;

    public string $savedMessage = '';

    #[Computed]
    public function coverage(): array
    {
        return app(AnnotationCoverage::class)->summary();
    }

    #[Computed]
    public function pillars(): Collection
    {
        return ContentPillar::query()
            ->withCount('topics')
            ->withCount(['annotations as primary_contents_count'])
            ->with(['topics' => fn ($query) => $query->withCount('contents')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function savePillar(): void
    {
        $validated = $this->validate([
            'pillarName' => ['required', 'string', 'max:255', Rule::unique('content_pillars', 'name')->ignore($this->editingPillarId)],
            'pillarSlug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('content_pillars', 'slug')->ignore($this->editingPillarId)],
            'pillarDescription' => ['nullable', 'string', 'max:5000'],
            'pillarSortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'pillarIsActive' => ['boolean'],
        ]);

        $pillar = $this->editingPillarId === null
            ? new ContentPillar()
            : ContentPillar::query()->findOrFail($this->editingPillarId);

        $pillar->fill([
            'name' => trim($validated['pillarName']),
            'slug' => trim($validated['pillarSlug']),
            'description' => $this->nullableTrim($validated['pillarDescription']),
            'sort_order' => $validated['pillarSortOrder'],
            'is_active' => $validated['pillarIsActive'],
        ])->save();

        $this->savedMessage = $this->editingPillarId === null ? 'Pillar ساخته شد.' : 'Pillar به‌روزرسانی شد.';
        $this->resetPillarForm();
        unset($this->pillars, $this->coverage);
    }

    public function editPillar(int $pillarId): void
    {
        $pillar = ContentPillar::query()->findOrFail($pillarId);

        $this->editingPillarId = $pillar->id;
        $this->pillarName = $pillar->name;
        $this->pillarSlug = $pillar->slug;
        $this->pillarDescription = $pillar->description ?? '';
        $this->pillarSortOrder = $pillar->sort_order;
        $this->pillarIsActive = $pillar->is_active;
        $this->savedMessage = '';
        $this->resetValidation();
    }

    public function togglePillar(int $pillarId): void
    {
        $pillar = ContentPillar::query()->findOrFail($pillarId);
        $pillar->update(['is_active' => ! $pillar->is_active]);

        $this->savedMessage = $pillar->fresh()->is_active ? 'Pillar فعال شد.' : 'Pillar غیرفعال شد.';
        unset($this->pillars);
    }

    public function resetPillarForm(): void
    {
        $this->reset('editingPillarId', 'pillarName', 'pillarSlug', 'pillarDescription');
        $this->pillarSortOrder = 0;
        $this->pillarIsActive = true;
        $this->resetValidation();
    }

    public function saveTopic(): void
    {
        $validated = $this->validate([
            'topicPillarId' => ['required', 'integer', 'exists:content_pillars,id'],
            'topicName' => ['required', 'string', 'max:255'],
            'topicSlug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('topics', 'slug')
                    ->where(fn ($query) => $query->where('content_pillar_id', $this->topicPillarId))
                    ->ignore($this->editingTopicId),
            ],
            'topicDescription' => ['nullable', 'string', 'max:5000'],
            'topicSortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'topicIsActive' => ['boolean'],
        ]);

        $topic = $this->editingTopicId === null
            ? new Topic()
            : Topic::query()->findOrFail($this->editingTopicId);

        $topic->fill([
            'content_pillar_id' => $validated['topicPillarId'],
            'name' => trim($validated['topicName']),
            'slug' => trim($validated['topicSlug']),
            'description' => $this->nullableTrim($validated['topicDescription']),
            'sort_order' => $validated['topicSortOrder'],
            'is_active' => $validated['topicIsActive'],
        ])->save();

        $this->savedMessage = $this->editingTopicId === null ? 'Topic ساخته شد.' : 'Topic به‌روزرسانی شد.';
        $this->resetTopicForm();
        unset($this->pillars, $this->coverage);
    }

    public function reorderTopic(string|int $topicId, int $position): void
    {
        $topic = Topic::query()->findOrFail((int) $topicId);
        $orderedIds = Topic::query()
            ->where('content_pillar_id', $topic->content_pillar_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $orderedIds = array_values(array_filter(
            $orderedIds,
            fn (int $id) => $id !== $topic->id,
        ));
        $position = max(0, min($position, count($orderedIds)));
        array_splice($orderedIds, $position, 0, [$topic->id]);

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                Topic::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        $this->savedMessage = 'ترتیب Topicها ذخیره شد.';
        unset($this->pillars);
    }

    public function editTopic(int $topicId): void
    {
        $topic = Topic::query()->findOrFail($topicId);

        $this->editingTopicId = $topic->id;
        $this->topicPillarId = $topic->content_pillar_id;
        $this->topicName = $topic->name;
        $this->topicSlug = $topic->slug;
        $this->topicDescription = $topic->description ?? '';
        $this->topicSortOrder = $topic->sort_order;
        $this->topicIsActive = $topic->is_active;
        $this->savedMessage = '';
        $this->resetValidation();
    }

    public function toggleTopic(int $topicId): void
    {
        $topic = Topic::query()->findOrFail($topicId);
        $topic->update(['is_active' => ! $topic->is_active]);

        $this->savedMessage = $topic->fresh()->is_active ? 'Topic فعال شد.' : 'Topic غیرفعال شد.';
        unset($this->pillars);
    }

    public function resetTopicForm(): void
    {
        $this->reset('editingTopicId', 'topicPillarId', 'topicName', 'topicSlug', 'topicDescription');
        $this->topicSortOrder = 0;
        $this->topicIsActive = true;
        $this->resetValidation();
    }

    public function formatRate(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate * 100, 1).'%';
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
};
?>

<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-primary">Content Intelligence Setup</p>
        <h1 class="mt-1 text-2xl font-black lg:text-3xl">Taxonomy و کیفیت Annotation</h1>
        <p class="mt-2 max-w-4xl text-sm text-base-content/60">
            taxonomy رسمی Lumixo را مدیریت کن و قبل از تحلیل، پوشش واقعی Content DNA را ببین. غیرفعال‌کردن taxonomy داده‌های تاریخی را حذف نمی‌کند.
        </p>
    </header>

    @if($savedMessage !== '')
        <div class="alert alert-success py-3 text-sm">
            <x-icon name="o-check-circle" class="size-5" />
            <span>{{ $savedMessage }}</span>
        </div>
    @endif

    @php($coverage = $this->coverage)

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-black">Annotation Coverage</h2>
                <p class="text-sm text-base-content/55">هر شاخص تعداد محتوایی است که آن بخش از Content DNA را واقعاً دارد.</p>
            </div>
            <div class="text-sm text-base-content/55">{{ number_format($coverage['total']) }} محتوا</div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
            @foreach([
                ['Analytics', 'with_metrics'],
                ['Annotated', 'annotated'],
                ['Primary Pillar', 'with_primary_pillar'],
                ['Primary Topic', 'with_primary_topic'],
                ['Primary Hook', 'with_primary_hook'],
                ['Hook Type', 'with_hook_type'],
                ['Hook Source', 'with_hook_source'],
                ['Goal', 'with_goal'],
            ] as [$label, $key])
                <div class="rounded-xl bg-base-200 p-3">
                    <div class="text-[11px] text-base-content/55">{{ $label }}</div>
                    <div class="mt-1 text-xl font-black">{{ number_format($coverage[$key]) }}</div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-base-300 p-3"><span class="text-xs text-base-content/55">Annotation Rate</span><div class="mt-1 font-mono font-bold">{{ $this->formatRate($coverage['annotation_rate']) }}</div></div>
            <div class="rounded-xl border border-base-300 p-3"><span class="text-xs text-base-content/55">Primary Pillar</span><div class="mt-1 font-mono font-bold">{{ $this->formatRate($coverage['primary_pillar_rate']) }}</div></div>
            <div class="rounded-xl border border-base-300 p-3"><span class="text-xs text-base-content/55">Primary Topic</span><div class="mt-1 font-mono font-bold">{{ $this->formatRate($coverage['primary_topic_rate']) }}</div></div>
            <div class="rounded-xl border border-base-300 p-3"><span class="text-xs text-base-content/55">Primary Hook</span><div class="mt-1 font-mono font-bold">{{ $this->formatRate($coverage['primary_hook_rate']) }}</div></div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Content Pillars</h2>
                    <p class="text-sm text-base-content/55">سطح اول taxonomy.</p>
                </div>
                @if($editingPillarId !== null)
                    <button type="button" wire:click="resetPillarForm" class="btn btn-ghost btn-sm">لغو ویرایش</button>
                @endif
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="form-control"><span class="label-text mb-1 text-xs">Name</span><input wire:model="pillarName" class="input input-bordered" placeholder="Linux" /></label>
                <label class="form-control"><span class="label-text mb-1 text-xs">Slug</span><input wire:model="pillarSlug" class="input input-bordered" placeholder="linux" dir="ltr" /></label>
                <label class="form-control"><span class="label-text mb-1 text-xs">Sort order</span><input wire:model="pillarSortOrder" type="number" min="0" class="input input-bordered" /></label>
                <label class="form-control justify-end"><span class="label cursor-pointer justify-start gap-3 pt-6"><input wire:model="pillarIsActive" type="checkbox" class="toggle toggle-primary" /><span class="label-text">Active</span></span></label>
                <label class="form-control md:col-span-2"><span class="label-text mb-1 text-xs">Description</span><textarea wire:model="pillarDescription" class="textarea textarea-bordered" rows="2"></textarea></label>
            </div>

            @error('pillarName') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
            @error('pillarSlug') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="savePillar" class="btn btn-primary btn-sm">
                    {{ $editingPillarId === null ? 'ساخت Pillar' : 'ذخیره Pillar' }}
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Topics</h2>
                    <p class="text-sm text-base-content/55">هر Topic دقیقاً به یک Pillar تعلق دارد؛ ترتیب نهایی را پایین صفحه drag کن.</p>
                </div>
                @if($editingTopicId !== null)
                    <button type="button" wire:click="resetTopicForm" class="btn btn-ghost btn-sm">لغو ویرایش</button>
                @endif
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="form-control md:col-span-2">
                    <span class="label-text mb-1 text-xs">Pillar</span>
                    <select wire:model="topicPillarId" class="select select-bordered">
                        <option value="">انتخاب Pillar</option>
                        @foreach($this->pillars as $pillar)
                            <option value="{{ $pillar->id }}">{{ $pillar->name }}{{ $pillar->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control"><span class="label-text mb-1 text-xs">Name</span><input wire:model="topicName" class="input input-bordered" placeholder="Permissions" /></label>
                <label class="form-control"><span class="label-text mb-1 text-xs">Slug</span><input wire:model="topicSlug" class="input input-bordered" placeholder="permissions" dir="ltr" /></label>
                <label class="form-control"><span class="label-text mb-1 text-xs">Initial sort order</span><input wire:model="topicSortOrder" type="number" min="0" class="input input-bordered" /></label>
                <label class="form-control justify-end"><span class="label cursor-pointer justify-start gap-3 pt-6"><input wire:model="topicIsActive" type="checkbox" class="toggle toggle-primary" /><span class="label-text">Active</span></span></label>
                <label class="form-control md:col-span-2"><span class="label-text mb-1 text-xs">Description</span><textarea wire:model="topicDescription" class="textarea textarea-bordered" rows="2"></textarea></label>
            </div>

            @error('topicPillarId') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
            @error('topicName') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
            @error('topicSlug') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="saveTopic" class="btn btn-primary btn-sm">
                    {{ $editingTopicId === null ? 'ساخت Topic' : 'ذخیره Topic' }}
                </button>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
        <div class="border-b border-base-300 px-5 py-4">
            <h2 class="text-lg font-black">Taxonomy فعلی</h2>
            <p class="text-sm text-base-content/55">Topicها را با دستگیره جابه‌جا کن؛ ترتیب همان لحظه ذخیره می‌شود. غیرفعال‌کردن رکوردها assignmentهای قبلی را حفظ می‌کند.</p>
        </div>

        @if($this->pillars->isEmpty())
            <div class="p-8 text-center text-sm text-base-content/55">هنوز هیچ Pillar ساخته نشده است.</div>
        @else
            <div class="divide-y divide-base-300">
                @foreach($this->pillars as $pillar)
                    <div class="p-5" wire:key="pillar-{{ $pillar->id }}">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-black">{{ $pillar->name }}</span>
                                    <span class="badge badge-outline badge-sm">{{ $pillar->slug }}</span>
                                    <span class="badge {{ $pillar->is_active ? 'badge-success' : 'badge-neutral' }} badge-sm">{{ $pillar->is_active ? 'Active' : 'Inactive' }}</span>
                                    <span class="text-xs text-base-content/45">order {{ $pillar->sort_order }}</span>
                                </div>
                                <div class="mt-1 text-xs text-base-content/55">
                                    {{ number_format($pillar->topics_count) }} topic · {{ number_format($pillar->primary_contents_count) }} primary content
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="editPillar({{ $pillar->id }})" class="btn btn-ghost btn-xs">ویرایش</button>
                                <button type="button" wire:click="togglePillar({{ $pillar->id }})" class="btn btn-ghost btn-xs">{{ $pillar->is_active ? 'غیرفعال' : 'فعال' }}</button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3" wire:sort="reorderTopic">
                            @forelse($pillar->topics as $topic)
                                <div
                                    class="rounded-xl border border-base-300 p-3"
                                    wire:key="topic-{{ $topic->id }}"
                                    wire:sort:item="{{ $topic->id }}"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 items-start gap-2">
                                            <button
                                                type="button"
                                                wire:sort:handle
                                                class="btn btn-ghost btn-xs cursor-grab px-1 active:cursor-grabbing"
                                                aria-label="جابجایی {{ $topic->name }}"
                                                title="Drag to reorder"
                                            >
                                                <x-icon name="o-bars-3" class="size-4" />
                                            </button>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-bold">{{ $topic->name }}</span>
                                                    <span class="badge {{ $topic->is_active ? 'badge-success' : 'badge-neutral' }} badge-xs">{{ $topic->is_active ? 'Active' : 'Inactive' }}</span>
                                                </div>
                                                <div class="mt-1 text-[11px] text-base-content/45">{{ $topic->slug }} · order {{ $topic->sort_order }} · {{ number_format($topic->contents_count) }} content</div>
                                            </div>
                                        </div>
                                        <div class="flex gap-1" wire:sort:ignore>
                                            <button type="button" wire:click="editTopic({{ $topic->id }})" class="btn btn-ghost btn-xs">Edit</button>
                                            <button type="button" wire:click="toggleTopic({{ $topic->id }})" class="btn btn-ghost btn-xs">{{ $topic->is_active ? 'Off' : 'On' }}</button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-base-content/45">Topic ندارد.</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
