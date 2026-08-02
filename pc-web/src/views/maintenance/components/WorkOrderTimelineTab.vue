<template>
  <el-timeline>
    <el-timeline-item
      v-for="(ev, idx) in timeline"
      :key="idx"
      :type="ev.type"
      :timestamp="ev.time"
      placement="top"
    >
      <div class="timeline-content">
        <div class="timeline-title">{{ ev.title }}</div>
        <div class="timeline-desc">{{ ev.desc }}</div>
      </div>
    </el-timeline-item>
  </el-timeline>
</template>

<script setup lang="ts">
defineProps<{
  timeline: Record<string, unknown>[]
}>()
</script>

<style scoped>
.timeline-content { padding: 4px 0; }
.timeline-title { font-weight: 500; }
.timeline-desc { font-size: 12px; color: #606266; margin-top: 2px; }
</style>
