<script setup lang="ts">
// import { dashboard, login, register } from '@/routes';
import { dashboard, login, register } from '../routes';
import { Head, Link } from '@inertiajs/vue3';
import { Ticket, Wrench } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome to Tech Ticket" />
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
        <!-- Header -->
        <header class="container mx-auto px-6 py-6">
            <nav class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary">
                        <Ticket class="h-6 w-6 text-primary-foreground" />
                    </div>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">Tech Ticket</span>
                </div>

                <div class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-6 py-2.5 text-sm font-semibold text-gray-700 hover:bg-white/50 transition dark:text-gray-200"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <main class="container mx-auto px-6 py-20">
            <div class="max-w-4xl mx-auto text-center">
                <!-- Logo -->
                <div class="mb-8 flex justify-center">
                    <div class="relative">
                        <div class="flex h-24 w-24 items-center justify-center rounded-2xl bg-primary shadow-2xl">
                            <Ticket class="h-12 w-12 text-primary-foreground" />
                        </div>
                        <div class="absolute -bottom-2 -right-2 flex h-10 w-10 items-center justify-center rounded-lg bg-orange-500 shadow-lg">
                            <Wrench class="h-5 w-5 text-white" />
                        </div>
                    </div>
                </div>

                <h1 class="mb-6 text-5xl font-bold text-gray-900 dark:text-white md:text-6xl">
                    Welcome to <span class="text-primary">Tech Ticket</span>
                </h1>

                <p class="mb-12 text-xl text-gray-600 dark:text-gray-300">
                    Your complete IT support ticket management system.<br />
                    Report issues, track progress, and get help faster.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="register()"
                        class="rounded-lg bg-primary px-8 py-4 text-lg font-semibold text-primary-foreground hover:bg-primary/90 transition shadow-lg"
                    >
                        Get Started
                    </Link>
                    <Link
                        :href="$page.props.auth.user ? dashboard() : login()"
                        class="rounded-lg bg-white px-8 py-4 text-lg font-semibold text-gray-900 hover:bg-gray-50 transition shadow-lg dark:bg-gray-800 dark:text-white"
                    >
                        {{ $page.props.auth.user ? 'Go to Dashboard' : 'Sign In' }}
                    </Link>
                </div>

                <!-- Features -->
                <div class="grid md:grid-cols-3 gap-8 mt-20">
                    <div class="p-6 rounded-xl bg-white shadow-lg dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                            <Ticket class="h-6 w-6 text-blue-600 dark:text-blue-300" />
                        </div>
                        <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Create Tickets</h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            Report IT issues with screenshots, descriptions, and priority levels.
                        </p>
                    </div>

                    <div class="p-6 rounded-xl bg-white shadow-lg dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Track Progress</h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            Monitor ticket status from Open to Closed with real-time updates.
                        </p>
                    </div>

                    <div class="p-6 rounded-xl bg-white shadow-lg dark:bg-gray-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900">
                            <svg class="h-6 w-6 text-purple-600 dark:text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                            </svg>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Collaborate</h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            Comment and communicate with IT staff directly on tickets.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="container mx-auto px-6 py-8 text-center text-sm text-gray-600 dark:text-gray-400">
            <p>&copy; {{ new Date().getFullYear() }} Tech Ticket. Built for efficient IT support management.</p>
        </footer>
    </div>
</template>
