<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-stone-50">
        <header class="sticky top-0 z-10 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <Link href="/" class="font-bold text-teal-700">Splitlah</Link>
                <Link href="/bills/create" class="text-sm font-medium text-teal-700">New bill</Link>
            </div>
        </header>
        <main class="mx-auto max-w-lg px-4 py-6 pb-24">
            <slot name="flash" />
            <slot />
        </main>
    </div>
</template>
