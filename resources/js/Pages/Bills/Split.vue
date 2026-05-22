<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import WizardLayout from '../../Layouts/WizardLayout.vue';

const props = defineProps({ bill: Object, participants: Array, items: Array });

const form = useForm({
    split_mode: props.bill.split_mode || 'equal',
    tax_distribution: props.bill.tax_distribution || 'proportional',
    rounding_mode: props.bill.rounding_mode || 'exact',
    manual_amounts: Object.fromEntries(props.participants.map((p) => [p.id, p.amount_cents])),
    percentages: Object.fromEntries(props.participants.map((p) => [p.id, p.percentage_share || 0])),
    assignments: Object.fromEntries(props.items.map((i) => [i.id, i.assigned_participant_ids || []])),
    publish: false,
});

const manualSum = computed(() =>
    Object.values(form.manual_amounts).reduce((a, b) => a + Number(b || 0), 0),
);
const remaining = computed(() => props.bill.total_cents - manualSum.value);

function submit(publish = false) {
    form.publish = publish;
    form.post(`/bills/${props.bill.public_token}/split`);
}
</script>

<template>
    <WizardLayout :step="4">
        <h1 class="text-xl font-bold">Split bill</h1>
        <p class="text-sm text-stone-600">Total: {{ bill.total }}</p>

        <div class="mt-4 flex gap-2 overflow-x-auto text-sm">
            <button
                v-for="m in ['equal', 'manual', 'itemized', 'percentage']"
                :key="m"
                type="button"
                class="rounded-full px-3 py-1 capitalize"
                :class="form.split_mode === m ? 'bg-teal-600 text-white' : 'bg-stone-100'"
                @click="form.split_mode = m"
            >
                {{ m }}
            </button>
        </div>

        <div v-if="form.split_mode === 'manual'" class="mt-4 space-y-2">
            <p class="text-sm">Remaining: RM{{ (remaining / 100).toFixed(2) }}</p>
            <div v-for="p in participants" :key="p.id" class="flex items-center justify-between rounded-lg border bg-white p-3">
                <span>{{ p.name }}</span>
                <input v-model.number="form.manual_amounts[p.id]" type="number" class="w-28 rounded border px-2 py-1 text-sm" />
            </div>
        </div>

        <div v-if="form.split_mode === 'percentage'" class="mt-4 space-y-2">
            <div v-for="p in participants" :key="p.id" class="flex items-center justify-between rounded-lg border bg-white p-3">
                <span>{{ p.name }}</span>
                <input v-model.number="form.percentages[p.id]" type="number" class="w-20 rounded border px-2 py-1 text-sm" /> %
            </div>
        </div>

        <div v-if="form.split_mode === 'itemized'" class="mt-4 space-y-3">
            <div v-for="item in items" :key="item.id" class="rounded-lg border bg-white p-3 text-sm">
                <p class="font-medium">{{ item.name }} · {{ item.total }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <label v-for="p in participants" :key="p.id" class="flex items-center gap-1">
                        <input
                            type="checkbox"
                            :checked="form.assignments[item.id]?.includes(p.id)"
                            @change="
                                (e) => {
                                    const arr = form.assignments[item.id] || [];
                                    form.assignments[item.id] = e.target.checked
                                        ? [...arr, p.id]
                                        : arr.filter((id) => id !== p.id);
                                }
                            "
                        />
                        {{ p.name }}
                    </label>
                </div>
            </div>
        </div>

        <div class="fixed bottom-0 left-0 right-0 border-t bg-white p-4">
            <div class="mx-auto flex max-w-lg gap-2">
                <button type="button" class="flex-1 rounded-xl border py-3 text-sm font-medium" @click="submit(false)">Save split</button>
                <button type="button" class="flex-1 rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white" @click="submit(true)">
                    Publish
                </button>
            </div>
        </div>
    </WizardLayout>
</template>
