<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import WizardLayout from '../../Layouts/WizardLayout.vue';

const form = useForm({
    title: '',
    organizer_name: '',
    organizer_email: '',
    description: '',
    due_date: '',
    participants: [
        { name: '', phone: '', email: '' },
        { name: '', phone: '', email: '' },
    ],
});

function addParticipant() {
    form.participants.push({ name: '', phone: '', email: '' });
}

function removeParticipant(i) {
    if (form.participants.length > 2) form.participants.splice(i, 1);
}

function submit() {
    form.post('/bills');
}
</script>

<template>
    <WizardLayout :step="1">
        <h1 class="text-xl font-bold">Bill details</h1>
        <p class="mt-1 text-sm text-stone-600">Add title, organizer, and participants.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="text-sm font-medium">Bill title</label>
                <input v-model="form.title" class="mt-1 w-full rounded-lg border border-stone-200 px-3 py-2" required />
            </div>
            <div>
                <label class="text-sm font-medium">Organizer name</label>
                <input v-model="form.organizer_name" class="mt-1 w-full rounded-lg border border-stone-200 px-3 py-2" required />
            </div>
            <div>
                <label class="text-sm font-medium">Organizer email (optional)</label>
                <input v-model="form.organizer_email" type="email" class="mt-1 w-full rounded-lg border border-stone-200 px-3 py-2" />
            </div>
            <div>
                <label class="text-sm font-medium">Due date (optional)</label>
                <input v-model="form.due_date" type="date" class="mt-1 w-full rounded-lg border border-stone-200 px-3 py-2" />
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium">Participants (min 2)</p>
                <div v-for="(p, i) in form.participants" :key="i" class="rounded-xl border border-stone-200 bg-white p-3">
                    <input v-model="p.name" placeholder="Name" class="mb-2 w-full rounded-lg border border-stone-200 px-3 py-2 text-sm" required />
                    <input v-model="p.phone" placeholder="Phone (WhatsApp)" class="mb-2 w-full rounded-lg border border-stone-200 px-3 py-2 text-sm" />
                    <button v-if="form.participants.length > 2" type="button" class="text-xs text-red-600" @click="removeParticipant(i)">Remove</button>
                </div>
                <button type="button" class="text-sm font-medium text-teal-700" @click="addParticipant">+ Add person</button>
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                Continue to receipt
            </button>
        </form>
    </WizardLayout>
</template>
