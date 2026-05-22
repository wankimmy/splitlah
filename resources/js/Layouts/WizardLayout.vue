<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ step: { type: Number, default: 1 } });
const steps = ['Details', 'Receipt', 'Review', 'Split', 'Share'];
</script>

<template>
    <div class="min-h-screen bg-stone-50">
        <header class="border-b border-stone-200 bg-white px-4 py-3">
            <Link href="/" class="font-bold text-teal-700">Splitlah</Link>
            <div class="mt-3 flex gap-1 overflow-x-auto text-xs">
                <span
                    v-for="(label, i) in steps"
                    :key="label"
                    class="rounded-full px-2 py-1"
                    :class="i + 1 === step ? 'bg-teal-100 text-teal-800 font-medium' : 'text-stone-400'"
                >
                    {{ label }}
                </span>
            </div>
        </header>
        <main class="mx-auto max-w-lg px-4 py-6 pb-28">
            <slot />
        </main>
    </div>
</template>
