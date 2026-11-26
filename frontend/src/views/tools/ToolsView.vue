<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-dark-900 dark:text-white">Tools</h1>
      <p class="text-dark-500 dark:text-dark-400 mt-1">Diagnostics, database and cache maintenance</p>
    </div>

    <div class="flex gap-2">
      <router-link to="/tools/diagnostics" class="btn-secondary" active-class="btn-primary">Diagnostics</router-link>
      <router-link to="/tools/database" class="btn-secondary" active-class="btn-primary">Database</router-link>
      <router-link to="/tools/cache" class="btn-secondary" active-class="btn-primary">Cache</router-link>
    </div>

    <RouterView />
  </div>
</template>
