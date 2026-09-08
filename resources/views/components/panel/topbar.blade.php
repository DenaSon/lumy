@props([
    'title' => null,
])

<header
    class="
        sticky
        top-0
        z-40
        border-b
        border-base-300
        bg-base-100/90
        backdrop-blur
    "
>

    <div
        class="
            navbar
            min-h-18
            px-4
            lg:px-6
        "
    >

        {{-- Right --}}
        <div class="navbar-start">

            <label
                for="panel-drawer"
                class="
                    btn
                    btn-ghost
                    btn-square
                    lg:hidden
                "
            >

                <x-icon
                    name="o-bars-3"
                    class="w-6 h-6"
                />

            </label>

            @if($title)

                <div
                    class="
                        mr-2
                    "
                >

                    <h1
                        class="
                            text-xl
                            font-black
                            leading-none
                        "
                    >
                        {{ $title }}
                    </h1>


                </div>

            @endif

        </div>

        {{-- Left --}}
        <div
            class="
                navbar-end
                gap-2
            "
        >

            <details class="dropdown dropdown-end">
                <summary class="btn btn-outline btn-sm list-none gap-2">
                    <x-icon name="o-arrow-down-tray" class="size-4" />
                    <span class="hidden sm:inline">Decision Context</span>
                </summary>
                <ul class="menu dropdown-content z-[60] mt-2 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl">
                    <li>
                        <a href="{{ route('decision-context.export', ['format' => 'json']) }}">
                            <x-icon name="o-code-bracket" class="size-4" />
                            Export JSON
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('decision-context.export', ['format' => 'markdown']) }}">
                            <x-icon name="o-document-text" class="size-4" />
                            Export Markdown
                        </a>
                    </li>
                </ul>
            </details>

            <div
                class="
                    hidden
                    lg:flex
                    badge
                    badge-success
                    badge-outline
                    gap-1
                "
            >

                <span
                    class="
                        size-2
                        rounded-full
                        bg-success
                    "
                ></span>

                فعال

            </div>

            <label
                class="
                    swap
                    swap-rotate
                    btn
                    btn-ghost
                    btn-circle
                "
            >

                <input
                    id="theme-toggle"
                    type="checkbox"
                    class="theme-controller"
                    value="dark"
                />

                <x-icon
                    name="o-sun"
                    class="
                        swap-off
                        w-5
                        h-5
                    "
                />

                <x-icon
                    name="o-moon"
                    class="
                        swap-on
                        w-5
                        h-5
                    "
                />

            </label>

        </div>

    </div>

</header>
