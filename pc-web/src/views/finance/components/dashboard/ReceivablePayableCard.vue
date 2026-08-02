<template>
  <el-row :gutter="16">
    <el-col :span="12">
      <el-card shadow="never" class="mini-card">
        <template #header>
          <div class="card-title">应收（待收 + 部分收）</div>
        </template>
        <el-button type="primary" plain @click="$emit('goto', '/finance/receivable')">进入应收管理</el-button>
      </el-card>
    </el-col>
    <el-col :span="12">
      <el-card shadow="never" class="mini-card">
        <template #header>
          <div class="card-title">应付（待付 + 部分付）</div>
        </template>
        <el-button type="warning" plain @click="$emit('goto', '/finance/payable')">进入应付管理</el-button>
      </el-card>
    </el-col>
  </el-row>
</template>

<script setup lang="ts">
defineEmits<{ (e: 'goto', path: string): void }>()
</script>

<style lang="scss" scoped>
.mini-card { border: 1px solid #e6ebf2; }
.card-title { font-size: 14px; font-weight: 600; }
</style>
