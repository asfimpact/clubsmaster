<script setup>
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import { avatarText, timeAgo } from '@core/utils/formatters'

definePage({
  name: 'admin-members',
  meta: {
    action: 'manage',
    subject: 'Admin',
  },
})

// 👉 States
const searchQuery = ref('')
const selectedStatus = ref()
const selectedPlan = ref()
const selectedRows = ref([])
const members = ref([])
const totalUsers = ref(0)
const loading = ref(false)

// 👉 Headers
const headers = [
  { title: 'USER ID', key: 'id' },
  { title: 'NAME', key: 'name' },
  { title: 'EMAIL-PHONE', key: 'contact' },
  { title: 'STATUS', key: 'computed_status' },
  { title: 'LATEST UPDATE', key: 'last_activity_at' },
  { title: 'PACKAGE', key: 'package' },
  { title: 'ACTIONS', key: 'actions', sortable: false },
]

// 👉 Filter Options
const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Suspended', value: 'suspended' },
  { title: 'Pending', value: 'pending' },
]

const planOptions = [
  { title: 'Basic', value: 'Basic' },
  { title: 'Standard', value: 'Standard' },
  { title: 'Gold', value: 'Gold' },
  { title: 'Pro', value: 'Pro' },
]

// 👉 Fetch Data
const fetchMembers = async () => {
  loading.value = true
  try {
    const response = await $api('/admin/members', {
      query: {
        q: searchQuery.value,
        status: selectedStatus.value,
        plan: selectedPlan.value,
      },
    })
    members.value = response.users
    totalUsers.value = response.totalUsers
  } catch (error) {
    console.error('Error fetching members:', error)
  } finally {
    loading.value = false
  }
}

// Watchers for instant filtering
watch([searchQuery, selectedStatus, selectedPlan], () => {
  fetchMembers()
}, { debounce: 500 })

// 👉 CSV Export Logic
const exportCSV = () => {
  if (!members.value.length) return

  const data = members.value.map(user => ({
    ID: user.id,
    Name: `${user.first_name} ${user.last_name}`,
    Email: user.email,
    Phone: user.phone || 'N/A',
    Status: user.computed_status,
    Package: user.subscription?.plan?.name || 'No Plan',
    Expiry: user.subscription?.expires_at ? new Date(user.subscription.expires_at).toLocaleDateString() : 'N/A',
    LastActivity: timeAgo(user.last_activity_at),
  }))

  const csvContent = "data:text/csv;charset=utf-8," 
    + [Object.keys(data[0]).join(","), ...data.map(row => Object.values(row).join(","))].join("\n")

  const encodedUri = encodeURI(csvContent)
  const link = document.createElement("a")
  link.setAttribute("href", encodedUri)
  link.setAttribute("download", "clubmaster_members.csv")
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// 👉 Dialog Logic
const isConfirmDialogVisible = ref(false)
const confirmActionType = ref('') // 'suspend', 'delete', 'bulkDelete'
const selectedMember = ref(null)

const confirmQuestion = computed(() => {
  if (confirmActionType.value === 'bulkDelete') return `Are you sure you want to delete ${selectedRows.value.length} selected members?`
  if (confirmActionType.value === 'delete') return 'Are you sure you want to delete this member?'
  return selectedMember.value?.status === 'active' ? 'Are you sure you want to suspend this member?' : 'Are you sure you want to activate this member?'
})

const openConfirmDialog = (item, type) => {
  selectedMember.value = item
  confirmActionType.value = type
  isConfirmDialogVisible.value = true
}

const handleConfirmAction = async (confirmed) => {
  if (!confirmed) return

  if (confirmActionType.value === 'bulkDelete') {
    await $api('/admin/members/bulk', { method: 'DELETE', body: { ids: selectedRows.value } })
    selectedRows.value = []
    fetchMembers()
  } else if (confirmActionType.value === 'delete') {
    await $api(`/admin/members/${selectedMember.value.id}`, { method: 'DELETE' })
    fetchMembers()
  } else {
    const newStatus = selectedMember.value.status === 'active' ? 'suspended' : 'active'
    await $api(`/admin/members/${selectedMember.value.id}`, { method: 'PATCH', body: { status: newStatus } })
    fetchMembers()
  }
}

const resolveStatusColor = (status) => {
  const stat = status?.toLowerCase()
  if (stat === 'active') return 'success'
  if (stat === 'suspended') return 'error'
  if (stat === 'pending') return 'warning'
  if (stat === 'inactive') return 'secondary'
  return 'primary'
}

const isExpired = (expiryDate) => {
  if (!expiryDate) return false
  return new Date(expiryDate) < new Date()
}

onMounted(fetchMembers)
</script>

<template>
  <section>
    <!-- 👉 Filters -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="selectedPlan"
              placeholder="Select Plan"
              :items="planOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="selectedStatus"
              placeholder="Select Status"
              :items="statusOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>
      
      <VDivider />

      <!-- 👉 Toolbar -->
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <VBtn
            v-if="selectedRows.length"
            color="error"
            variant="tonal"
            prepend-icon="tabler-trash"
            @click="openConfirmDialog(null, 'bulkDelete')"
          >
            Delete Selected ({{ selectedRows.length }})
          </VBtn>
        </div>

        <VSpacer />

        <div class="d-flex align-center flex-wrap gap-4">
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Member..."
              prepend-inner-icon="tabler-search"
            />
          </div>

          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-download"
            @click="exportCSV"
          >
            Export
          </VBtn>
        </div>
      </VCardText>

      <!-- 👉 Data Table -->
      <VDataTable
        v-model="selectedRows"
        :headers="headers"
        :items="members"
        :loading="loading"
        show-select
        class="text-no-wrap"
      >
        <!-- ID -->
        <template #item.id="{ item }">
          <span class="text-body-2 font-weight-medium">#{{ item.id }}</span>
        </template>

        <!-- Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center">
            <VAvatar size="34" color="primary" variant="tonal" class="me-3">
              <span class="text-sm">{{ avatarText(`${item.first_name} ${item.last_name}`) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base font-weight-medium mb-0">
                <RouterLink :to="{ name: 'admin-members-view-id', params: { id: item.id } }" class="text-link">
                  {{ item.first_name }} {{ item.last_name }}
                </RouterLink>
              </h6>
            </div>
          </div>
        </template>

        <!-- Email-Phone -->
        <template #item.contact="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 text-high-emphasis">{{ item.email }}</span>
            <span class="text-xs text-disabled">{{ item.phone || 'No Phone' }}</span>
          </div>
        </template>

        <!-- Status -->
        <template #item.computed_status="{ item }">
          <VChip :color="resolveStatusColor(item.computed_status)" size="small" label class="text-capitalize">
            {{ item.computed_status }}
          </VChip>
        </template>

        <!-- Latest Update -->
        <template #item.last_activity_at="{ item }">
          <span class="text-body-2">{{ timeAgo(item.last_activity_at) }}</span>
        </template>

        <!-- Package -->
        <template #item.package="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-medium text-high-emphasis text-capitalize">
              {{ item.subscription?.plan?.name || 'No Plan' }}
            </span>
            <span v-if="item.subscription?.expires_at" class="text-xs" :class="isExpired(item.subscription.expires_at) ? 'text-error' : 'text-disabled'">
              {{ isExpired(item.subscription.expires_at) ? 'Expired' : 'Expires' }}: {{ new Date(item.subscription.expires_at).toLocaleDateString() }}
            </span>
          </div>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click.stop>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList pill>
                <VListItem :to="{ name: 'admin-members-view-id', params: { id: item.id } }">
                  <template #prepend><VIcon icon="tabler-eye" /></template>
                  <VListItemTitle>View</VListItemTitle>
                </VListItem>
                <VListItem link>
                  <template #prepend><VIcon icon="tabler-pencil" /></template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>
                <VListItem @click="openConfirmDialog(item, 'suspend')">
                  <template #prepend><VIcon :icon="item.status === 'active' ? 'tabler-ban' : 'tabler-check'" /></template>
                  <VListItemTitle>{{ item.status === 'active' ? 'Suspend' : 'Activate' }}</VListItemTitle>
                </VListItem>
                <VListItem @click="openConfirmDialog(item, 'delete')">
                  <template #prepend><VIcon icon="tabler-trash" color="error" /></template>
                  <VListItemTitle class="text-error">Delete</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </IconBtn>
        </template>
      </VDataTable>
    </VCard>

    <!-- 👉 Confirm Dialog -->
    <ConfirmDialog
      v-model:is-dialog-visible="isConfirmDialogVisible"
      :confirmation-question="confirmQuestion"
      confirm-title="Action Successful"
      confirm-msg="The operation was completed successfully."
      cancel-title="Cancelled"
      cancel-msg="No changes were made."
      @confirm="handleConfirmAction"
    />
  </section>
</template>
