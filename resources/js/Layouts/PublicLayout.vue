<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900">
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <Link href="/" class="text-lg font-bold text-teal-700">Splitlah</Link>
                <span class="text-xs text-stone-500">Snap · Split · Collect</span>
            </div>
        </header>
        <main class="mx-auto max-w-lg px-4 py-6">
            <slot name="flash" />
            <slot />
        </main>
    </div>
</template>
