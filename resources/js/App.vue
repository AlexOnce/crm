<template>
  <router-view />
  <!-- Notificación Global en tiempo real para Códigos OTP de Meta/Facebook Linking -->
  <SystemVerificationCodeToast />
</template>

<script setup>
import SystemVerificationCodeToast from '@/components/notifications/SystemVerificationCodeToast.vue';
</script>
