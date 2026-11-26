<script setup>
import { RouterView } from 'vue-router'
import ToastContainer from '@components/ui/ToastContainer.vue'
import ModalContainer from '@components/ui/ModalContainer.vue'
</script>

<template>
  <RouterView />
  <ToastContainer />
  <ModalContainer />
</template>
