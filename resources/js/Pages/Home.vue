<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });
</script>

<template>
    <section class="space-y-6">
        <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-teal-700">Splitlah</p>
            <h1 class="mt-2 text-2xl font-bold leading-tight">Split bills without the awkward chase.</h1>
            <p class="mt-3 text-sm text-stone-600">
                Snap a receipt, split fairly, share payment links, and track who has paid — built for Malaysian group payments.
            </p>
            <div class="mt-6 flex flex-col gap-3">
                <Link
                    href="/bills/create"
                    class="rounded-xl bg-teal-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-teal-700"
                >
                    Create a split bill
                </Link>
                <Link
                    href="/demo"
                    class="rounded-xl border border-stone-200 px-4 py-3 text-center text-sm font-medium text-stone-700 hover:bg-stone-50"
                >
                    Try demo bill
                </Link>
            </div>
        </div>

        <div class="grid gap-3 text-sm">
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="font-semibold">How it works</p>
                <p class="mt-1 text-stone-600">Snap → Review → Split → Share → Track</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div v-for="f in ['Receipt OCR', 'Auto split', 'Fiuu sandbox', 'WhatsApp share']" :key="f" class="rounded-lg border border-stone-100 bg-white p-3 text-xs font-medium">
                    {{ f }}
                </div>
            </div>
        </div>
    </section>
</template>
