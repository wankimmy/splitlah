<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    errors: { type: Object, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const errorList = computed(() => {
    if (!props.errors) {
        return [];
    }

    return Object.values(props.errors).flat();
});
</script>

<template>
    <div v-if="flash.success || flash.error || errorList.length" class="space-y-2">
        <p v-if="flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ flash.success }}
        </p>
        <p v-if="flash.error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ flash.error }}
        </p>
        <ul v-if="errorList.length" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            <li v-for="(msg, i) in errorList" :key="i">{{ msg }}</li>
        </ul>
    </div>
</template>
