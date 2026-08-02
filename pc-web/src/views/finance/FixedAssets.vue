<template>
  <div class="page-container">
    <div class="page-header">
      <h2>固定资产管理</h2>
      <span class="header-sub">资产台账 / 折旧 / 维修保养 / 盘点 / 报废处置 / 调拨</span>
    </div>

    <div class="content-card">
      <el-tabs v-model="activeTab">
        <!-- ============ 资产台账 ============ -->
        <el-tab-pane label="资产台账" name="assets">
          <div class="asset-layout">
            <div class="category-panel">
              <div class="category-head">
                <span>资产分类</span>
                <el-button link type="primary" size="small" :icon="Plus" @click="openCategoryDialog()">新增</el-button>
              </div>
              <el-tree
                :data="categoryTree"
                node-key="id"
                :props="{ label: 'name', children: 'children' }"
                highlight-current
                :expand-on-click-node="false"
                @node-click="onCategoryClick"
              >
                <template #default="{ data }">
                  <div class="tree-node">
                    <span>{{ data.name }}</span>
                    <el-dropdown trigger="click" @command="(cmd: string) => onCategoryCmd(cmd, data)">
                      <span class="tree-ops" @click.stop><el-icon><MoreFilled /></el-icon></span>
                      <template #dropdown>
                        <el-dropdown-menu>
                          <el-dropdown-item command="add">新增子分类</el-dropdown-item>
                          <el-dropdown-item command="edit">重命名</el-dropdown-item>
                          <el-dropdown-item command="del" divided>删除</el-dropdown-item>
                        </el-dropdown-menu>
                      </template>
                    </el-dropdown>
                  </div>
                </template>
              </el-tree>
            </div>

            <div class="asset-main">
              <div class="filter-bar">
                <el-select v-model="assetFilter.status" placeholder="状态" clearable style="width: 120px" @change="loadAssets(1)">
                  <el-option label="使用中" value="in_use" />
                  <el-option label="闲置" value="idle" />
                  <el-option label="维修中" value="repair" />
                  <el-option label="已报废" value="scrapped" />
                </el-select>
                <el-select v-model="assetFilter.source" placeholder="来源" clearable style="width: 130px" @change="loadAssets(1)">
                  <el-option label="手动录入" value="manual" />
                  <el-option label="工具打通" value="tool" />
                </el-select>
                <el-input v-model="assetFilter.keyword" placeholder="搜索名称/编号/规格" clearable style="width: 240px" :prefix-icon="Search" @keyup.enter="loadAssets(1)" @clear="loadAssets(1)" />
                <el-button type="primary" plain :icon="Plus" @click="openAssetDialog()">新增资产</el-button>
              </div>

              <el-table v-loading="assetsLoading" :data="assets" stripe border size="small">
                <el-table-column type="index" label="#" width="44" />
                <el-table-column label="资产编号" width="165">
                  <template #default="{ row }"><span class="asset-no">{{ row.asset_no }}</span></template>
                </el-table-column>
                <el-table-column prop="name" label="资产名称" min-width="140" show-overflow-tooltip />
                <el-table-column label="分类" width="110">
                  <template #default="{ row }">{{ row.category?.name || '未分类' }}</template>
                </el-table-column>
                <el-table-column label="规格" width="110" show-overflow-tooltip>
                  <template #default="{ row }">{{ row.specification || '-' }}</template>
                </el-table-column>
                <el-table-column prop="quantity" label="数量" width="60" align="center" />
                <el-table-column label="原值" width="100" align="right">
                  <template #default="{ row }">¥{{ Number(row.original_value || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="净值" width="100" align="right">
                  <template #default="{ row }">¥{{ Number(row.net_book_value || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="86" align="center">
                  <template #default="{ row }">
                    <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="来源" width="92" align="center">
                  <template #default="{ row }">
                    <el-tag v-if="row.source === 'tool'" type="primary" size="small" effect="plain">工具打通</el-tag>
                    <span v-else class="muted">手动</span>
                  </template>
                </el-table-column>
                <el-table-column label="保管人" width="90">
                  <template #default="{ row }">{{ row.keeper?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="110" fixed="right">
                  <template #default="{ row }">
                    <el-button link type="primary" size="small" @click="openAssetDetail(row)">详情</el-button>
                    <el-button link type="warning" size="small" @click="openAssetDialog(row)">编辑</el-button>
                    <el-button link type="danger" size="small" @click="deleteAsset(row)">删除</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div class="pagination-wrap">
                <el-pagination background layout="total, prev, pager, next" :total="assetsTotal" :current-page="assetsPage" :page-size="assetsPerPage" @current-change="(p: number) => loadAssets(p)" />
              </div>
            </div>
          </div>
        </el-tab-pane>

        <!-- ============ 折旧管理 ============ -->
        <el-tab-pane label="折旧管理" name="dep">
          <div class="filter-bar">
            <el-date-picker v-model="depPeriod" type="month" placeholder="选择月份" value-format="YYYY-MM" style="width: 160px" @change="loadDepreciations(1)" />
            <el-button type="primary" plain :loading="depreciating" @click="runDepreciate">本月计提折旧</el-button>
            <span class="muted">直线法：月折旧 = (原值 − 净残值) ÷ 使用月数，已提满/已报废自动跳过</span>
          </div>
          <el-table v-loading="depLoading" :data="depreciations" stripe border size="small">
            <el-table-column type="index" label="#" width="44" />
            <el-table-column prop="period" label="期间" width="100" align="center" />
            <el-table-column label="资产" min-width="180">
              <template #default="{ row }">{{ row.asset?.name }}（{{ row.asset?.asset_no }}）</template>
            </el-table-column>
            <el-table-column label="当月折旧" width="110" align="right">
              <template #default="{ row }"><span style="font-weight: 600; color: #A32D2D">¥{{ Number(row.month_depreciation).toFixed(2) }}</span></template>
            </el-table-column>
            <el-table-column label="累计折旧" width="110" align="right">
              <template #default="{ row }">¥{{ Number(row.accumulated_after).toFixed(2) }}</template>
            </el-table-column>
            <el-table-column label="净值" width="110" align="right">
              <template #default="{ row }">¥{{ Number(row.net_value_after).toFixed(2) }}</template>
            </el-table-column>
            <el-table-column prop="created_at" label="计提时间" width="160">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap">
            <el-pagination background layout="total, prev, pager, next" :total="depTotal" :current-page="depPage" :page-size="depPerPage" @current-change="(p: number) => loadDepreciations(p)" />
          </div>
        </el-tab-pane>

        <!-- ============ 维修保养 ============ -->
        <el-tab-pane label="维修保养" name="mt">
          <div class="filter-bar">
            <el-button type="primary" plain :icon="Plus" @click="openMtDialog">新增维修/保养</el-button>
          </div>
          <el-table v-loading="mtLoading" :data="maintenances" stripe border size="small">
            <el-table-column type="index" label="#" width="44" />
            <el-table-column label="资产" min-width="180">
              <template #default="{ row }">{{ row.asset?.name }}（{{ row.asset?.asset_no }}）</template>
            </el-table-column>
            <el-table-column label="类型" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.type === 'maintain' ? 'primary' : row.type === 'inspect' ? 'info' : 'warning'" size="small">{{ mtTypeLabel(row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="日期" width="110">
              <template #default="{ row }">{{ row.date || '-' }}</template>
            </el-table-column>
            <el-table-column label="费用" width="100" align="right">
              <template #default="{ row }">¥{{ Number(row.cost || 0).toFixed(2) }}</template>
            </el-table-column>
            <el-table-column prop="description" label="内容" min-width="160" show-overflow-tooltip />
            <el-table-column prop="result" label="结果" min-width="140" show-overflow-tooltip />
            <el-table-column label="经办人" width="90">
              <template #default="{ row }">{{ row.handler?.name || '-' }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap">
            <el-pagination background layout="total, prev, pager, next" :total="mtTotal" :current-page="mtPage" :page-size="mtPerPage" @current-change="(p: number) => loadMaintenances(p)" />
          </div>
        </el-tab-pane>

        <!-- ============ 盘点 ============ -->
        <el-tab-pane label="盘点管理" name="inv">
          <div class="filter-bar">
            <el-button type="primary" plain :icon="Plus" @click="openInvDialog">新建盘点单</el-button>
          </div>
          <el-table v-loading="invLoading" :data="inventories" stripe border size="small">
            <el-table-column type="index" label="#" width="44" />
            <el-table-column prop="no" label="盘点单号" width="180">
              <template #default="{ row }"><span class="asset-no">{{ row.no }}</span></template>
            </el-table-column>
            <el-table-column prop="date" label="盘点日期" width="120" />
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'done' ? 'success' : 'warning'" size="small">{{ row.status === 'done' ? '已完成' : '盘点中' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="明细" min-width="220">
              <template #default="{ row }">
                <el-tag v-for="it in (row.items || []).slice(0, 4)" :key="it.id" size="small" :type="(it.difference || 0) !== 0 ? 'danger' : 'info'" style="margin: 1px 3px 1px 0">
                  {{ it.asset?.name }} 实盘{{ it.actual_qty }}/账面{{ it.book_qty }}
                </el-tag>
                <span v-if="(row.items || []).length > 4" class="muted">+{{ row.items.length - 4 }}项</span>
              </template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
            <el-table-column label="操作" width="90" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" link type="success" size="small" @click="completeInventory(row)">完成</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap">
            <el-pagination background layout="total, prev, pager, next" :total="invTotal" :current-page="invPage" :page-size="invPerPage" @current-change="(p: number) => loadInventories(p)" />
          </div>
        </el-tab-pane>

        <!-- ============ 报废处置 ============ -->
        <el-tab-pane label="报废处置" name="disp">
          <div class="filter-bar">
            <el-button type="primary" plain :icon="Plus" @click="openDispDialog">新增处置</el-button>
          </div>
          <el-table v-loading="dispLoading" :data="disposals" stripe border size="small">
            <el-table-column type="index" label="#" width="44" />
            <el-table-column label="资产" min-width="180">
              <template #default="{ row }">{{ row.asset?.name }}（{{ row.asset?.asset_no }}）</template>
            </el-table-column>
            <el-table-column label="方式" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.method === 'sell' ? 'success' : row.method === 'donate' ? 'info' : 'danger'" size="small">{{ dispMethodLabel(row.method) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="date" label="日期" width="110" />
            <el-table-column label="残值收入" width="110" align="right">
              <template #default="{ row }">¥{{ Number(row.amount || 0).toFixed(2) }}</template>
            </el-table-column>
            <el-table-column prop="reason" label="原因" min-width="180" show-overflow-tooltip />
            <el-table-column label="经办人" width="90">
              <template #default="{ row }">{{ row.handler?.name || '-' }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap">
            <el-pagination background layout="total, prev, pager, next" :total="dispTotal" :current-page="dispPage" :page-size="dispPerPage" @current-change="(p: number) => loadDisposals(p)" />
          </div>
        </el-tab-pane>

        <!-- ============ 调拨 ============ -->
        <el-tab-pane label="调拨管理" name="tf">
          <div class="filter-bar">
            <el-button type="primary" plain :icon="Plus" @click="openTfDialog">新增调拨</el-button>
          </div>
          <el-table v-loading="tfLoading" :data="transfers" stripe border size="small">
            <el-table-column type="index" label="#" width="44" />
            <el-table-column label="资产" min-width="180">
              <template #default="{ row }">{{ row.asset?.name }}（{{ row.asset?.asset_no }}）</template>
            </el-table-column>
            <el-table-column prop="date" label="日期" width="110" />
            <el-table-column prop="from_location" label="调出地" min-width="120">
              <template #default="{ row }">{{ row.from_location || '-' }}</template>
            </el-table-column>
            <el-table-column prop="to_location" label="调入地" min-width="120">
              <template #default="{ row }">{{ row.to_location || '-' }}</template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
          </el-table>
          <div class="pagination-wrap">
            <el-pagination background layout="total, prev, pager, next" :total="tfTotal" :current-page="tfPage" :page-size="tfPerPage" @current-change="(p: number) => loadTransfers(p)" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 新增/编辑资产 -->
    <el-dialog v-model="assetDialogVisible" :title="assetForm.id ? '编辑资产' : '新增资产'" width="760px" :close-on-click-modal="false">
      <el-form ref="assetFormRef" :model="assetForm" :rules="assetRules" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="资产名称" prop="name"><el-input v-model="assetForm.name" placeholder="如：光功率计" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="资产分类">
              <el-select v-model="assetForm.category_id" clearable filterable placeholder="选择分类" style="width: 100%">
                <el-option v-for="c in flatCategories" :key="c.id" :label="c.name" :value="c.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规格型号"><el-input v-model="assetForm.specification" /></el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="单位"><el-input v-model="assetForm.unit" /></el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="数量"><el-input-number v-model="assetForm.quantity" :min="1" :step="1" style="width: 100%" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="原值(元)"><el-input-number v-model="assetForm.original_value" :min="0" :precision="2" :step="100" style="width: 100%" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="净残值(元)"><el-input-number v-model="assetForm.net_residual_value" :min="0" :precision="2" :step="10" style="width: 100%" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="使用年限(月)"><el-input-number v-model="assetForm.useful_life_months" :min="1" :max="600" :step="12" style="width: 100%" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="购置日期"><el-date-picker v-model="assetForm.acquisition_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态">
              <el-select v-model="assetForm.status" style="width: 100%">
                <el-option label="使用中" value="in_use" />
                <el-option label="闲置" value="idle" />
                <el-option label="维修中" value="repair" />
                <el-option label="已报废" value="scrapped" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="存放地"><el-input v-model="assetForm.location" /></el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="备注"><el-input v-model="assetForm.remark" type="textarea" :rows="2" maxlength="1000" show-word-limit /></el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="assetDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="assetSubmitting" @click="submitAsset">保存</el-button>
      </template>
    </el-dialog>

    <!-- 资产详情 -->
    <el-drawer v-model="detailVisible" title="资产详情" size="720px">
      <template v-if="assetDetail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="资产编号"><span class="asset-no">{{ assetDetail.asset_no }}</span></el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusTag(assetDetail.status)" size="small">{{ statusLabel(assetDetail.status) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="名称">{{ assetDetail.name }}</el-descriptions-item>
          <el-descriptions-item label="分类">{{ assetDetail.category?.name || '未分类' }}</el-descriptions-item>
          <el-descriptions-item label="规格">{{ assetDetail.specification || '-' }}</el-descriptions-item>
          <el-descriptions-item label="数量">{{ assetDetail.quantity }} {{ assetDetail.unit || '' }}</el-descriptions-item>
          <el-descriptions-item label="来源">
            <el-tag v-if="assetDetail.source === 'tool'" type="primary" size="small">工具打通</el-tag>
            <span v-else>手动录入</span>
          </el-descriptions-item>
          <el-descriptions-item label="保管人">{{ assetDetail.keeper?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="原值">¥{{ Number(assetDetail.original_value).toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="净值">¥{{ Number(assetDetail.net_book_value).toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="累计折旧">¥{{ Number(assetDetail.accumulated_depreciation).toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="月折旧额">¥{{ monthlyDep(assetDetail).toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="使用年限">{{ assetDetail.useful_life_months }} 月</el-descriptions-item>
          <el-descriptions-item label="购置日期">{{ assetDetail.acquisition_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="存放地">{{ assetDetail.location || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ assetDetail.remark || '-' }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="detail-h">折旧记录</h4>
        <el-table :data="assetDetail.depreciations || []" stripe border size="small" max-height="180">
          <el-table-column prop="period" label="期间" width="90" />
          <el-table-column label="当月折旧" width="100" align="right"><template #default="{ row }">¥{{ Number(row.month_depreciation).toFixed(2) }}</template></el-table-column>
          <el-table-column label="累计" width="100" align="right"><template #default="{ row }">¥{{ Number(row.accumulated_after).toFixed(2) }}</template></el-table-column>
          <el-table-column label="净值" width="100" align="right"><template #default="{ row }">¥{{ Number(row.net_value_after).toFixed(2) }}</template></el-table-column>
        </el-table>

        <h4 class="detail-h">维修保养</h4>
        <el-table :data="assetDetail.maintenances || []" stripe border size="small" max-height="160">
          <el-table-column prop="date" label="日期" width="100" />
          <el-table-column label="类型" width="80"><template #default="{ row }">{{ mtTypeLabel(row.type) }}</template></el-table-column>
          <el-table-column prop="description" label="内容" min-width="130" show-overflow-tooltip />
          <el-table-column prop="result" label="结果" min-width="120" show-overflow-tooltip />
        </el-table>

        <h4 class="detail-h">调拨记录</h4>
        <el-table :data="assetDetail.transfers || []" stripe border size="small" max-height="140">
          <el-table-column prop="date" label="日期" width="100" />
          <el-table-column prop="from_location" label="调出地" min-width="100"><template #default="{ row }">{{ row.from_location || '-' }}</template></el-table-column>
          <el-table-column prop="to_location" label="调入地" min-width="100"><template #default="{ row }">{{ row.to_location || '-' }}</template></el-table-column>
        </el-table>

        <h4 class="detail-h">报废处置</h4>
        <el-table :data="assetDetail.disposals || []" stripe border size="small" max-height="140">
          <el-table-column prop="date" label="日期" width="100" />
          <el-table-column label="方式" width="90"><template #default="{ row }">{{ dispMethodLabel(row.method) }}</template></el-table-column>
          <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
        </el-table>
      </template>
    </el-drawer>

    <!-- 分类弹窗 -->
    <el-dialog v-model="categoryDialogVisible" :title="categoryForm.id ? '重命名分类' : (categoryForm.parent_id ? '新增子分类' : '新增分类')" width="420px" :close-on-click-modal="false">
      <el-form label-width="80px">
        <el-form-item label="分类名称">
          <el-input v-model="categoryForm.name" placeholder="如：施工工具 / 检测仪器" maxlength="100" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="categoryDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="categorySubmitting" @click="submitCategory">保存</el-button>
      </template>
    </el-dialog>

    <!-- 维修保养弹窗 -->
    <el-dialog v-model="mtDialogVisible" title="新增维修/保养" width="620px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="资产" required>
          <el-select v-model="mtForm.asset_id" filterable remote :remote-method="searchAssetOptions" placeholder="搜索资产编号/名称" style="width: 100%">
            <el-option v-for="o in assetOptions" :key="o.id" :label="o.label" :value="o.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="类型">
            <el-select v-model="mtForm.type" style="width: 100%">
              <el-option label="维修" value="repair" />
              <el-option label="保养" value="maintain" />
              <el-option label="检测" value="inspect" />
            </el-select>
          </el-form-item></el-col>
          <el-col :span="12"><el-form-item label="日期"><el-date-picker v-model="mtForm.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="费用(元)"><el-input-number v-model="mtForm.cost" :min="0" :precision="2" style="width: 100%" /></el-form-item>
        <el-form-item label="内容"><el-input v-model="mtForm.description" type="textarea" :rows="2" maxlength="1000" /></el-form-item>
        <el-form-item label="结果"><el-input v-model="mtForm.result" type="textarea" :rows="2" maxlength="1000" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="mtDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="mtSubmitting" @click="submitMaintenance">保存</el-button>
      </template>
    </el-dialog>

    <!-- 盘点弹窗 -->
    <el-dialog v-model="invDialogVisible" title="新建盘点单" width="900px" :close-on-click-modal="false">
      <div class="filter-bar" style="margin-bottom: 10px; padding: 8px 12px">
        <el-date-picker v-model="invForm.date" type="date" value-format="YYYY-MM-DD" style="width: 150px" />
        <el-select v-model="invPickedAssets" multiple filterable remote :remote-method="searchAssetOptions" placeholder="搜索并选择要盘点的资产" style="flex: 1">
          <el-option v-for="o in assetOptions" :key="o.id" :label="o.label" :value="o.id" />
        </el-select>
        <el-button type="primary" plain size="small" :disabled="!invPickedAssets.length" @click="buildInventoryRows">加入盘点</el-button>
      </div>
      <el-table :data="invRows" stripe border size="small" max-height="360">
        <el-table-column type="index" label="#" width="44" />
        <el-table-column label="资产" min-width="180"><template #default="{ row }">{{ row.name }}（{{ row.asset_no }}）</template></el-table-column>
        <el-table-column label="账面数量" width="100" align="center"><template #default="{ row }">{{ row.book_qty }}</template></el-table-column>
        <el-table-column label="实盘数量" width="140">
          <template #default="{ row }"><el-input-number v-model="row.actual_qty" :min="0" :step="1" size="small" style="width: 110px" /></template>
        </el-table-column>
        <el-table-column label="差异" width="80" align="center">
          <template #default="{ row }">
            <span :style="{ fontWeight: 600, color: (row.actual_qty - row.book_qty) !== 0 ? '#A32D2D' : '#1D9E75' }">{{ row.actual_qty - row.book_qty }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="60" align="center">
          <template #default="{ $index }"><el-button type="danger" link size="small" :icon="Delete" @click="invRows.splice($index, 1)" /></template>
        </el-table-column>
      </el-table>
      <div style="margin-top: 10px"><el-input v-model="invForm.remark" placeholder="盘点备注(可选)" maxlength="500" /></div>
      <template #footer>
        <el-button @click="invDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="invSubmitting" :disabled="!invRows.length" @click="submitInventory">提交盘点</el-button>
      </template>
    </el-dialog>

    <!-- 报废处置弹窗 -->
    <el-dialog v-model="dispDialogVisible" title="新增报废处置" width="560px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="资产" required>
          <el-select v-model="dispForm.asset_id" filterable remote :remote-method="searchAssetOptions" placeholder="搜索资产编号/名称" style="width: 100%">
            <el-option v-for="o in assetOptions" :key="o.id" :label="o.label" :value="o.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="方式">
            <el-select v-model="dispForm.method" style="width: 100%">
              <el-option label="报废" value="scrap" />
              <el-option label="出售" value="sell" />
              <el-option label="捐赠" value="donate" />
            </el-select>
          </el-form-item></el-col>
          <el-col :span="12"><el-form-item label="日期"><el-date-picker v-model="dispForm.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="残值收入"><el-input-number v-model="dispForm.amount" :min="0" :precision="2" style="width: 100%" /></el-form-item>
        <el-form-item label="处置原因"><el-input v-model="dispForm.reason" type="textarea" :rows="2" maxlength="1000" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dispDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="dispSubmitting" @click="submitDisposal">确认处置</el-button>
      </template>
    </el-dialog>

    <!-- 调拨弹窗 -->
    <el-dialog v-model="tfDialogVisible" title="新增调拨" width="560px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="资产" required>
          <el-select v-model="tfForm.asset_id" filterable remote :remote-method="searchAssetOptions" placeholder="搜索资产编号/名称" style="width: 100%">
            <el-option v-for="o in assetOptions" :key="o.id" :label="o.label" :value="o.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="调出地"><el-input v-model="tfForm.from_location" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="调入地"><el-input v-model="tfForm.to_location" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="日期"><el-date-picker v-model="tfForm.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="tfForm.remark" type="textarea" :rows="2" maxlength="500" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="tfDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="tfSubmitting" @click="submitTransfer">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { Search, Plus, Delete, MoreFilled } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'

interface CategoryNode extends Record<string, unknown> { id: number; name: string; children?: CategoryNode[] }
interface AssetOption { id: number; label: string; asset_no?: string; name?: string; quantity?: number }

const activeTab = ref('assets')

const formatDate = (s?: string | null) => {
  if (!s) return '-'
  const t = s.replace('T', ' ').slice(0, 16)
  return t || s
}
type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'
const statusLabelMap: Record<string, string> = { in_use: '使用中', idle: '闲置', repair: '维修中', scrapped: '已报废' }
const statusLabel = (s?: string) => statusLabelMap[s || ''] || s || '-'
const statusTagMap: Record<string, TagType> = { in_use: 'success', idle: 'info', repair: 'warning', scrapped: 'danger' }
const statusTag = (s?: string): TagType => statusTagMap[s || ''] || 'info'
const mtTypeLabel = (t?: string) => ({ repair: '维修', maintain: '保养', inspect: '检测' } as Record<string, string>)[t || ''] || t || '-'
const dispMethodLabel = (m?: string) => ({ scrap: '报废', sell: '出售', donate: '捐赠' } as Record<string, string>)[m || ''] || m || '-'

const apiErr = (e: unknown, fallback: string) => {
  const err = e as { response?: { data?: { message?: string } }; message?: string }
  ElMessage.error(err?.response?.data?.message || err?.message || fallback)
}

// ===== 资产台账 =====
const assets = ref<Record<string, unknown>[]>([])
const assetsLoading = ref(false)
const assetsPage = ref(1)
const assetsPerPage = 15
const assetsTotal = ref(0)
const assetFilter = reactive({ keyword: '', status: '', source: '', category_id: null as number | null })

const categoryTree = ref<CategoryNode[]>([])
const flatCategories = computed(() => {
  const out: { id: number; name: string }[] = []
  const walk = (ns: CategoryNode[], prefix = '') => {
    for (const n of ns) {
      out.push({ id: n.id, name: prefix + n.name })
      if (n.children?.length) walk(n.children, prefix + '  ')
    }
  }
  walk(categoryTree.value)
  return out
})

async function loadCategories() {
  try {
    const res = await get('/finance/assets/categories/tree')
    categoryTree.value = unwrapList(res) as CategoryNode[]
  } catch (e) { console.warn('[loadCategories]', e) }
}

async function loadAssets(page = 1) {
  assetsPage.value = page
  assetsLoading.value = true
  try {
    const res = await get('/finance/assets', {
      page, per_page: assetsPerPage,
      keyword: assetFilter.keyword || undefined,
      status: assetFilter.status || undefined,
      source: assetFilter.source || undefined,
      category_id: assetFilter.category_id || undefined,
    })
    const pag = unwrapPaginate(res)
    assets.value = pag.list
    assetsTotal.value = pag.total
  } catch (e) { console.error('[loadAssets]', e); assets.value = []; assetsTotal.value = 0 }
  finally { assetsLoading.value = false }
}

function onCategoryClick(data: CategoryNode) {
  assetFilter.category_id = data.id
  loadAssets(1)
}

const monthlyDep = (row: { original_value?: number; net_residual_value?: number; useful_life_months?: number }) => {
  const months = Number(row.useful_life_months || 0)
  if (months <= 0) return 0
  return Math.max(0, Number(row.original_value || 0) - Number(row.net_residual_value || 0)) / months
}

// 分类 CRUD
const categoryDialogVisible = ref(false)
const categorySubmitting = ref(false)
const categoryForm = reactive({ id: null as number | null, name: '', parent_id: null as number | null })

function openCategoryDialog(parentId: number | null = null) {
  categoryForm.id = null
  categoryForm.parent_id = parentId
  categoryForm.name = ''
  categoryDialogVisible.value = true
}
function onCategoryCmd(cmd: string, data: CategoryNode) {
  if (cmd === 'add') openCategoryDialog(data.id)
  else if (cmd === 'edit') { categoryForm.id = data.id; categoryForm.parent_id = data.parent_id as number | null ?? null; categoryForm.name = data.name; categoryDialogVisible.value = true }
  else if (cmd === 'del') deleteCategory(data)
}
async function submitCategory() {
  if (!categoryForm.name.trim()) { ElMessage.warning('请输入分类名称'); return }
  categorySubmitting.value = true
  try {
    if (categoryForm.id) await put(`/finance/assets/categories/${categoryForm.id}`, { name: categoryForm.name })
    else await post('/finance/assets/categories', { name: categoryForm.name, parent_id: categoryForm.parent_id })
    ElMessage.success('保存成功')
    categoryDialogVisible.value = false
    await loadCategories()
  } catch (e: unknown) { apiErr(e, '保存失败') }
  finally { categorySubmitting.value = false }
}
async function deleteCategory(data: CategoryNode) {
  try { await ElMessageBox.confirm(`确认删除分类「${data.name}」?`, '删除确认', { type: 'warning' }) } catch { return }
  try {
    await del(`/finance/assets/categories/${data.id}`)
    ElMessage.success('已删除')
    await loadCategories()
  } catch (e: unknown) { apiErr(e, '删除失败') }
}

// 资产新增/编辑
const assetDialogVisible = ref(false)
const assetSubmitting = ref(false)
const assetFormRef = ref<FormInstance | null>(null)
const assetForm = reactive({
  id: null as number | null,
  name: '', category_id: null as number | null, specification: '', unit: '', quantity: 1,
  original_value: 0, net_residual_value: 0, useful_life_months: 60, acquisition_date: null as string | null,
  status: 'in_use', location: '', remark: '',
})
const assetRules = { name: [{ required: true, message: '请输入资产名称', trigger: 'blur' }] }

function openAssetDialog(row?: Record<string, unknown>) {
  assetForm.id = row ? Number(row.id) : null
  assetForm.name = row ? String(row.name || '') : ''
  assetForm.category_id = row ? Number(row.category_id || null) : null
  assetForm.specification = row ? String(row.specification || '') : ''
  assetForm.unit = row ? String(row.unit || '') : ''
  assetForm.quantity = row ? Number(row.quantity || 1) : 1
  assetForm.original_value = row ? Number(row.original_value || 0) : 0
  assetForm.net_residual_value = row ? Number(row.net_residual_value || 0) : 0
  assetForm.useful_life_months = row ? Number(row.useful_life_months || 60) : 60
  assetForm.acquisition_date = row ? (row.acquisition_date as string | null) ?? null : null
  assetForm.status = row ? String(row.status || 'in_use') : 'in_use'
  assetForm.location = row ? String(row.location || '') : ''
  assetForm.remark = row ? String(row.remark || '') : ''
  assetDialogVisible.value = true
  nextTick(() => assetFormRef.value?.clearValidate())
}

async function submitAsset() {
  if (!assetFormRef.value) return
  await assetFormRef.value.validate().catch(() => null)
  if (!assetForm.name.trim()) { ElMessage.warning('请输入资产名称'); return }
  assetSubmitting.value = true
  try {
    const payload = {
      name: assetForm.name, category_id: assetForm.category_id, specification: assetForm.specification,
      unit: assetForm.unit, quantity: assetForm.quantity, original_value: assetForm.original_value,
      net_residual_value: assetForm.net_residual_value, useful_life_months: assetForm.useful_life_months,
      acquisition_date: assetForm.acquisition_date, status: assetForm.status, location: assetForm.location, remark: assetForm.remark,
    }
    if (assetForm.id) await put(`/finance/assets/${assetForm.id}`, payload)
    else await post('/finance/assets', payload)
    ElMessage.success('保存成功')
    assetDialogVisible.value = false
    await loadAssets(assetsPage.value)
  } catch (e: unknown) { apiErr(e, '保存失败') }
  finally { assetSubmitting.value = false }
}

async function deleteAsset(row: Record<string, unknown>) {
  try { await ElMessageBox.confirm(`确认删除资产「${row.name}」(${row.asset_no})?`, '删除确认', { type: 'error' }) } catch { return }
  try {
    await del(`/finance/assets/${row.id}`)
    ElMessage.success('已删除')
    await loadAssets(assetsPage.value)
  } catch (e: unknown) { apiErr(e, '删除失败') }
}

// 资产详情
interface AssetDetail {
  id: number
  asset_no: string
  name: string
  status: string
  source: string
  specification?: string | null
  unit?: string | null
  quantity?: number
  original_value?: number
  net_residual_value?: number
  accumulated_depreciation?: number
  net_book_value?: number
  useful_life_months?: number
  acquisition_date?: string | null
  location?: string | null
  remark?: string | null
  category?: { id?: number; name?: string } | null
  keeper?: { id?: number; name?: string } | null
  depreciations?: Array<{ period?: string; month_depreciation?: number; accumulated_after?: number; net_value_after?: number }>
  maintenances?: Array<{ date?: string | null; type?: string; description?: string | null; result?: string | null }>
  transfers?: Array<{ date?: string | null; from_location?: string | null; to_location?: string | null }>
  disposals?: Array<{ date?: string | null; method?: string; reason?: string | null }>
}
const detailVisible = ref(false)
const assetDetail = ref<AssetDetail | null>(null)
async function openAssetDetail(row: Record<string, unknown>) {
  detailVisible.value = true
  assetDetail.value = null
  try {
    const res = await get(`/finance/assets/${row.id}`)
    assetDetail.value = (res?.data ?? res) as AssetDetail
  } catch (e: unknown) { console.error(e); detailVisible.value = false; apiErr(e, '加载详情失败') }
}

// 资产选择器 (维修/盘点/报废/调拨共用)
const assetOptions = ref<AssetOption[]>([])
async function searchAssetOptions(keyword: string) {
  try {
    const res = await get('/finance/assets', { keyword: keyword || undefined, per_page: 50 })
    const pag = unwrapPaginate(res)
    assetOptions.value = pag.list.map((a: Record<string, unknown>) => ({
      id: Number(a.id),
      asset_no: String(a.asset_no || ''),
      name: String(a.name || ''),
      quantity: Number(a.quantity || 1),
      label: `${a.asset_no} · ${a.name}${a.status === 'scrapped' ? '(已报废)' : ''}`,
    }))
  } catch (e) { console.warn('[searchAssetOptions]', e) }
}

// ===== 折旧 =====
const depPeriod = ref('')
const depreciations = ref<Record<string, unknown>[]>([])
const depLoading = ref(false)
const depPage = ref(1)
const depPerPage = 15
const depTotal = ref(0)
const depreciating = ref(false)

async function loadDepreciations(page = 1) {
  depPage.value = page
  depLoading.value = true
  try {
    const res = await get('/finance/assets/depreciations', { period: depPeriod.value || undefined, page, per_page: depPerPage })
    const pag = unwrapPaginate(res)
    depreciations.value = pag.list
    depTotal.value = pag.total
  } catch (e) { console.warn(e); depreciations.value = []; depTotal.value = 0 }
  finally { depLoading.value = false }
}
async function runDepreciate() {
  const period = depPeriod.value || new Date().toISOString().slice(0, 7)
  depreciating.value = true
  try {
    const res = await post('/finance/assets/depreciate', { period })
    const d = (res?.data ?? {}) as { depreciated?: number; skipped?: number }
    ElMessage.success(`计提完成: ${d.depreciated ?? 0} 条新增, ${d.skipped ?? 0} 条跳过`)
    await loadDepreciations(1)
  } catch (e: unknown) { apiErr(e, '计提失败') }
  finally { depreciating.value = false }
}

// ===== 维修保养 =====
const maintenances = ref<Record<string, unknown>[]>([])
const mtLoading = ref(false)
const mtPage = ref(1)
const mtPerPage = 15
const mtTotal = ref(0)
const mtDialogVisible = ref(false)
const mtSubmitting = ref(false)
const mtForm = reactive({ asset_id: null as number | null, type: 'repair', date: null as string | null, cost: 0, description: '', result: '' })

async function loadMaintenances(page = 1) {
  mtPage.value = page
  mtLoading.value = true
  try {
    const res = await get('/finance/assets/maintenances', { page, per_page: mtPerPage })
    const pag = unwrapPaginate(res)
    maintenances.value = pag.list
    mtTotal.value = pag.total
  } catch (e) { console.warn(e); maintenances.value = []; mtTotal.value = 0 }
  finally { mtLoading.value = false }
}
function openMtDialog() {
  mtForm.asset_id = null; mtForm.type = 'repair'; mtForm.date = null; mtForm.cost = 0; mtForm.description = ''; mtForm.result = ''
  assetOptions.value = []
  mtDialogVisible.value = true
}
async function submitMaintenance() {
  if (!mtForm.asset_id) { ElMessage.warning('请选择资产'); return }
  mtSubmitting.value = true
  try {
    await post('/finance/assets/maintenances', { ...mtForm })
    ElMessage.success('保存成功')
    mtDialogVisible.value = false
    await loadMaintenances(mtPage.value)
  } catch (e: unknown) { apiErr(e, '保存失败') }
  finally { mtSubmitting.value = false }
}

// ===== 盘点 =====
const inventories = ref<Record<string, unknown>[]>([])
const invLoading = ref(false)
const invPage = ref(1)
const invPerPage = 15
const invTotal = ref(0)
const invDialogVisible = ref(false)
const invSubmitting = ref(false)
const invForm = reactive({ date: null as string | null, remark: '' })
const invPickedAssets = ref<number[]>([])
const invRows = ref<{ asset_id: number; name: string; asset_no: string; book_qty: number; actual_qty: number }[]>([])

async function loadInventories(page = 1) {
  invPage.value = page
  invLoading.value = true
  try {
    const res = await get('/finance/assets/inventories', { page, per_page: invPerPage })
    const pag = unwrapPaginate(res)
    inventories.value = pag.list
    invTotal.value = pag.total
  } catch (e) { console.warn(e); inventories.value = []; invTotal.value = 0 }
  finally { invLoading.value = false }
}
function openInvDialog() {
  invForm.date = null; invForm.remark = ''; invPickedAssets.value = []; invRows.value = []; assetOptions.value = []
  invDialogVisible.value = true
}
function buildInventoryRows() {
  for (const id of invPickedAssets.value) {
    const o = assetOptions.value.find(x => x.id === id)
    if (o && !invRows.value.some(r => r.asset_id === id)) {
      invRows.value.push({ asset_id: id, name: o.name || '', asset_no: o.asset_no || '', book_qty: o.quantity || 1, actual_qty: o.quantity || 1 })
    }
  }
  invPickedAssets.value = []
}
async function submitInventory() {
  if (!invRows.value.length) { ElMessage.warning('请至少加入一项资产'); return }
  invSubmitting.value = true
  try {
    await post('/finance/assets/inventories', {
      date: invForm.date, remark: invForm.remark,
      items: invRows.value.map(r => ({ asset_id: r.asset_id, actual_qty: r.actual_qty })),
    })
    ElMessage.success('盘点单已创建')
    invDialogVisible.value = false
    await loadInventories(invPage.value)
  } catch (e: unknown) { apiErr(e, '创建失败') }
  finally { invSubmitting.value = false }
}
async function completeInventory(row: Record<string, unknown>) {
  try { await ElMessageBox.confirm(`确认完成盘点单「${row.no}」?`, '完成确认', { type: 'info' }) } catch { return }
  try {
    await post(`/finance/assets/inventories/${row.id}/complete`)
    ElMessage.success('盘点完成')
    await loadInventories(invPage.value)
  } catch (e: unknown) { apiErr(e, '操作失败') }
}

// ===== 报废处置 =====
const disposals = ref<Record<string, unknown>[]>([])
const dispLoading = ref(false)
const dispPage = ref(1)
const dispPerPage = 15
const dispTotal = ref(0)
const dispDialogVisible = ref(false)
const dispSubmitting = ref(false)
const dispForm = reactive({ asset_id: null as number | null, method: 'scrap', date: null as string | null, amount: 0, reason: '' })

async function loadDisposals(page = 1) {
  dispPage.value = page
  dispLoading.value = true
  try {
    const res = await get('/finance/assets/disposals', { page, per_page: dispPerPage })
    const pag = unwrapPaginate(res)
    disposals.value = pag.list
    dispTotal.value = pag.total
  } catch (e) { console.warn(e); disposals.value = []; dispTotal.value = 0 }
  finally { dispLoading.value = false }
}
function openDispDialog() {
  dispForm.asset_id = null; dispForm.method = 'scrap'; dispForm.date = null; dispForm.amount = 0; dispForm.reason = ''
  assetOptions.value = []
  dispDialogVisible.value = true
}
async function submitDisposal() {
  if (!dispForm.asset_id) { ElMessage.warning('请选择资产'); return }
  dispSubmitting.value = true
  try {
    await post('/finance/assets/disposals', { ...dispForm })
    ElMessage.success('处置完成, 资产已标记为报废')
    dispDialogVisible.value = false
    await loadDisposals(dispPage.value)
    await loadAssets(assetsPage.value)
  } catch (e: unknown) { apiErr(e, '操作失败') }
  finally { dispSubmitting.value = false }
}

// ===== 调拨 =====
const transfers = ref<Record<string, unknown>[]>([])
const tfLoading = ref(false)
const tfPage = ref(1)
const tfPerPage = 15
const tfTotal = ref(0)
const tfDialogVisible = ref(false)
const tfSubmitting = ref(false)
const tfForm = reactive({ asset_id: null as number | null, date: null as string | null, from_location: '', to_location: '', remark: '' })

async function loadTransfers(page = 1) {
  tfPage.value = page
  tfLoading.value = true
  try {
    const res = await get('/finance/assets/transfers', { page, per_page: tfPerPage })
    const pag = unwrapPaginate(res)
    transfers.value = pag.list
    tfTotal.value = pag.total
  } catch (e) { console.warn(e); transfers.value = []; tfTotal.value = 0 }
  finally { tfLoading.value = false }
}
function openTfDialog() {
  tfForm.asset_id = null; tfForm.date = null; tfForm.from_location = ''; tfForm.to_location = ''; tfForm.remark = ''
  assetOptions.value = []
  tfDialogVisible.value = true
}
async function submitTransfer() {
  if (!tfForm.asset_id) { ElMessage.warning('请选择资产'); return }
  tfSubmitting.value = true
  try {
    await post('/finance/assets/transfers', { ...tfForm })
    ElMessage.success('调拨成功')
    tfDialogVisible.value = false
    await loadTransfers(tfPage.value)
    await loadAssets(assetsPage.value)
  } catch (e: unknown) { apiErr(e, '操作失败') }
  finally { tfSubmitting.value = false }
}

onMounted(() => {
  loadAssets(1)
  loadCategories()
  loadDepreciations(1)
  loadMaintenances(1)
  loadInventories(1)
  loadDisposals(1)
  loadTransfers(1)
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { margin-bottom: 16px; display: flex; align-items: baseline; gap: 12px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
  .header-sub { font-size: 12px; color: #909399; }
}
.content-card { background: #fff; border-radius: 8px; padding: 8px 16px 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.filter-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 12px; }
.muted { color: #c0c4cc; }
.asset-no { font-family: "DIN Pro", monospace; font-weight: 600; color: #0C447C; font-size: 12px; }
.asset-layout { display: flex; gap: 16px; align-items: flex-start; }
.category-panel { width: 230px; flex-shrink: 0; border: 1px solid #e8ecf1; border-radius: 8px; padding: 10px; }
.category-head { display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600; color: #0C447C; margin-bottom: 8px; }
.tree-node { display: flex; align-items: center; justify-content: space-between; flex: 1; padding-right: 4px; }
.tree-ops { color: #c0c4cc; cursor: pointer; }
.asset-main { flex: 1; min-width: 0; }
.detail-h { margin: 14px 0 8px; font-size: 14px; color: #0C447C; }
:deep(.el-dialog__body) { padding-top: 12px; }
</style>
