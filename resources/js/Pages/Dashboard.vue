<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage()

Echo.private(`App.Models.User.${page.props.auth.user.id}`)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    });
Echo.channel(`notifications.${page.props.auth.user.id}`)
    .notification((data) => {
        console.log(data);
    });
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
