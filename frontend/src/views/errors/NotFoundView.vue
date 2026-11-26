<script setup>
import { useRouter } from 'vue-router'
import { HomeIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const router = useRouter()

function goHome() {
  router.push('/')
}

function goBack() {
  router.back()
}
</script>

<template>
  <div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="text-center max-w-md">
      <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
        <svg class="w-12 h-12 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h1 class="text-4xl font-bold text-dark-900 dark:text-white mb-2">404</h1>
      <h2 class="text-xl text-dark-600 dark:text-dark-400 mb-4">Page Not Found</h2>
      <p class="text-dark-500 dark:text-dark-400 mb-8">
        Sorry, we couldn't find the page you're looking for. It might have been moved or doesn't exist.
      </p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button @click="goHome" class="btn-primary">
          <HomeIcon class="w-5 h-5" />
          Go to Dashboard
        </button>
        <button @click="goBack" class="btn-secondary">
          <ArrowLeftIcon class="w-5 h-5" />
          Go Back
        </button>
      </div>
    </div>
  </div>
</template>