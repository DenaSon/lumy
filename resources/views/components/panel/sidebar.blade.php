<aside
    class="
        bg-base-100
        min-h-full
        w-64
        border-l
        border-base-300
        flex
        flex-col
    "
>
    <div class="px-5 py-5 border-b border-base-300">
        <div class="flex items-center gap-3">
            <div
                class="
                    size-11
                    rounded-2xl
                    bg-primary
                    text-primary-content
                    flex
                    items-center
                    justify-center
                    shadow-sm
                    shrink-0
                "
            >
                <x-icon name="o-bolt" class="w-5 h-5" />
            </div>

            <div>
                <h2 class="text-lg font-black leading-none">Lumy</h2>
                <p class="hidden lg:block text-xs text-base-content/60 mt-1">
                    Content Intelligence
                </p>
            </div>
        </div>
    </div>

    <div class="flex-1 py-3">
        <ul class="menu w-full px-3 gap-1">
            <li>
                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('dashboard') }}"
                >
                    <x-icon name="o-squares-2x2" class="w-5 h-5" />
                    <span>داشبورد</span>
                </a>
            </li>

            <li>
                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('content.index') }}"
                >
                    <x-icon name="o-rectangle-stack" class="w-5 h-5" />
                    <span>محتوا</span>
                </a>
            </li>

            <li>
                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('content.hooks') }}"
                >
                    <x-icon name="o-bolt" class="w-5 h-5" />
                    <span>ثبت سریع Hook</span>
                </a>
            </li>

            <li class="menu-title mt-3">
                <span>هوشمندی</span>
            </li>

            <li>
                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('intelligence.analysis') }}"
                >
                    <x-icon name="o-light-bulb" class="w-5 h-5" />
                    <span>تحلیل</span>
                </a>
            </li>

            <li>
                <a
                    wire:navigate
                    wire:current="menu-active"
                    href="{{ route('intelligence.index') }}"
                >
                    <x-icon name="o-adjustments-horizontal" class="w-5 h-5" />
                    <span>تنظیمات</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
