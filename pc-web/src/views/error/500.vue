<template>
  <ErrorLayout
    :code="500"
    title="服务暂时不可用"
    desc="服务器开了个小差,请稍后再试。如果问题持续存在,请联系系统管理员。"
    :show-retry="true"
  />
</template>

<script setup lang="ts">
import ErrorLayout from './ErrorLayout.vue'
</script>
