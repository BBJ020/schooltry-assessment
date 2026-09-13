<script setup>
defineProps({ errors: { type: Object, default: () => ({}) } });
</script>

<template>
    <ul v-if="Object.keys(errors).length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <template v-for="(messages, field) in errors" :key="field">
            <li v-for="message in messages" :key="message">{{ message }}</li>
        </template>
    </ul>
</template>
