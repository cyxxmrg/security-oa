<template>
  <transition name="page-loader-fade">
    <div v-if="loading" class="page-loader">
      <div class="page-loader__bar"></div>
    </div>
  </transition>
</template>

<script setup lang="ts">
defineProps<{ loading: boolean }>()
</script>

<style scoped lang="scss">
.page-loader {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  z-index: 9999;
  pointer-events: none;
  background: rgba(12, 68, 124, 0.08);
  overflow: hidden;
}
.page-loader__bar {
  height: 100%;
  background: linear-gradient(90deg, #0C447C 0%, #1D9E75 100%);
  width: 30%;
  animation: pageLoaderSlide 1.2s ease-in-out infinite;
  box-shadow: 0 0 8px rgba(29, 158, 117, 0.6);
}
@keyframes pageLoaderSlide {
  0%   { transform: translateX(-100%); }
  50%  { transform: translateX(200%); width: 60%; }
  100% { transform: translateX(400%); width: 30%; }
}
.page-loader-fade-enter-active,
.page-loader-fade-leave-active {
  transition: opacity 0.25s;
}
.page-loader-fade-enter-from,
.page-loader-fade-leave-to {
  opacity: 0;
}
</style>
