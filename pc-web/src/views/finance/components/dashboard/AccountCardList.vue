<template>
  <el-row :gutter="16">
    <el-col v-for="acc in accounts" :key="acc.id" :span="6">
      <div class="account-card" :class="'account-' + acc.type">
        <div class="account-icon">
          <el-icon :size="28"><component :is="iconFn(acc.type || '')" /></el-icon>
        </div>
        <div class="account-info">
          <div class="account-name">{{ acc.name }}</div>
          <div class="account-no">{{ acc.account_no }}</div>
          <div class="account-balance" :class="Number(acc.balance) >= 0 ? 'positive' : 'negative'">
            ¥ {{ formatMoney(acc.balance) }}
          </div>
          <div class="account-meta">
            <el-tag size="small" :type="acc.status === 'active' ? 'success' : 'info'" effect="plain">
              {{ statusLabel(acc.status || '') }}
            </el-tag>
            <span class="bank-name">{{ acc.bank_name }}</span>
          </div>
        </div>
      </div>
    </el-col>
  </el-row>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { formatMoney } from './format'
import type { FinanceAccount } from '../../types'

defineProps<{
  accounts: FinanceAccount[]
  iconFn: (t: string) => Component
  statusLabel: (s: string) => string
}>()
</script>

<style lang="scss" scoped>
.account-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  margin-bottom: 16px;
  &-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; }
  &-name { font-size: 15px; font-weight: 600; }
  &-no { font-size: 12px; color: #909399; font-family: monospace; }
  &-balance { font-size: 22px; font-weight: 700; margin: 4px 0; }
  &-balance.positive { color: #1D9E75; }
  &-balance.negative { color: #A32D2D; }
  &-meta { display: flex; gap: 8px; align-items: center; }
}
.account-bank    { border-left: 3px solid #0C447C; }
.account-cash    { border-left: 3px solid #1D9E75; }
.account-online  { border-left: 3px solid #534AB7; }
.account-other   { border-left: 3px solid #909399; }
.bank-name { font-size: 12px; color: #909399; }
</style>
