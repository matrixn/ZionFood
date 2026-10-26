<template>
    <section
        class="mt-10 rounded-2xl border border-slate-200/80 bg-white/60 p-4 text-xs leading-relaxed text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400 sm:p-5"
        data-testid="allergen-notice"
    >
        <div class="flex items-start gap-3">
            <i class="pi pi-info-circle mt-0.5 text-slate-400" aria-hidden="true" />
            <div class="min-w-0 flex-1 space-y-2">
                <h2 class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $t('allergen_heading') }}</h2>
                <p>{{ $t('allergen_intro') }}</p>
                <div class="grid gap-1 sm:grid-cols-2">
                    <p>{{ $t('allergen_marker') }}</p>
                    <p>{{ $t('frozen_marker') }}</p>
                </div>
                <details
                    class="rounded-xl border border-slate-200/70 bg-white/70 dark:border-slate-700 dark:bg-slate-900"
                >
                    <summary
                        class="cursor-pointer rounded-xl px-3 py-2 font-medium text-slate-600 focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300"
                    >
                        {{ $t('allergen_groups_toggle') }}
                    </summary>
                    <ol class="grid gap-2 px-3 pb-3 sm:grid-cols-2 lg:grid-cols-3">
                        <li
                            v-for="number in 14"
                            :key="number"
                            class="flex gap-2 rounded-lg bg-slate-50 px-2 py-1.5 dark:bg-slate-800"
                        >
                            <span class="font-medium text-slate-400">{{ number }}.</span
                            ><span>{{ $t('allergen_group_' + number) }}</span>
                        </li>
                    </ol>
                </details>
                <p>{{ $t('allergen_contact') }}</p>
                <a
                    class="inline-block underline underline-offset-2 hover:text-brand"
                    href="https://eur-lex.europa.eu/legal-content/RO/TXT/?uri=CELEX:02011R1169-20250401"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ $t('allergen_reference') }}</a
                >
            </div>
        </div>
    </section>
</template>
